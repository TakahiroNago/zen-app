{{-- Zen consultation form. --}}

@extends('layouts.app')

@section('content')
    <section class="form-panel">
        @if (session('status') === 'email-verified')
            @include('partials.toast', ['message' => __('メールアドレスを確認しました。')])
        @endif
        @if (session('consultation_rate_limited'))
            @include('partials.toast', ['message' => session('consultation_rate_limited'), 'type' => 'error'])
        @endif
        <p class="eyebrow">{{ __('A MOMENT FOR YOU') }}</p>
        <h1>{{ __('心にある思いを') }}<br>{{ __('聞かせてください') }}</h1>
        <p class="page-description">{{ __('禅の考え方を基にして、AIがあなたの心に向き合います。') }}</p>

        @auth
            @if (! $creditSummary['unlimited'] && $creditSummary['total'] === 0)
                @include('partials.credit-exhausted-notice', ['isGuest' => false, 'role' => 'alert'])
            @elseif (session('credit_exhausted'))
                <a class="form-notice credit-notice credit-notice-link" href="{{ route('plans') }}" role="alert">
                    {{ session('credit_exhausted') }} {{ __('クレジット・プランを見る') }}
                </a>
            @endif
        @else
            @if ($guestCredits === 0)
                @include('partials.credit-exhausted-notice', ['isGuest' => true, 'role' => 'alert'])
            @else
                <p class="credit-summary">{{ app()->isLocale('ja') ? __('無料体験の残り').' '.$guestCredits.'回' : __('無料体験の残り: :count回', ['count' => $guestCredits]) }}</p>
                @if (session('credit_exhausted'))
                    <a class="form-notice credit-notice credit-notice-link" href="{{ route('register') }}" role="alert">
                        {{ session('credit_exhausted') }} {{ __('会員登録はこちら') }}
                    </a>
                @endif
            @endif
        @endauth

        <form class="question-form" method="POST" action="{{ route('zen.respond') }}">
            @csrf
            <label class="question-label" for="concern">{{ __('あなたの思い') }}</label>
            <p class="page-description question-privacy-note" id="concern-privacy-note">{{ __('氏名・住所・勤務先・電話番号・メールアドレスなどの個人情報は、ご自身や他の方のものも含めて書かないでください。') }}</p>
            <textarea
                class="question-input question-auto-grow"
                id="concern"
                name="concern"
                placeholder="{{ __('例：新しい一歩を踏み出すには？') }}"
                rows="1"
                maxlength="{{ App\Models\ZenConsultation::CONCERN_MAX_LENGTH }}"
                aria-describedby="concern-privacy-note concern-character-count"
                data-auto-grow
                data-submit-on-enter
                data-character-count-target="concern-character-count"
                required
            >{{ old('concern') }}</textarea>
            <p class="question-character-count" id="concern-character-count">
                <span data-character-count>{{ mb_strlen((string) old('concern')) }}</span> / {{ App\Models\ZenConsultation::CONCERN_MAX_LENGTH }}
            </p>
            @error('concern')
                <p class="form-error" role="alert">{{ $message }}</p>
            @enderror
            @if (session('personal_info_detected') || old('confirm_personal_info'))
                <label class="form-notice">
                    <input type="checkbox" name="confirm_personal_info" value="1" @checked(old('confirm_personal_info'))>
                    {{ __('個人情報が含まれていることを理解したうえで、このまま送信する（共有URLでは相談文が非表示になります）') }}
                </label>
            @endif
            <button class="primary-action" type="submit" @disabled(auth()->check() ? (! $creditSummary['unlimited'] && $creditSummary['total'] === 0) : $guestCredits === 0) data-submitting-label="{{ __('読み解いています...') }}">
                <span class="loading-spinner" aria-hidden="true"></span>
                <span class="submit-label">{{ __('相談する') }}</span>
                <span class="action-arrow" aria-hidden="true">→</span>
            </button>
        </form>
        @if (session('anonymized_concern'))
            <form class="question-form" method="POST" action="{{ route('zen.respond') }}">
                @csrf
                <p class="form-notice">{{ __('個人情報と思われる部分を伏せた内容で相談できます。内容をご確認ください。') }}</p>
                <p class="question-quote">{{ session('anonymized_concern') }}</p>
                <input type="hidden" name="concern" value="{{ session('anonymized_concern') }}">
                <button class="primary-action" type="submit" data-submitting-label="{{ __('読み解いています...') }}">
                    <span class="loading-spinner" aria-hidden="true"></span>
                    <span class="submit-label">{{ __('この内容で相談する') }}</span>
                    <span class="action-arrow" aria-hidden="true">→</span>
                </button>
            </form>
        @endif
    </section>
@endsection
