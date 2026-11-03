{{-- One thing to try today, taken from the answer. Expects $dailyAction (nullable). --}}
@if (filled($dailyAction ?? null))
    <section class="daily-action" aria-labelledby="daily-action-heading">
        <span class="daily-action-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3c3 3 5 6 5 9a5 5 0 0 1-10 0c0-3 2-6 5-9z"/><path d="M12 21v-6"/></svg>
        </span>
        <div class="daily-action-copy">
            <h2 class="daily-action-label" id="daily-action-heading">{{ __('今日のひとつ') }}</h2>
            <p class="daily-action-text">{{ $dailyAction }}</p>
        </div>
    </section>
@endif
