<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AudioPlaybackController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CheckinController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MeetingAttendeeController;
use App\Http\Controllers\MeetingController;
use App\Http\Controllers\MeetingPdfController;
use App\Http\Controllers\MinuteController;
use App\Http\Controllers\OrganizationKopController;
use App\Http\Controllers\PublicShareController;
use App\Http\Controllers\RecordingController;
use App\Http\Controllers\ShareLinkController;
use App\Http\Controllers\TranscriptController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Welcome / landing page
Route::get('/', function () {
    return view('welcome');
});

// Public check-in route for participants (/c/{meeting}?code=...)
Route::get('/c/{meeting}', [CheckinController::class, 'show'])->name('checkin.show');
Route::post('/c/{meeting}', [CheckinController::class, 'store'])->name('checkin.store');

// Public share link routes for meeting minutes (/n/{token})
Route::get('/n/{token}', [PublicShareController::class, 'show'])->name('public_share.show');
Route::post('/n/{token}/verify', [PublicShareController::class, 'verify'])->name('public_share.verify');
Route::get('/n/{token}/pdf', [PublicShareController::class, 'pdf'])->name('public_share.pdf');

// Guest routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

// Authenticated tenant routes
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit_logs.index');

    // User management (creating notulis accounts)
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');

    // Meeting rooms & attendees
    Route::get('/meetings', [MeetingController::class, 'index'])->name('meetings.index');
    Route::get('/meetings/create', [MeetingController::class, 'create'])->name('meetings.create');
    Route::post('/meetings', [MeetingController::class, 'store'])->name('meetings.store');
    Route::get('/meetings/{meeting}', [MeetingController::class, 'show'])->name('meetings.show');
    Route::get('/meetings/{meeting}/edit', [MeetingController::class, 'edit'])->name('meetings.edit');
    Route::put('/meetings/{meeting}', [MeetingController::class, 'update'])->name('meetings.update');
    Route::delete('/meetings/{meeting}', [MeetingController::class, 'destroy'])->name('meetings.destroy');

    Route::post('/meetings/{meeting}/attendees', [MeetingAttendeeController::class, 'store'])->name('meetings.attendees.store');
    Route::delete('/meetings/{meeting}/attendees/{attendee}', [MeetingAttendeeController::class, 'destroy'])->name('meetings.attendees.destroy');

    // Attendance host screen, live QR polling, manual attendance, closing, and recap
    Route::get('/meetings/{meeting}/attendance', [AttendanceController::class, 'hostScreen'])->name('meetings.attendance.host');
    Route::get('/meetings/{meeting}/attendance/qr-data', [AttendanceController::class, 'qrData'])->name('meetings.attendance.qr_data');
    Route::post('/meetings/{meeting}/attendees/{attendee}/manual', [AttendanceController::class, 'manualCheckin'])->name('meetings.attendance.manual');
    Route::post('/meetings/{meeting}/attendees/{attendee}/approve-guest', [AttendanceController::class, 'approveGuest'])->name('meetings.attendance.approve_guest');
    Route::post('/meetings/{meeting}/attendees/{attendee}/reject-guest', [AttendanceController::class, 'rejectGuest'])->name('meetings.attendance.reject_guest');
    Route::post('/meetings/{meeting}/close-attendance', [AttendanceController::class, 'closeAttendance'])->name('meetings.attendance.close');
    Route::put('/meetings/{meeting}/attendees/{attendee}/status', [AttendanceController::class, 'updateStatus'])->name('meetings.attendance.update_status');
    Route::get('/meetings/{meeting}/recap', [AttendanceController::class, 'recap'])->name('meetings.attendance.recap');

    // Audio recording session, live segment upload, and finish
    Route::get('/meetings/{meeting}/record', [RecordingController::class, 'show'])->name('meetings.record');
    Route::post('/meetings/{meeting}/start-recording', [RecordingController::class, 'start'])->name('meetings.record.start');
    Route::post('/meetings/{meeting}/segments', [RecordingController::class, 'uploadSegment'])->name('meetings.record.segments');
    Route::post('/meetings/{meeting}/finish', [RecordingController::class, 'finish'])->name('meetings.record.finish');
    Route::post('/meetings/{meeting}/retry-transcription', [RecordingController::class, 'retryTranscription'])->name('meetings.record.retry_transcription');

    // Secure audio playback (short-lived signed URL, Range request support)
    Route::get('/meetings/{meeting}/audio/stream', [AudioPlaybackController::class, 'stream'])
        ->name('meetings.audio.stream')
        ->middleware('signed');

    // Raw transcript viewing and manual editing
    Route::get('/meetings/{meeting}/transcript', [TranscriptController::class, 'show'])->name('meetings.transcript.show');
    Route::put('/meetings/{meeting}/transcript/{segment}', [TranscriptController::class, 'update'])->name('meetings.transcript.update');
    Route::post('/meetings/{meeting}/transcript/{segment}/reset', [TranscriptController::class, 'reset'])->name('meetings.transcript.reset');

    // Minutes: show, edit, update, finalize, revise, acknowledge
    Route::get('/meetings/{meeting}/minutes', [MinuteController::class, 'show'])->name('meetings.minutes.show');
    Route::get('/meetings/{meeting}/minutes/edit', [MinuteController::class, 'edit'])->name('meetings.minutes.edit');
    Route::put('/meetings/{meeting}/minutes', [MinuteController::class, 'update'])->name('meetings.minutes.update');
    Route::post('/meetings/{meeting}/minutes/finalize', [MinuteController::class, 'finalize'])->name('meetings.minutes.finalize');
    Route::post('/meetings/{meeting}/minutes/revise', [MinuteController::class, 'revise'])->name('meetings.minutes.revise');
    Route::post('/meetings/{meeting}/minutes/{minute}/acknowledge', [MinuteController::class, 'acknowledge'])->name('meetings.minutes.acknowledge');

    // Public share links management
    Route::post('/meetings/{meeting}/share-links', [ShareLinkController::class, 'store'])->name('meetings.share_links.store');
    Route::post('/meetings/{meeting}/share-links/{shareLink}/revoke', [ShareLinkController::class, 'revoke'])->name('meetings.share_links.revoke');

    // Organization Kop management & preview
    Route::get('/organization/kop', [OrganizationKopController::class, 'show'])->name('organization.kop.show');
    Route::post('/organization/kop', [OrganizationKopController::class, 'update'])->name('organization.kop.update');
    Route::delete('/organization/kop', [OrganizationKopController::class, 'destroy'])->name('organization.kop.destroy');
    Route::get('/organization/kop/preview', [OrganizationKopController::class, 'preview'])->name('organization.kop.preview');

    // PDF generation (Minutes & Attendance Recap)
    Route::get('/meetings/{meeting}/minutes/pdf', [MeetingPdfController::class, 'minute'])->name('meetings.minutes.pdf');
    Route::get('/meetings/{meeting}/minutes/{minute}/pdf', [MeetingPdfController::class, 'minute'])->name('meetings.minutes.version.pdf');
    Route::get('/meetings/{meeting}/attendance/pdf', [MeetingPdfController::class, 'attendanceRecap'])->name('meetings.attendance.pdf');
});
