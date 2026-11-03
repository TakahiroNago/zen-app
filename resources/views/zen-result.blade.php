@extends('layouts.app')

@section('title', __('禅師からのことば'))

@section('content')
    <article class="reading">
        <header class="reading-header">
            <div class="enso-disc">
                <img src="{{ App\Models\ZenConsultation::imageUrl($image_number ?? null) }}" alt="" width="{{ App\Models\ZenConsultation::IMAGE_SIZE }}" height="{{ App\Models\ZenConsultation::IMAGE_SIZE }}" decoding="async" fetchpriority="high">
            </div>
            <div>
                <p class="eyebrow">WORDS FOR YOU</p>
                <h1 class="result-heading">{{ __('禅師からのことば') }}</h1>
                <p class="reading-date"><time datetime="{{ now()->toIso8601String() }}" data-local-date>{{ now()->format('Y/m/d') }}</time></p>
            </div>
        </header>

        <section class="concern-block" aria-labelledby="concern-heading">
            <h2 class="concern-label" id="concern-heading">{{ __('あなたの思い') }}</h2>
            <p class="question-quote">{{ $concern }}</p>
        </section>

        @include('partials.consultation-zengo', ['zengo' => $zengo ?? null, 'zengoReading' => $zengo_reading ?? null, 'zengoMeaning' => $zengo_meaning ?? null])

        <section aria-labelledby="answer-heading">
            <h2 class="sr-only" id="answer-heading">{{ __('お答え') }}</h2>
            <div class="consultation-copy" id="consultation-copy">{!! app(App\Services\ConsultationResponseFormatter::class)->toHtml($response) !!}</div>
        </section>

        @include('partials.consultation-daily-action', ['dailyAction' => $daily_action ?? null])

        @include('partials.consultation-share', ['consultationId' => $consultationId, 'showSavedLink' => ! $isGuest])

        @if ($creditsRemaining === 0)
            @include('partials.credit-exhausted-notice', ['isGuest' => $isGuest])
        @else
            <div class="result-actions">
                <p class="credit-summary">
                    @if ($creditsRemaining === null)
                        {{ __('相談回数は無制限です') }}
                    @else
                        {{ app()->isLocale('ja') ? __('相談残り').' '.$creditsRemaining.__('回') : __('相談残り: :count', ['count' => $creditsRemaining]) }}
                    @endif
                </p>
                <a class="primary-action" href="{{ route('home') }}">
                    <span>{{ __('もう一度相談する') }}</span>
                    <svg class="action-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </a>
                <a class="text-link" href="{{ route('consultations.index') }}">{{ __('相談の記録を見る') }}</a>
            </div>
        @endif
    </article>
@endsection
