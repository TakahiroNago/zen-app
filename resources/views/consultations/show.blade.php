@extends('layouts.app')

@section('title', __('相談の記録'))
@section('robots', 'noindex, nofollow, noarchive')
@section('main_class', 'history-content')

@section('content')
    <article class="consultation-detail consultation-detail">
        @if ($imageUrl = App\Models\ZenConsultation::imageUrl($consultation['image_number'] ?? null))
            <div class="art-stage consultation-image-stage">
                <img class="consultation-image" src="{{ $imageUrl }}" alt="" width="{{ App\Models\ZenConsultation::IMAGE_SIZE }}" height="{{ App\Models\ZenConsultation::IMAGE_SIZE }}" decoding="async" fetchpriority="high">
            </div>
        @endif
        <div class="consultation-detail-copy">
            <p class="eyebrow consultation-detail-eyebrow">YOUR REFLECTION</p>
            <h1 class="consultation-detail-title">{{ __('禅師からのことば') }}</h1>
            <section class="result-section">
                <h2 class="section-label">{{ __('あなたの思い') }}</h2>
                <p class="question-quote">{{ $consultation['concern'] }}</p>
            </section>
            <section class="result-section">
                <h2 class="section-label">{{ __('お答え') }}</h2>
                <div class="consultation-copy">{!! app(App\Services\ConsultationResponseFormatter::class)->toHtml($consultation['response']) !!}</div>
            </section>
            @include('partials.consultation-share', ['shareUrl' => $shareUrl])
            <div class="history-actions">
                <form method="POST" action="{{ route('consultations.destroy', $consultationId) }}" onsubmit="return confirm(this.dataset.confirm)" data-confirm="{{ __('この相談の記録を削除しますか？公開用URLも無効になります。') }}">
                    @csrf
                    @method('DELETE')
                    <button class="text-link" type="submit">{{ __('この記録を削除する') }}</button>
                </form>
                <a class="text-link" href="{{ route('consultations.index') }}">{{ __('履歴一覧へ戻る') }}</a>
                <a class="text-link" href="{{ route('zen') }}">{{ __('もう一度相談する') }}</a>
            </div>
        </div>
    </article>
@endsection
