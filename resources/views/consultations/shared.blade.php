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
            <span class="brand-caption">ZEN REFLECTIONS</span>
        </a>
    </header>
@endsection

@section('content')
    <article class="consultation-detail consultation-detail">
        @if ($imageUrl = App\Models\ZenConsultation::imageUrl($consultation['image_number'] ?? null))
            <div class="art-stage consultation-image-stage">
                <img class="consultation-image" src="{{ $imageUrl }}" alt="" width="{{ App\Models\ZenConsultation::IMAGE_SIZE }}" height="{{ App\Models\ZenConsultation::IMAGE_SIZE }}" decoding="async" fetchpriority="high">
            </div>
        @endif
        <div class="consultation-detail-copy">
            <p class="eyebrow consultation-detail-eyebrow">WORDS FOR YOU</p>
            <h1 class="consultation-detail-title">{{ __('禅師からのことば') }}</h1>
            <section class="result-section">
                <h2 class="section-label">{{ __('あなたの思い') }}</h2>
                @if ($consultation['concern_hidden'])
                    <p class="question-quote">{{ __('プライバシー保護のため、相談文は表示されません。') }}</p>
                @else
                    <p class="question-quote">{{ $consultation['concern'] }}</p>
                @endif
            </section>
            <section class="result-section">
                <h2 class="section-label">{{ __('お答え') }}</h2>
                <div class="consultation-copy">{!! app(App\Services\ConsultationResponseFormatter::class)->toHtml($consultation['response']) !!}</div>
            </section>
            <a class="text-link" href="{{ route('zen') }}">{{ __('もう一度相談する') }}</a>
        </div>
    </article>
@endsection
