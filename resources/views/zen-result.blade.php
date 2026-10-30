@extends('layouts.app')

@section('title', __('禅師からのことば'))

@section('content')
    <section class="result-panel result-panel-consultation">
        @if ($imageUrl = App\Models\ZenConsultation::imageUrl($image_number ?? null))
            <div class="art-stage consultation-image-stage">
                <img class="consultation-image" src="{{ $imageUrl }}" alt="" width="{{ App\Models\ZenConsultation::IMAGE_SIZE }}" height="{{ App\Models\ZenConsultation::IMAGE_SIZE }}" decoding="async" fetchpriority="high">
            </div>
        @endif
        <div class="result-copy">
            <p class="eyebrow result-eyebrow">WORDS FOR YOU</p>
            <h1 class="result-heading result-title">{{ __('禅師からのことば') }}</h1>

            <section class="result-section">
                <h2 class="section-label">{{ __('あなたの思い') }}</h2>
                <p class="question-quote">{{ $concern }}</p>
            </section>

            <section class="result-section">
                <h2 class="section-label">{{ __('お答え') }}</h2>
                <div class="consultation-copy">{!! app(App\Services\ConsultationResponseFormatter::class)->toHtml($response) !!}</div>
            </section>

            @include('partials.consultation-share', ['shareUrl' => $shareUrl])

            @if ($creditsRemaining === null)
                <p class="credit-summary">{{ __('相談回数は無制限です') }}</p>
            @elseif ($creditsRemaining === 0)
                @include('partials.credit-exhausted-notice', ['isGuest' => $isGuest])
            @else
                <p class="credit-summary">{{ app()->isLocale('ja') ? __('相談残り').' '.$creditsRemaining.__('回') : __('相談残り: :count', ['count' => $creditsRemaining]) }}</p>
            @endif

            <div class="result-actions">
                @if ($creditsRemaining === 0)
                    <a class="text-link" href="{{ route('zen') }}">{{ __('もう一度相談する') }}</a>
                @else
                    <a class="primary-action" href="{{ route('zen') }}">
                        <span>{{ __('もう一度相談する') }}</span>
                        <span class="action-arrow" aria-hidden="true">→</span>
                    </a>
                @endif
                <a class="text-link" href="{{ route('consultations.index') }}">{{ __('相談の記録を見る') }}</a>
            </div>
        </div>
    </section>
@endsection
