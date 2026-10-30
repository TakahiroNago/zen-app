{{-- resources/views/welcome.blade.php --}}

@extends('layouts.app')

@section('body_class', 'welcome-page')

@section('content')
    <section class="welcome-panel">
        <div class="welcome-copy">
            @if (session('status') === 'email-verified')
                @include('partials.toast', ['message' => __('メールアドレスを確認しました。')])
            @endif
            <p class="eyebrow" data-reveal="eyebrow">{{ __('今日のあなたへ') }}</p>
            <h1 class="welcome-title" data-reveal="title">{{ __('ゆっくりと') }}<br>{{ __('心を見つめて') }}</h1>
            <p class="welcome-description" data-reveal="description">{{ __('思っていることを聞かせてください。AIが禅の視点で、心を見つめる言葉をお届けします。') }}</p>
            <a class="primary-action" data-reveal="action" href="{{ route('zen') }}">
                <span>{{ __('AI禅師に相談する') }}</span>
                <span class="action-arrow" aria-hidden="true">→</span>
            </a>
        </div>
        <div class="art-stage" data-reveal="art">
            <img class="welcome-logo" src="{{ asset('images/logo.png') }}" alt="{{ __('心の庵') }} ZEN REFLECTIONS">
        </div>
    </section>
@endsection
