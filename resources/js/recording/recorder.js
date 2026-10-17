import { enqueueSegment, getPendingSegments, removeSegment, updateSegmentRetry, countPendingSegments } from './db.js';

/**
 * Negotiate optimal audio MIME type supported by the client browser.
 */
export function getSupportedMimeType() {
    if (typeof MediaRecorder === 'undefined') {
        return '';
    }

    const candidates = [
        'audio/webm;codecs=opus',
        'audio/webm',
        'audio/mp4;codecs=mp4a.40.2',
        'audio/mp4',
        'audio/aac',
        'audio/ogg;codecs=opus',
        'audio/wav',
    ];

    for (const mime of candidates) {
        if (MediaRecorder.isTypeSupported(mime)) {
            return mime;
        }
    }

    return '';
}

/**
 * Calculate SHA-256 hash string for an audio Blob using crypto.subtle.
 */
export async function calculateSha256(blob) {
    const arrayBuffer = await blob.arrayBuffer();
    const hashBuffer = await crypto.subtle.digest('SHA-256', arrayBuffer);
    const hashArray = Array.from(new Uint8Array(hashBuffer));
    return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
}

/**
 * Meeting Audio Recording Session Controller.
 */
export class MeetingRecordingSession {
    constructor(config) {
        this.meetingId = Number(config.meetingId);
        this.csrfToken = config.csrfToken;
        this.endpoints = config.endpoints; // { start, upload, finish }
        this.maxDurationMinutes = Number(config.maxDurationMinutes || 180);
        this.segmentDurationMs = Number(config.segmentDurationMs || 60000); // ~60 seconds per segment
        this.onStateChange = config.onStateChange || (() => {});
        this.onLevelChange = config.onLevelChange || (() => {});
        this.onQueueUpdate = config.onQueueUpdate || (() => {});

        // Internal states
        this.state = 'idle'; // 'idle' | 'recording' | 'paused' | 'finishing' | 'finished' | 'error'
        this.currentSeq = 1;
        this.confirmedCount = 0;
        this.pendingCount = 0;
        this.stream = null;
        this.mediaRecorder = null;
        this.currentChunks = [];
        this.segmentStartTime = null;
        this.rotationTimer = null;
        this.elapsedTimer = null;
        this.totalElapsedSeconds = 0;
        this.wakeLock = null;
        this.audioContext = null;
        this.analyser = null;
        this.analyserData = null;
        this.analyserInterval = null;
        this.uploadWorkerTimer = null;
        this.isUploading = false;
        this.selectedMime = getSupportedMimeType();

        this.setupVisibilityListener();
        this.setupOnlineListener();
    }

    /**
     * Start the recording session.
     */
    async start() {
        if (!window.isSecureContext) {
            throw new Error('Perekaman audio membutuhkan koneksi aman (HTTPS).');
        }

        if (this.state === 'recording') {
            return;
        }

        try {
            // 1. Notify server
            const res = await fetch(this.endpoints.start, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                    'Accept': 'application/json',
                },
            });

            if (!res.ok) {
                const err = await res.json().catch(() => ({ message: 'Gagal memulai rekaman di server.' }));
                throw new Error(err.message || 'Gagal memulai rekaman di server.');
            }

            // 2. Request microphone stream
            this.stream = await navigator.mediaDevices.getUserMedia({
                audio: {
                    echoCancellation: true,
                    noiseSuppression: true,
                    autoGainControl: true,
                },
            });

            // 3. Audio analyser for live sound level meter
            this.setupAudioAnalyser(this.stream);

            // 4. Request Screen Wake Lock
            await this.requestWakeLock();

            // 5. Update state
            this.state = 'recording';
            this.onStateChange(this.state);

            // 6. Start elapsed timer
            this.startElapsedTimer();

            // 7. Start background upload queue processor
            this.startUploadWorker();

            // 8. Start recording initial segment
            this.startNewSegment();

        } catch (error) {
            this.cleanup();
            this.state = 'error';
            this.onStateChange(this.state, error.message);
            throw error;
        }
    }

    /**
     * Start a new independent standalone audio segment.
     */
    startNewSegment() {
        if (this.state !== 'recording') {
            return;
        }

        this.currentChunks = [];
        this.segmentStartTime = Date.now();

        const options = this.selectedMime ? { mimeType: this.selectedMime } : {};
        try {
            this.mediaRecorder = new MediaRecorder(this.stream, options);
        } catch (e) {
            // Fallback without mime options
            this.mediaRecorder = new MediaRecorder(this.stream);
        }

        const seqForThisSegment = this.currentSeq;

        this.mediaRecorder.ondataavailable = (event) => {
            if (event.data && event.data.size > 0) {
                this.currentChunks.push(event.data);
            }
        };

        this.mediaRecorder.onstop = async () => {
            const chunks = [...this.currentChunks];
            const durationSec = Math.max(0.5, ((Date.now() - (this.segmentStartTime || Date.now())) / 1000));

            if (chunks.length > 0) {
                const mime = this.mediaRecorder?.mimeType || this.selectedMime || 'audio/webm';
                const segmentBlob = new Blob(chunks, { type: mime });

                try {
                    const checksum = await calculateSha256(segmentBlob);
                    await enqueueSegment({
                        meetingId: this.meetingId,
                        seq: seqForThisSegment,
                        blob: segmentBlob,
                        checksum,
                        durationSec,
                        mimeType: mime,
                    });

                    await this.syncQueueCount();
                    this.triggerUploadImmediate();
                } catch (e) {
                    console.error('Gagal mengenqueue segmen audio:', e);
                }
            }

            // If we are still actively recording, immediately spin up next segment
            if (this.state === 'recording') {
                this.currentSeq++;
                this.startNewSegment();
            }
        };

        // Start recording WITHOUT timeslice so chunks form one complete standalone container file
        this.mediaRecorder.start();

        // Schedule segment rotation after segmentDurationMs (~60 seconds)
        if (this.rotationTimer) {
            clearTimeout(this.rotationTimer);
        }
        this.rotationTimer = setTimeout(() => {
            this.rotateSegment();
        }, this.segmentDurationMs);
    }

    /**
     * Rotate to next segment cleanly by stopping current MediaRecorder.
     */
    rotateSegment() {
        if (this.mediaRecorder && this.mediaRecorder.state === 'recording') {
            this.mediaRecorder.stop();
        }
    }

    /**
     * Pause the recording session.
     */
    pause() {
        if (this.state !== 'recording') return;

        // Stop current segment so recorded audio up to pause is captured
        if (this.mediaRecorder && this.mediaRecorder.state === 'recording') {
            this.mediaRecorder.stop();
        }

        if (this.rotationTimer) {
            clearTimeout(this.rotationTimer);
            this.rotationTimer = null;
        }
        if (this.elapsedTimer) {
            clearInterval(this.elapsedTimer);
            this.elapsedTimer = null;
        }

        this.state = 'paused';
        this.onStateChange(this.state);
    }

    /**
     * Resume the recording session.
     */
    resume() {
        if (this.state !== 'paused') return;

        this.state = 'recording';
        this.onStateChange(this.state);

        this.startElapsedTimer();
        this.requestWakeLock();

        // Increment sequence and start next standalone segment
        this.currentSeq++;
        this.startNewSegment();
    }

    /**
     * Complete and finish the recording session.
     */
    async finish() {
        if (this.state === 'finishing' || this.state === 'finished') {
            return;
        }

        this.state = 'finishing';
        this.onStateChange(this.state);

        // 1. Stop active segment rotation
        if (this.rotationTimer) {
            clearTimeout(this.rotationTimer);
            this.rotationTimer = null;
        }

        // 2. Stop recorder if active (flushes final partial segment)
        if (this.mediaRecorder && this.mediaRecorder.state === 'recording') {
            await new Promise((resolve) => {
                const origOnStop = this.mediaRecorder.onstop;
                this.mediaRecorder.onstop = async (e) => {
                    if (origOnStop) await origOnStop(e);
                    resolve();
                };
                this.mediaRecorder.stop();
            });
        }

        // 3. Stop media stream and wake lock
        this.stopStream();
        this.releaseWakeLock();

        const expectedSegments = this.currentSeq;

        // 4. Flush all pending uploads in IndexedDB
        let remaining = await countPendingSegments(this.meetingId);
        let flushAttempts = 0;

        while (remaining > 0 && flushAttempts < 30) {
            await this.processUploadQueue();
            remaining = await countPendingSegments(this.meetingId);
            if (remaining > 0) {
                await new Promise(r => setTimeout(r, 1000));
            }
            flushAttempts++;
        }

        // 5. Call finish endpoint on server
        const finishRes = await fetch(this.endpoints.finish, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': this.csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                expected_segments: expectedSegments,
            }),
        });

        const finishData = await finishRes.json().catch(() => ({}));

        if (!finishRes.ok) {
            if (finishData.missing_segments && finishData.missing_segments.length > 0) {
                this.state = 'finishing';
                this.onStateChange(this.state, `Segmen ${finishData.missing_segments.join(', ')} belum lengkap di server. Mengunggah ulang...`);
                // Trigger queue processor to retry
                await this.processUploadQueue();
                throw new Error(`Segmen audio belum lengkap di server (${finishData.missing_segments.length} segmen kurang). Silakan tunggu proses pengunggahan selesai.`);
            }

            throw new Error(finishData.message || 'Gagal menyelesaikan sesi perekaman.');
        }

        this.state = 'finished';
        this.cleanup();
        this.onStateChange(this.state);

        return finishData;
    }

    /**
     * Background Upload Queue Worker with Exponential Backoff.
     */
    startUploadWorker() {
        if (this.uploadWorkerTimer) return;

        this.uploadWorkerTimer = setInterval(() => {
            this.processUploadQueue();
        }, 3000);
    }

    triggerUploadImmediate() {
        setTimeout(() => this.processUploadQueue(), 100);
    }

    async processUploadQueue() {
        if (this.isUploading) return;
        if (!navigator.onLine) {
            await this.syncQueueCount();
            return;
        }

        this.isUploading = true;

        try {
            const pending = await getPendingSegments(this.meetingId);

            for (const item of pending) {
                // Check backoff delay
                if (item.retryCount > 0 && item.lastAttemptAt) {
                    const delayMs = Math.min(1000 * Math.pow(2, item.retryCount - 1), 30000);
                    if (Date.now() - item.lastAttemptAt < delayMs) {
                        continue;
                    }
                }

                const success = await this.uploadSingleSegment(item);
                if (success) {
                    await removeSegment(this.meetingId, item.seq);
                    this.confirmedCount++;
                }
            }
        } catch (e) {
            console.error('Error in processUploadQueue:', e);
        } finally {
            this.isUploading = false;
            await this.syncQueueCount();
        }
    }

    /**
     * Upload an individual segment to server.
     */
    async uploadSingleSegment(item) {
        const formData = new FormData();
        const ext = item.mimeType?.includes('mp4') ? 'mp4' : 'webm';
        formData.append('file', item.blob, `seg-${item.seq}.${ext}`);
        formData.append('seq', String(item.seq));
        formData.append('checksum', item.checksum);
        if (item.durationSec) {
            formData.append('duration_sec', String(item.durationSec));
        }

        try {
            const res = await fetch(this.endpoints.upload, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': this.csrfToken,
                    'Accept': 'application/json',
                },
                body: formData,
            });

            if (res.status === 200 || res.status === 201) {
                return true;
            }

            const errorData = await res.json().catch(() => ({}));
            await updateSegmentRetry(this.meetingId, item.seq, errorData.message || `HTTP ${res.status}`);
            return false;
        } catch (networkError) {
            await updateSegmentRetry(this.meetingId, item.seq, networkError.message);
            return false;
        }
    }

    async syncQueueCount() {
        this.pendingCount = await countPendingSegments(this.meetingId);
        this.onQueueUpdate({
            pendingCount: this.pendingCount,
            confirmedCount: this.confirmedCount,
            currentSeq: this.currentSeq,
        });
    }

    /**
     * Elapsed time counter.
     */
    startElapsedTimer() {
        if (this.elapsedTimer) clearInterval(this.elapsedTimer);
        this.elapsedTimer = setInterval(() => {
            this.totalElapsedSeconds++;
        }, 1000);
    }

    /**
     * Live Sound Level Meter via AnalyserNode.
     */
    setupAudioAnalyser(stream) {
        try {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) return;

            this.audioContext = new AudioCtx();
            const source = this.audioContext.createMediaStreamSource(stream);
            this.analyser = this.audioContext.createAnalyser();
            this.analyser.fftSize = 256;
            source.connect(this.analyser);

            this.analyserData = new Uint8Array(this.analyser.frequencyBinCount);

            this.analyserInterval = setInterval(() => {
                if (this.state !== 'recording') {
                    this.onLevelChange(0);
                    return;
                }
                this.analyser.getByteFrequencyData(this.analyserData);
                let sum = 0;
                for (let i = 0; i < this.analyserData.length; i++) {
                    sum += this.analyserData[i];
                }
                const avg = sum / this.analyserData.length;
                const levelPercent = Math.min(100, Math.round((avg / 128) * 100));
                this.onLevelChange(levelPercent);
            }, 100);
        } catch (e) {
            console.warn('AudioContext AnalyserNode not available:', e);
        }
    }

    /**
     * Screen Wake Lock management.
     */
    async requestWakeLock() {
        if ('wakeLock' in navigator) {
            try {
                this.wakeLock = await navigator.wakeLock.request('screen');
                this.wakeLock.addEventListener('release', () => {
                    this.wakeLock = null;
                });
            } catch (err) {
                console.warn('Screen Wake Lock request failed:', err);
            }
        }
    }

    releaseWakeLock() {
        if (this.wakeLock) {
            this.wakeLock.release().catch(() => {});
            this.wakeLock = null;
        }
    }

    setupVisibilityListener() {
        document.addEventListener('visibilitychange', async () => {
            if (document.visibilityState === 'visible' && this.state === 'recording') {
                // Re-request wake lock when user switches back to tab
                await this.requestWakeLock();
            }
        });
    }

    setupOnlineListener() {
        window.addEventListener('online', () => {
            this.triggerUploadImmediate();
        });
    }

    stopStream() {
        if (this.stream) {
            this.stream.getTracks().forEach(track => track.stop());
            this.stream = null;
        }
        if (this.audioContext) {
            this.audioContext.close().catch(() => {});
            this.audioContext = null;
        }
        if (this.analyserInterval) {
            clearInterval(this.analyserInterval);
            this.analyserInterval = null;
        }
    }

    cleanup() {
        this.stopStream();
        this.releaseWakeLock();
        if (this.rotationTimer) {
            clearTimeout(this.rotationTimer);
            this.rotationTimer = null;
        }
        if (this.elapsedTimer) {
            clearInterval(this.elapsedTimer);
            this.elapsedTimer = null;
        }
        if (this.uploadWorkerTimer) {
            clearInterval(this.uploadWorkerTimer);
            this.uploadWorkerTimer = null;
        }
    }
}
