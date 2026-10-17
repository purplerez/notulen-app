<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CheckinController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MeetingAttendeeController;
use App\Http\Controllers\MeetingController;
use App\Http\Controllers\RecordingController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Welcome / landing page
Route::get('/', function () {
    return view('welcome');
});

// Public check-in route for participants (/c/{meeting}?code=...)
Route::get('/c/{meeting}', [CheckinController::class, 'show'])->name('checkin.show');
Route::post('/c/{meeting}', [CheckinController::class, 'store'])->name('checkin.store');

// Guest routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

// Authenticated tenant routes
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

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
});
