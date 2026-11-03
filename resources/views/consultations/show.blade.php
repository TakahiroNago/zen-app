@extends('layouts.app')

@section('title', __('相談の記録'))
@section('robots', 'noindex, nofollow, noarchive')
@section('main_class', 'history-content')

@section('content')
    <article class="reading consultation-detail">
        <header class="reading-header">
            <div class="enso-disc">
                <img src="{{ App\Models\ZenConsultation::imageUrl($consultation['image_number'] ?? null) }}" alt="" width="{{ App\Models\ZenConsultation::IMAGE_SIZE }}" height="{{ App\Models\ZenConsultation::IMAGE_SIZE }}" decoding="async" fetchpriority="high">
            </div>
            <div>
                <p class="eyebrow consultation-detail-eyebrow">YOUR REFLECTION</p>
                <h1 class="consultation-detail-title">{{ __('禅師からのことば') }}</h1>
                @if (($consultation['created_at'] ?? null) instanceof DateTimeInterface)
                    <p class="reading-date"><time datetime="{{ $consultation['created_at']->toIso8601String() }}" data-local-date>{{ $consultation['created_at']->format('Y/m/d') }}</time></p>
                @endif
            </div>
        </header>

        <section class="concern-block" aria-labelledby="concern-heading">
            <h2 class="concern-label" id="concern-heading">{{ __('あなたの思い') }}</h2>
            <p class="question-quote">{{ $consultation['concern'] }}</p>
        </section>

        <section aria-labelledby="answer-heading">
            <h2 class="sr-only" id="answer-heading">{{ __('お答え') }}</h2>
            <div class="consultation-copy" id="consultation-copy">{!! app(App\Services\ConsultationResponseFormatter::class)->toHtml($consultation['response']) !!}</div>
        </section>

        @include('partials.consultation-share', ['consultationId' => $consultationId])

        <div class="history-actions">
            <a class="text-link" href="{{ route('consultations.index') }}">{{ __('履歴一覧へ戻る') }}</a>
            <a class="text-link" href="{{ route('home') }}">{{ __('もう一度相談する') }}</a>
            <form method="POST" action="{{ route('consultations.destroy', $consultationId) }}" onsubmit="return confirm(this.dataset.confirm)" data-confirm="{{ __('この相談の記録を削除しますか？公開用URLも無効になります。') }}">
                @csrf
                @method('DELETE')
                <button class="text-link" type="submit">{{ __('この記録を削除する') }}</button>
            </form>
        </div>
    </article>
@endsection
