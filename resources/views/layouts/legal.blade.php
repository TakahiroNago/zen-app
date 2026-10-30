@extends('layouts.app')

@section('main_class', 'history-content')

@section('content')
    <article class="history-panel legal-panel">
        <p class="eyebrow">@yield('eyebrow')</p>
        <h1>@yield('title')</h1>
        <p class="legal-updated">{{ __('更新日') }}: {{ app()->isLocale('ja') ? '2026年10月13日' : 'October 13, 2026' }}</p>
        @if (app()->isLocale('en'))
            <p class="legal-translation-notice">{{ __('これは日本語版を正本とする参考訳です。内容に相違がある場合は日本語版が優先します。') }}</p>
        @endif
        @yield('legal-content')
    </article>
@endsection
