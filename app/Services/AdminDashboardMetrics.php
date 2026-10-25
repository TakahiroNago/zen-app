<?php

namespace App\Services;

use App\Models\ContactInquiry;
use App\Models\User;
use App\Models\ZenConsultation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AdminDashboardMetrics
{
    private const LOG_TAIL_BYTES = 1048576;

    private const SUBSCRIPTION_STATUSES = ['active', 'trialing', 'past_due', 'unpaid', 'incomplete', 'canceled'];

    /**
     * @return array<string, mixed>
     */
    public function users(): array
    {
        return [
            'total' => User::count(),
            'active' => User::where('is_active', true)->count(),
            'suspended' => User::where('is_active', false)->count(),
            'admins' => User::where('is_admin', true)->count(),
            'unverified' => User::whereNull('email_verified_at')->count(),
            'registered_today' => User::where('created_at', '>=', now()->startOfDay())->count(),
            'registered_7_days' => User::where('created_at', '>=', now()->subDays(7))->count(),
            'recent' => User::query()->latest()->limit(8)->get(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function billing(): array
    {
        $plans = User::query()
            ->select('plan', DB::raw('count(*) as total'))
            ->groupBy('plan')
            ->pluck('total', 'plan');

        $statuses = User::query()
            ->whereNotNull('stripe_subscription_id')
            ->select('stripe_subscription_status', DB::raw('count(*) as total'))
            ->groupBy('stripe_subscription_status')
            ->pluck('total', 'stripe_subscription_status');

        return [
            'plans' => [
                User::PLAN_FREE => (int) ($plans[User::PLAN_FREE] ?? 0),
                User::PLAN_LITE => (int) ($plans[User::PLAN_LITE] ?? 0),
                User::PLAN_PREMIUM => (int) ($plans[User::PLAN_PREMIUM] ?? 0),
            ],
            'statuses' => collect(self::SUBSCRIPTION_STATUSES)
                ->mapWithKeys(fn (string $status): array => [$status => (int) ($statuses[$status] ?? 0)])
                ->all(),
            'customers' => User::whereNotNull('stripe_customer_id')->count(),
            'cancel_at_period_end' => User::where('stripe_cancel_at_period_end', true)->count(),
            'problem_subscriptions' => User::query()
                ->whereIn('stripe_subscription_status', ['past_due', 'unpaid', 'incomplete'])
                ->orderBy('stripe_current_period_end')
                ->limit(10)
                ->get(),
            'credit_purchases_30_days' => DB::table('stripe_credit_purchases')
                ->where('created_at', '>=', now()->subDays(30))
                ->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function usage(): array
    {
        $since = now()->subDays(6)->startOfDay();
        $daily = ZenConsultation::query()
            ->where('created_at', '>=', $since)
            ->get(['created_at'])
            ->countBy(fn (ZenConsultation $consultation): string => $consultation->created_at->format('Y-m-d'));

        $series = collect(range(0, 6))->mapWithKeys(function (int $offset) use ($since, $daily): array {
            $date = $since->copy()->addDays($offset)->format('Y-m-d');

            return [$date => (int) ($daily[$date] ?? 0)];
        });

        return [
            'total' => ZenConsultation::count(),
            'today' => ZenConsultation::where('created_at', '>=', now()->startOfDay())->count(),
            'last_7_days' => ZenConsultation::where('created_at', '>=', now()->subDays(7))->count(),
            'last_30_days' => ZenConsultation::where('created_at', '>=', now()->subDays(30))->count(),
            'active_users_7_days' => ZenConsultation::where('created_at', '>=', now()->subDays(7))
                ->distinct()
                ->count('user_id'),
            'daily' => $series,
            'topics' => ZenConsultation::query()
                ->whereNotNull('topic')
                ->select('topic', DB::raw('count(*) as total'))
                ->groupBy('topic')
                ->orderByDesc('total')
                ->limit(5)
                ->pluck('total', 'topic'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function errors(): array
    {
        $log = $this->recentLogErrors();

        return [
            'log_errors_24h' => $log['count'],
            'log_recent' => $log['recent'],
            'log_readable' => $log['readable'],
            'failed_jobs_24h' => $this->failedJobs(now()->subDay()),
            'failed_jobs_total' => $this->failedJobs(),
            'mail_failures' => ContactInquiry::where('mail_sent', false)->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function operations(): array
    {
        return [
            'unresolved_inquiries' => ContactInquiry::whereNull('resolved_at')->count(),
            'inquiries_7_days' => ContactInquiry::where('created_at', '>=', now()->subDays(7))->count(),
            'recent_inquiries' => ContactInquiry::query()->latest()->limit(10)->get(),
            'checks' => $this->systemChecks(),
            'last_webhook' => DB::table('stripe_webhook_events')->latest('processed_at')->first(['type', 'processed_at']),
            'pending_jobs' => Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0,
        ];
    }

    /**
     * @return list<array{label: string, ok: bool, detail: string}>
     */
    private function systemChecks(): array
    {
        $databaseOk = true;
        try {
            DB::select('select 1');
        } catch (Throwable) {
            $databaseOk = false;
        }

        $logWritable = is_writable(storage_path('logs'));

        return [
            ['label' => 'database', 'ok' => $databaseOk, 'detail' => (string) config('database.default')],
            ['label' => 'openai', 'ok' => filled(config('openai.api_key')), 'detail' => 'API key'],
            ['label' => 'stripe', 'ok' => filled(config('services.stripe.secret')), 'detail' => 'API key'],
            ['label' => 'stripe_webhook', 'ok' => filled(config('services.stripe.webhook_secret')), 'detail' => 'Webhook secret'],
            ['label' => 'mail', 'ok' => filled(config('services.contact.email')), 'detail' => (string) config('mail.default')],
            ['label' => 'queue', 'ok' => true, 'detail' => (string) config('queue.default')],
            ['label' => 'storage', 'ok' => $logWritable, 'detail' => 'storage/logs'],
        ];
    }

    private function failedJobs(?Carbon $since = null): int
    {
        if (! Schema::hasTable('failed_jobs')) {
            return 0;
        }

        return DB::table('failed_jobs')
            ->when($since, fn ($query) => $query->where('failed_at', '>=', $since))
            ->count();
    }

    /**
     * @return array{count: int, recent: list<array{at: string, level: string, message: string}>, readable: bool}
     */
    private function recentLogErrors(): array
    {
        $files = glob(storage_path('logs/*.log')) ?: [];
        usort($files, fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

        if ($files === [] || ! is_readable($files[0])) {
            return ['count' => 0, 'recent' => [], 'readable' => $files === []];
        }

        $size = filesize($files[0]);
        $handle = fopen($files[0], 'rb');
        if ($handle === false) {
            return ['count' => 0, 'recent' => [], 'readable' => false];
        }

        fseek($handle, max(0, $size - self::LOG_TAIL_BYTES));
        $contents = (string) stream_get_contents($handle);
        fclose($handle);

        $threshold = now()->subDay();
        $entries = [];
        preg_match_all(
            '/^\[(\d{4}-\d{2}-\d{2}[ T][\d:]+)[^\]]*\] \S+?\.(ERROR|CRITICAL|ALERT|EMERGENCY): ([^\r\n]*)/m',
            $contents,
            $matches,
            PREG_SET_ORDER,
        );

        foreach ($matches as $match) {
            $at = Carbon::parse($match[1]);
            if ($at->greaterThanOrEqualTo($threshold)) {
                $entries[] = [
                    'at' => $at->format('Y/m/d H:i'),
                    'level' => $match[2],
                    'message' => mb_strimwidth($match[3], 0, 160, '…'),
                ];
            }
        }

        return [
            'count' => count($entries),
            'recent' => array_slice(array_reverse($entries), 0, 5),
            'readable' => true,
        ];
    }
}
