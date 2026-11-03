@extends('layouts.app')

@section('title', __('共有されたことば'))
@section('robots', 'noindex, nofollow, noarchive')
@section('main_class', 'history-content')

@push('meta')
    <meta name="referrer" content="no-referrer">
@endpush

@section('header')
    <header class="site-header">
        <a class="brand" href="{{ route('home') }}">
            <span class="brand-mark" aria-hidden="true">✦</span>
            <span class="brand-name">{{ __('心の庵') }}</span>
        </a>
    </header>
@endsection

@section('content')
    <article class="reading consultation-detail">
        <header class="reading-header">
            <div class="enso-disc">
                <img src="{{ App\Models\ZenConsultation::imageUrl($consultation['image_number'] ?? null) }}" alt="" width="{{ App\Models\ZenConsultation::IMAGE_SIZE }}" height="{{ App\Models\ZenConsultation::IMAGE_SIZE }}" decoding="async" fetchpriority="high">
            </div>
            <div>
                <p class="eyebrow consultation-detail-eyebrow">WORDS FOR YOU</p>
                <h1 class="consultation-detail-title">{{ __('禅師からのことば') }}</h1>
            </div>
        </header>

        <section class="concern-block" aria-labelledby="concern-heading">
            <h2 class="concern-label" id="concern-heading">{{ __('あなたの思い') }}</h2>
            @if ($consultation['concern_hidden'])
                <p class="question-quote">{{ __('プライバシー保護のため、相談文は表示されません。') }}</p>
            @else
                <p class="question-quote">{{ $consultation['concern'] }}</p>
            @endif
        </section>

        <section aria-labelledby="answer-heading">
            <h2 class="sr-only" id="answer-heading">{{ __('お答え') }}</h2>
            <div class="consultation-copy">{!! app(App\Services\ConsultationResponseFormatter::class)->toHtml($consultation['response']) !!}</div>
        </section>

        <div class="result-actions">
            <a class="primary-action" href="{{ route('home') }}">
                <span>{{ __('心の庵で相談する') }}</span>
                <svg class="action-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
            </a>
        </div>
    </article>
@endsection
