<?php

namespace App\Providers;

use App\Models\User;
use App\Services\ConsultationCreditService;
use App\Services\ConsultationUsageLimiter;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as ViewInstance;

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
        URL::defaults(['locale' => 'ja']);

        $this->configureRateLimiting();

        ResetPassword::createUrlUsing(function (User $user, string $token): string {
            return URL::route('password.reset', [
                'locale' => $user->preferredLocale(),
                'token' => $token,
                'email' => $user->getEmailForPasswordReset(),
            ]);
        });

        VerifyEmail::createUrlUsing(function (User $user): string {
            return URL::temporarySignedRoute(
                'verification.verify',
                now()->addMinutes(config('auth.verification.expire', 60)),
                [
                    'locale' => $user->preferredLocale(),
                    'id' => $user->getKey(),
                    'hash' => sha1($user->getEmailForVerification()),
                ],
            );
        });

        View::composer('partials.site-header', function (ViewInstance $view): void {
            $user = request()->user();

            if ($user instanceof User) {
                $view->with('creditSummary', app(ConsultationCreditService::class)->balance($user));
            }
        });
    }

    /**
     * Short-window limits per IP address. They return 429 with Retry-After and sit in front of the
     * per-user credits and the limits in ConsultationUsageLimiter.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('consultations', fn (Request $request): Limit => Limit::perMinute(10)
            ->by(app(ConsultationUsageLimiter::class)->hashedIdentifier((string) $request->ip())));

        RateLimiter::for('registrations', fn (Request $request): Limit => Limit::perHour(10)
            ->by(app(ConsultationUsageLimiter::class)->hashedIdentifier((string) $request->ip())));
    }
}
