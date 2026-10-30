<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @hasSection('robots')
        <meta name="robots" content="@yield('robots')">
    @endif
    @stack('meta')
    @php($siteName = e(__('心の庵')).' ZEN REFLECTIONS')
    @php($pageTitle = trim($__env->yieldContent('title')) !== '' ? trim($__env->yieldContent('title')).' | '.$siteName : $siteName)
    @php($pageDescription = trim($__env->yieldContent('description')) ?: e(__('AIとの対話を通じて、禅の考え方を手がかりに自分の心を見つめ直すための場所です。')))
    <title>{!! $pageTitle !!}</title>
    <meta name="description" content="{!! $pageDescription !!}">
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    @sectionMissing('robots')
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{!! $siteName !!}">
        <meta property="og:title" content="{!! $pageTitle !!}">
        <meta property="og:description" content="{!! $pageDescription !!}">
        <meta property="og:url" content="{{ url()->current() }}">
        <meta property="og:image" content="{{ asset('images/logo.png') }}">
        <meta property="og:locale" content="{{ app()->isLocale('ja') ? 'ja_JP' : 'en_US' }}">
        <meta name="twitter:card" content="summary">
    @endif
    @vite(['resources/css/app.css'])
</head>
<body @hasSection('body_class')class="@yield('body_class')"@endif>
    <div class="page-frame">
        @section('header')
            @include('partials.site-header')
        @show

        <main class="{{ trim('page-content '.$__env->yieldContent('main_class')) }}">
            @yield('content')
        </main>

        @include('partials.site-footer')
    </div>
    @stack('page_end')
</body>
</html>
