@extends('layouts.app')

@section('title', __('メールアドレスの確認'))

@section('content')
    <section class="form-panel">
        <p class="eyebrow">{{ __('VERIFY YOUR EMAIL') }}</p>
        <h1>{{ __('メールアドレスの確認') }}</h1>
        <p class="page-description">{{ __('登録を完了するため、メールに記載された確認リンクを開いてください。') }}</p>
        @if (session('status') === 'verification-link-sent')
            @include('partials.toast', ['message' => __('確認メールを再送しました。受信トレイをご確認ください。')])
        @endif
        <form class="question-form" method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button class="primary-action" type="submit">{{ __('確認メールを再送する') }} <span class="action-arrow" aria-hidden="true">→</span></button>
        </form>
    </section>
@endsection
