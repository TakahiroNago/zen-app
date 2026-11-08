<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;

/**
 * Usage limits that protect the AI API budget on top of the per-user credits.
 *
 * Every counter here counts attempts that reach the AI API, not successful readings: a failed reading
 * still costs tokens, so failures must not reset these counters or they could be bypassed on purpose.
 */
class ConsultationUsageLimiter
{
    /**
     * Guest trials per IP address per 24 hours. Generous so shared networks (offices, mobile carriers) are not blocked,
     * while still stopping a single client from clearing its cookie to get unlimited trials.
     */
    public const GUEST_IP_DAILY_LIMIT = 10;

    /**
     * Failed readings per member per hour. Failed readings refund their credit, so without this limit
     * a member could retry a failing reading indefinitely at the expense of the AI API budget.
     */
    public const MEMBER_HOURLY_FAILURE_LIMIT = 5;

    private const DAY_SECONDS = 86400;

    private const HOUR_SECONDS = 3600;

    public function consultationsEnabled(): bool
    {
        return (bool) config('zen.consultations_enabled', true);
    }

    /**
     * Reserves one guest trial for the request's IP address. The counter is incremented before it is
     * checked so concurrent requests from the same IP address cannot all pass the limit at once.
     *
     * @return string|null The reservation key, or null when the IP address has reached its limit.
     */
    public function reserveGuestIpTrial(?string $ipAddress): ?string
    {
        $key = 'guest-ip-consultation:'.$this->hashedIdentifier((string) $ipAddress);

        if (RateLimiter::hit($key, self::DAY_SECONDS) > self::GUEST_IP_DAILY_LIMIT) {
            RateLimiter::decrement($key, self::DAY_SECONDS);

            return null;
        }

        return $key;
    }

    /**
     * Gives a reservation back when the request never reached the AI API (e.g. the device has no trial left).
     */
    public function releaseGuestIpTrial(string $reservationKey): void
    {
        RateLimiter::decrement($reservationKey, self::DAY_SECONDS);
    }

    public function memberHasTooManyFailures(User $user): bool
    {
        return RateLimiter::tooManyAttempts($this->memberFailureKey($user), self::MEMBER_HOURLY_FAILURE_LIMIT);
    }

    public function recordMemberFailure(User $user): void
    {
        RateLimiter::hit($this->memberFailureKey($user), self::HOUR_SECONDS);
    }

    /**
     * Whether the application-wide hourly or daily consultation limit has been reached. These limits cap the
     * total AI API spend, which per-user limits cannot do when many accounts are used together.
     */
    public function globalLimitReached(): bool
    {
        foreach ($this->globalWindows() as $window => ['key' => $key, 'limit' => $limit]) {
            if ($limit > 0 && RateLimiter::tooManyAttempts($key, $limit)) {
                if (Cache::add($key.':reported', true, self::DAY_SECONDS)) {
                    Log::warning('The application-wide consultation limit was reached.', [
                        'window' => $window,
                        'limit' => $limit,
                    ]);
                }

                return true;
            }
        }

        return false;
    }

    public function recordGlobalUsage(): void
    {
        foreach ($this->globalWindows() as ['key' => $key, 'decay' => $decaySeconds]) {
            RateLimiter::hit($key, $decaySeconds);
        }
    }

    /**
     * Rate-limit keys never contain raw IP addresses or user data; they are HMAC-hashed with the app key.
     */
    public function hashedIdentifier(string $identifier): string
    {
        $applicationKey = config('app.key');
        if (! is_string($applicationKey) || $applicationKey === '') {
            throw new RuntimeException('APP_KEY is required to protect rate-limit identifiers.');
        }

        return hash_hmac('sha256', $identifier, $applicationKey);
    }

    private function memberFailureKey(User $user): string
    {
        return 'member-consultation-failures:'.$user->getAuthIdentifier();
    }

    /**
     * @return array<string, array{key: string, limit: int, decay: int}>
     */
    private function globalWindows(): array
    {
        return [
            'hourly' => [
                'key' => 'global-consultations:hourly:'.now()->format('Y-m-d-H'),
                'limit' => (int) config('zen.global_consultation_limits.hourly', 0),
                'decay' => self::HOUR_SECONDS,
            ],
            'daily' => [
                'key' => 'global-consultations:daily:'.now()->toDateString(),
                'limit' => (int) config('zen.global_consultation_limits.daily', 0),
                'decay' => self::DAY_SECONDS,
            ],
        ];
    }
}
