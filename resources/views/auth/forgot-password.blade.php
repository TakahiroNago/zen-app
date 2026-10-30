@extends('layouts.app')

@section('title', __('パスワード再設定'))

@section('content')
    <section class="form-panel">
        <p class="eyebrow">{{ __('PASSWORD RESET') }}</p>
        <h1>{{ __('パスワード再設定') }}</h1>
        @if (session('status'))@include('partials.toast', ['message' => __(session('status'))])@endif
        <form class="question-form" method="POST" action="{{ route('password.email') }}">
            @csrf
            <label class="question-label" for="email">{{ __('登録メールアドレス') }}</label>
            <input class="question-input" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
            @error('email')<p class="form-error" role="alert">{{ __($message) }}</p>@enderror
            <button class="primary-action" type="submit">{{ __('再設定メールを送る') }} <span class="action-arrow" aria-hidden="true">→</span></button>
        </form>
        <a class="text-link" href="{{ route('login') }}">{{ __('ログインへ戻る') }}</a>
    </section>
@endsection
