{{-- Today's Zen saying. Expects $zengo, $zengoReading and $zengoMeaning (all nullable). --}}
@if (filled($zengo ?? null) && filled($zengoReading ?? null) && filled($zengoMeaning ?? null))
    <figure class="zengo-block">
        <span class="zengo-label">{{ __('今日の禅語') }}</span>
        <blockquote class="zengo-text" lang="ja">{{ $zengo }}</blockquote>
        <figcaption class="zengo-caption">
            <span class="zengo-reading" lang="ja">{{ $zengoReading }}</span>
            <span class="zengo-meaning">{{ $zengoMeaning }}</span>
        </figcaption>
    </figure>
@endif
