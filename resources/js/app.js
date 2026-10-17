import Alpine from 'alpinejs';
import QRCode from 'qrcode';
import { MeetingRecordingSession, getSupportedMimeType } from './recording/recorder.js';

window.Alpine = Alpine;
window.QRCode = QRCode;
window.MeetingRecordingSession = MeetingRecordingSession;

Alpine.data('meetingRecorder', (config) => ({
    meetingId: config.meetingId,
    csrfToken: config.csrfToken,
    endpoints: config.endpoints,
    maxDurationMinutes: config.maxDurationMinutes || 180,
    session: null,
    state: 'idle', // idle, recording, paused, finishing, finished, error
    errorMessage: '',
    soundLevel: 0,
    totalSeconds: 0,
    timerInterval: null,
    pendingUploads: 0,
    confirmedUploads: 0,
    currentSeq: 1,
    isOnline: navigator.onLine,
    supportedMime: getSupportedMimeType(),
    isSecure: window.isSecureContext,

    init() {
        window.addEventListener('online', () => { this.isOnline = true; });
        window.addEventListener('offline', () => { this.isOnline = false; });

        this.session = new MeetingRecordingSession({
            meetingId: this.meetingId,
            csrfToken: this.csrfToken,
            endpoints: this.endpoints,
            maxDurationMinutes: this.maxDurationMinutes,
            onStateChange: (state, errorMsg) => {
                this.state = state;
                if (errorMsg) this.errorMessage = errorMsg;
                if (state === 'finished' && this.timerInterval) {
                    clearInterval(this.timerInterval);
                }
            },
            onLevelChange: (lvl) => {
                this.soundLevel = lvl;
            },
            onQueueUpdate: (status) => {
                this.pendingUploads = status.pendingCount;
                this.confirmedUploads = status.confirmedCount;
                this.currentSeq = status.currentSeq;
            },
        });
    },

    get formattedDuration() {
        const hrs = Math.floor(this.totalSeconds / 3600);
        const mins = Math.floor((this.totalSeconds % 3600) / 60);
        const secs = this.totalSeconds % 60;
        return [hrs, mins, secs].map(v => String(v).padStart(2, '0')).join(':');
    },

    async startRecording() {
        this.errorMessage = '';
        try {
            await this.session.start();
            this.timerInterval = setInterval(() => {
                if (this.state === 'recording') {
                    this.totalSeconds++;
                }
            }, 1000);
        } catch (e) {
            this.errorMessage = e.message || 'Gagal memulai rekaman.';
        }
    },

    pauseRecording() {
        this.session.pause();
    },

    resumeRecording() {
        this.session.resume();
    },

    async finishRecording() {
        if (!confirm('Akhiri rekaman rapat ini? Seluruh segmen audio yang tersisa akan diunggah ke server.')) {
            return;
        }

        this.errorMessage = '';
        try {
            const result = await this.session.finish();
            if (result && result.redirect_url) {
                window.location.href = result.redirect_url;
            }
        } catch (e) {
            this.errorMessage = e.message || 'Gagal menyelesaikan sesi perekaman.';
        }
    },
}));

Alpine.start();
