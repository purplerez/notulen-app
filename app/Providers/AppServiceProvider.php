<?php

namespace App\Providers;

use App\Contracts\PaymentGatewayInterface;
use App\Contracts\SpeechToTextProvider;
use App\Contracts\Summarizer;
use App\Models\Attendee;
use App\Models\AuditLog;
use App\Models\Meeting;
use App\Models\Minute;
use App\Models\QuotaOrder;
use App\Models\User;
use App\Policies\AttendeePolicy;
use App\Policies\AuditLogPolicy;
use App\Policies\MeetingPolicy;
use App\Policies\MinutePolicy;
use App\Policies\QuotaOrderPolicy;
use App\Policies\UserPolicy;
use App\Services\Billing\MockPaymentGateway;
use App\Services\Minutes\FakeSummarizer;
use App\Services\Minutes\LlmSummarizer;
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

        $this->app->bind(Summarizer::class, function ($app) {
            $driver = config('summarizer.driver', 'fake');

            return match ($driver) {
                'fake' => $app->make(FakeSummarizer::class),
                'llm' => $app->make(LlmSummarizer::class),
                default => throw new InvalidArgumentException("Driver Summarizer tidak didukung: {$driver}"),
            };
        });

        $this->app->bind(PaymentGatewayInterface::class, function ($app) {
            return $app->make(MockPaymentGateway::class);
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
        Gate::policy(Minute::class, MinutePolicy::class);
        Gate::policy(QuotaOrder::class, QuotaOrderPolicy::class);

        // STT queue rate limiter (RPM)
        RateLimiter::for('stt', function () {
            return Limit::perMinute((int) config('stt.rpm_limit', 18));
        });

        // Automatically clean up orphaned Vite 'hot' file if dev server is offline
        if ($this->app->environment('local')) {
            $hotPath = public_path('hot');
            if (is_file($hotPath)) {
                $hotUrl = trim((string) @file_get_contents($hotPath));
                $parts = parse_url($hotUrl);
                $host = $parts['host'] ?? '127.0.0.1';
                $port = (int) ($parts['port'] ?? 5173);
                $checkHost = in_array($host, ['localhost', '127.0.0.1', 'notulen-app.test'], true) ? '127.0.0.1' : $host;
                $socket = @fsockopen($checkHost, $port, $errno, $errstr, 0.05);
                if ($socket) {
                    fclose($socket);
                } else {
                    @unlink($hotPath);
                }
            }
        }
    }
}
