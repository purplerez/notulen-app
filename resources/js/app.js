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
    availableQuotaMinutes: Number(config.initialAvailableQuotaMinutes || 0),
    gracePeriodMinutes: Number(config.gracePeriodMinutes || 15),
    quotaStatusUrl: config.quotaStatusUrl || '/quota/status',
    quotaBuyUrl: config.quotaBuyUrl || '/quota',
    inGracePeriod: false,
    graceSecondsLeft: Number(config.gracePeriodMinutes || 15) * 60,
    showTopUpModal: false,
    quotaCheckInterval: null,
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
                if (state === 'finished') {
                    if (this.timerInterval) clearInterval(this.timerInterval);
                    if (this.quotaCheckInterval) clearInterval(this.quotaCheckInterval);
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

    get formattedGraceLeft() {
        const mins = Math.floor(this.graceSecondsLeft / 60);
        const secs = this.graceSecondsLeft % 60;
        return [mins, secs].map(v => String(v).padStart(2, '0')).join(':');
    },

    async checkQuota() {
        try {
            const res = await fetch(this.quotaStatusUrl, {
                headers: { 'Accept': 'application/json' },
            });
            if (res.ok) {
                const data = await res.json();
                this.availableQuotaMinutes = data.available_minutes;
                if (data.available_minutes <= 0) {
                    this.inGracePeriod = true;
                } else {
                    this.inGracePeriod = false;
                    this.graceSecondsLeft = this.gracePeriodMinutes * 60;
                }
            }
        } catch (_) {
            // Keep going if network check temporarily fails
        }
    },

    async startRecording() {
        this.errorMessage = '';
        try {
            await this.session.start();
            this.timerInterval = setInterval(() => {
                if (this.state === 'recording') {
                    this.totalSeconds++;
                    if (this.inGracePeriod && this.graceSecondsLeft > 0) {
                        this.graceSecondsLeft--;
                        if (this.graceSecondsLeft <= 0) {
                            this.autoStopGracePeriod();
                        }
                    }
                }
            }, 1000);

            // Poll quota status every 25 seconds
            this.quotaCheckInterval = setInterval(() => {
                if (this.state === 'recording' || this.state === 'paused') {
                    this.checkQuota();
                }
            }, 25000);
        } catch (e) {
            this.errorMessage = e.message || 'Gagal memulai rekaman.';
        }
    },

    async autoStopGracePeriod() {
        this.errorMessage = 'Masa toleransi kuota 15 menit telah berakhir. Menyimpan seluruh rekaman audio secara aman...';
        await this.finishRecording(true);
    },

    pauseRecording() {
        this.session.pause();
    },

    resumeRecording() {
        this.session.resume();
    },

    async finishRecording(force = false) {
        if (!force && !confirm('Akhiri rekaman rapat ini? Seluruh segmen audio yang tersisa akan diunggah ke server.')) {
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
