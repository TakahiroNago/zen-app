{{-- Shown instead of the consult button once no credits remain. Expects $isGuest. --}}
@php
    $creditService = app(App\Services\ConsultationCreditService::class);
    $memberPlan = auth()->user()?->plan ?? App\Models\User::PLAN_FREE;
    $refreshAt = $creditService->nextDailyRefreshAt()->setTimezone('Asia/Tokyo');
    $refreshesToday = $refreshAt->isSameDay(now('Asia/Tokyo'));
    $refreshDay = app()->isLocale('ja') ? ($refreshesToday ? '本日' : '明日') : ($refreshesToday ? 'today' : 'tomorrow');
    $refreshTime = app()->isLocale('ja')
        ? ($refreshAt->hour < 12 ? '午前'.$refreshAt->hour : '午後'.($refreshAt->hour - 12)).'時'
        : $refreshAt->format('g:i A').' JST';
@endphp
<section class="credit-panel" aria-labelledby="credit-panel-title">
    <div class="credit-panel-copy">
        @if ($isGuest)
            <h2 class="credit-panel-title" id="credit-panel-title">{{ __('無料体験はここまでです') }}</h2>
            <p class="credit-panel-note">{{ __('無料会員登録すると、毎日1回ご相談いただけます。') }}</p>
        @else
            <h2 class="credit-panel-title" id="credit-panel-title">{{ $memberPlan === App\Models\User::PLAN_FREE ? __('本日の無料相談はここまでです') : __('本日の相談回数を使い切りました') }}</h2>
            <p class="credit-panel-note">{{ __(':dayの:timeに、また:count回ご相談いただけます。今日のことばをゆっくり味わってください。', ['day' => $refreshDay, 'time' => $refreshTime, 'count' => $creditService->dailyCreditLimit($memberPlan)]) }}</p>
        @endif
    </div>
    <div class="credit-panel-actions">
        @if ($isGuest)
            <a class="credit-panel-primary" href="{{ route('register') }}">{{ __('無料で会員登録する') }}</a>
            <a class="credit-panel-secondary" href="{{ route('login') }}">{{ __('ログイン') }}</a>
        @else
            <a class="credit-panel-primary" href="{{ route('plans') }}">{{ __('有料プランを見る') }}</a>
            <a class="credit-panel-secondary" href="{{ route('consultations.index') }}">{{ __('相談の記録を見る') }}</a>
        @endif
    </div>
</section>
