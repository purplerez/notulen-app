<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property int $organization_id
 * @property int|null $meeting_id
 * @property int|null $actor_user_id
 * @property int|null $actor_attendee_id
 * @property string $action
 * @property array<string, mixed>|null $meta
 * @property Carbon $created_at
 */
class AuditLog extends Model
{
    use BelongsToOrganization, HasFactory;

    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'meeting_id',
        'actor_user_id',
        'actor_attendee_id',
        'action',
        'meta',
        'created_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Booted method to enforce append-only rules.
     */
    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('AuditLog is append-only. Updates are not allowed.');
        });

        static::deleting(function () {
            throw new LogicException('AuditLog is append-only. Deletions are not allowed.');
        });
    }

    /**
     * Prevent updates via save().
     */
    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new LogicException('AuditLog is append-only. Updates are not allowed.');
        }

        return parent::save($options);
    }

    /**
     * Prevent deletion.
     */
    public function delete(): ?bool
    {
        throw new LogicException('AuditLog is append-only. Deletions are not allowed.');
    }

    /**
     * Actor user relation.
     *
     * @return BelongsTo<User, $this>
     */
    public function actorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /**
     * Actor attendee relation (for public checkins).
     *
     * @return BelongsTo<Attendee, $this>
     */
    public function actorAttendee(): BelongsTo
    {
        return $this->belongsTo(Attendee::class, 'actor_attendee_id');
    }

    /**
     * Meeting relation.
     * Note: audit_logs doesn't have an FK constraint on meeting_id, so the meeting may have been deleted.
     *
     * @return BelongsTo<Meeting, $this>
     */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class, 'meeting_id');
    }

    /**
     * Human-readable Indonesian label for audit log action.
     */
    public function getActionLabelAttribute(): string
    {
        return match ($this->action) {
            'audio_play' => 'Pemutaran Audio',
            'audio_merge' => 'Penggabungan Audio',
            'audio_purge' => 'Pembersihan Audio (Retensi)',
            'transcript_view' => 'Melihat Transkrip Mentah',
            'transcript_edit' => 'Penyuntingan Transkrip',
            'transcript_reset' => 'Reset Transkrip ke STT',
            'transcript_assemble' => 'Perakitan Transkrip',
            'minute_draft_created' => 'Draf Notulen Dibuat',
            'minute_finalize' => 'Finalisasi Notulen',
            'minute_revise' => 'Pengajuan Revisi Notulen',
            'minute_acknowledge' => 'Konfirmasi Diketahui Notulen',
            'attendance_closed' => 'Penutupan Absensi',
            'attendance_corrected' => 'Koreksi Data Absensi',
            'attendee_manual_checkin' => 'Presensi Manual',
            'attendee_checkin' => 'Presensi Mandiri QR',
            'guest_approved' => 'Persetujuan Tamu',
            'guest_rejected' => 'Penolakan Tamu',
            'recording_start' => 'Mulai Rekaman Rapat',
            'recording_finish' => 'Selesai Rekaman Rapat',
            'meeting_create' => 'Pembuatan Room Rapat',
            'meeting_delete' => 'Penghapusan Rapat',
            'user_create' => 'Pembuatan Akun Notulis',
            'login' => 'Masuk Aplikasi (Login)',
            'logout' => 'Keluar Aplikasi (Logout)',
            'stt_waiting_quota' => 'Menunggu Kuota STT',
            'stt_failed' => 'Kegagalan STT',
            'stt_retry_manual' => 'Coba Ulang Transkripsi',
            'minute_pdf_export' => 'Ekspor PDF Notulen',
            'attendance_pdf_export' => 'Ekspor PDF Berita Acara',
            'kop_uploaded' => 'Unggah Kop Surat',
            'kop_deleted' => 'Penghapusan Kop Surat',
            'link_create' => 'Pembuatan Tautan Berbagi',
            'link_open' => 'Pembukaan Tautan Berbagi',
            'link_revoke' => 'Pencabutan Tautan Berbagi',
            'link_pdf_download' => 'Unduh PDF via Tautan Publik',
            'platform_login' => 'Masuk Platform (Super Admin)',
            'platform_login_2fa' => 'Verifikasi 2FA Platform',
            'platform_logout' => 'Keluar Platform (Super Admin)',
            'platform_dashboard_view' => 'Melihat Dashboard Platform',
            'platform_org_view' => 'Melihat Detail Lembaga Platform',
            'platform_export_csv' => 'Ekspor CSV Biaya Lembaga',
            'platform_rate_create' => 'Penambahan Tarif Layanan',
            'platform_fx_update' => 'Pembaruan Kurs USD/IDR',
            'platform_reconciliation_save' => 'Pencatatan Rekonsiliasi Tagihan',
            'storage_cost_calculated' => 'Perhitungan Biaya Penyimpanan',
            'quota_order_create' => 'Pemesanan Kuota Rekam',
            'quota_order_proof_upload' => 'Unggah Bukti Bayar Kuota',
            'quota_order_cancel' => 'Pembatalan Pesanan Kuota',
            'quota_order_paid' => 'Pembayaran Kuota Berhasil',
            'quota_order_approved' => 'Verifikasi Pesanan Kuota (Super Admin)',
            'quota_order_rejected' => 'Penolakan Pesanan Kuota (Super Admin)',
            'recording_grace_period_start' => 'Pemberian Masa Toleransi Kuota Rekam',
            default => str_replace('_', ' ', ucfirst($this->action)),
        };
    }
}
