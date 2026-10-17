<?php

namespace App\Providers;

use App\Contracts\SpeechToTextProvider;
use App\Models\Attendee;
use App\Models\AuditLog;
use App\Models\Meeting;
use App\Models\User;
use App\Policies\AttendeePolicy;
use App\Policies\AuditLogPolicy;
use App\Policies\MeetingPolicy;
use App\Policies\UserPolicy;
use App\Services\STT\GroqWhisperProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SpeechToTextProvider::class, function ($app) {
            $driver = config('stt.driver', 'groq');

            return match ($driver) {
                'groq' => $app->make(GroqWhisperProvider::class),
                default => throw new InvalidArgumentException("Driver STT tidak didukung: {$driver}"),
            };
        });
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

        // STT queue rate limiter (RPM)
        RateLimiter::for('stt', function () {
            return Limit::perMinute((int) config('stt.rpm_limit', 18));
        });
    }
}
