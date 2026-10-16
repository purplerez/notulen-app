<?php

namespace App\Providers;

use App\Models\Attendee;
use App\Models\AuditLog;
use App\Models\Meeting;
use App\Models\User;
use App\Policies\AttendeePolicy;
use App\Policies\AuditLogPolicy;
use App\Policies\MeetingPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(AuditLog::class, AuditLogPolicy::class);
        Gate::policy(Meeting::class, MeetingPolicy::class);
        Gate::policy(Attendee::class, AttendeePolicy::class);
    }
}
