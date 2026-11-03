{{-- Home page with the consultation form. --}}

@extends('layouts.app')

@section('body_class', 'welcome-page')

@php
    $canConsult = auth()->check()
        ? ($creditSummary['unlimited'] || $creditSummary['total'] > 0)
        : $guestCredits > 0;
@endphp

@section('content')
    <section class="home-panel">
        @if (session('status') === 'email-verified')
            @include('partials.toast', ['message' => __('メールアドレスを確認しました。')])
        @endif
        @if (session('consultation_rate_limited'))
            @include('partials.toast', ['message' => session('consultation_rate_limited'), 'type' => 'error'])
        @endif

        <div class="home-art" data-reveal="art">
            <div class="enso-disc enso-disc-large">
                <img src="{{ asset('images/logo.webp') }}" alt="" width="720" height="684" decoding="async" fetchpriority="high">
            </div>
            <p class="home-art-caption">{{ __('一息ついて、心の声に耳を澄ます') }}</p>
        </div>

        <div class="home-copy">
            <div class="home-intro" data-reveal="eyebrow">
                <div class="enso-disc enso-disc-icon" aria-hidden="true">
                    <img src="{{ asset('images/logo.webp') }}" alt="" width="720" height="684" decoding="async" loading="lazy">
                </div>
                <p class="eyebrow">{{ __('今日のあなたへ') }}</p>
            </div>
            <h1 class="welcome-title" data-reveal="title">{{ __('いま、心にあることを') }}<br>{{ __('聞かせてください') }}</h1>
            <p class="welcome-description" data-reveal="description">{{ __('禅の考え方をもとに、AI禅師があなたの思いに静かに向き合います。') }}</p>

            <div data-reveal="action">
                @auth
                    @if ($canConsult && session('credit_exhausted'))
                        <a class="form-notice credit-notice credit-notice-link" href="{{ route('plans') }}" role="alert">
                            {{ session('credit_exhausted') }} {{ __('クレジット・プランを見る') }}
                        </a>
                    @endif
                @else
                    @if ($canConsult && session('credit_exhausted'))
                        <a class="form-notice credit-notice credit-notice-link" href="{{ route('register') }}" role="alert">
                            {{ session('credit_exhausted') }} {{ __('会員登録はこちら') }}
                        </a>
                    @endif
                @endauth

                <form class="question-form consultation-card" method="POST" action="{{ route('zen.respond') }}">
                    @csrf
                    <label class="question-label" for="concern">{{ __('あなたの思い') }}</label>
                    <textarea
                        class="question-input question-auto-grow"
                        id="concern"
                        name="concern"
                        placeholder="{{ __('例：新しい一歩を踏み出すのが怖いです。') }}"
                        rows="4"
                        maxlength="{{ App\Models\ZenConsultation::CONCERN_MAX_LENGTH }}"
                        aria-describedby="concern-privacy-note concern-character-count"
                        data-auto-grow
                        data-submit-on-enter
                        data-concern-input
                        data-character-count-target="concern-character-count"
                        required
                    >{{ old('concern', $concernInput ?? '') }}</textarea>
                    <div class="question-meta">
                        <p class="question-privacy-note" id="concern-privacy-note">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                            <span>{{ __('名前や連絡先など、個人が特定できる情報は書かないでください') }}</span>
                        </p>
                        <p class="question-character-count" id="concern-character-count">
                            <span data-character-count>{{ mb_strlen((string) old('concern', $concernInput ?? '')) }}</span> / {{ App\Models\ZenConsultation::CONCERN_MAX_LENGTH }}
                        </p>
                    </div>
                    @error('concern')
                        <p class="form-error" role="alert">{{ $message }}</p>
                    @enderror
                    @if (($personalInfoDetected ?? false) || old('confirm_personal_info'))
                        <label class="form-notice">
                            <input type="checkbox" name="confirm_personal_info" value="1" @checked(old('confirm_personal_info'))>
                            {{ __('個人情報が含まれていることを理解したうえで、このまま送信する（共有URLでは相談文が非表示になります）') }}
                        </label>
                    @endif
                    @if (config('zen.concern_hints'))
                        <div class="concern-hints" role="group" aria-labelledby="concern-hints-label">
                            <span class="concern-hints-label" id="concern-hints-label">{{ __('ヒント') }}</span>
                            @foreach (config('zen.concern_hints') as $hint)
                                <button class="concern-hint" type="button" data-concern-hint="{{ __($hint) }}">{{ __($hint) }}</button>
                            @endforeach
                        </div>
                    @endif
                    @if ($canConsult)
                        <div class="question-submit-bar">
                            <button class="primary-action" type="submit" data-requires-input data-submitting-label="{{ __('読み解いています...') }}">
                                <span class="loading-spinner" aria-hidden="true"></span>
                                <span class="submit-label">{{ __('禅師に相談する') }}</span>
                                <svg class="action-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                            </button>
                        </div>
                        @guest
                            <p class="credit-summary">{{ app()->isLocale('ja') ? __('無料体験の残り').' '.$guestCredits.'回' : __('無料体験の残り: :count回', ['count' => $guestCredits]) }}</p>
                        @endguest
                    @else
                        @include('partials.credit-exhausted-notice', ['isGuest' => auth()->guest()])
                    @endif
                </form>

                @if ($anonymizedConcern ?? null)
                    <form class="question-form consultation-card" method="POST" action="{{ route('zen.respond') }}">
                        @csrf
                        <p class="form-notice">{{ __('個人情報と思われる部分を伏せた内容で相談できます。内容をご確認ください。') }}</p>
                        <p class="question-quote">{{ $anonymizedConcern }}</p>
                        <input type="hidden" name="concern" value="{{ $anonymizedConcern }}">
                        <button class="primary-action" type="submit" data-submitting-label="{{ __('読み解いています...') }}">
                            <span class="loading-spinner" aria-hidden="true"></span>
                            <span class="submit-label">{{ __('この内容で相談する') }}</span>
                            <svg class="action-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </section>
@endsection
