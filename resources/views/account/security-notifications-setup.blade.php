@extends('layouts.app')

@section('title', __('セキュリティ通知の初期設定'))

@section('content')
    <section class="form-panel">
        <p class="eyebrow">{{ __('SECURITY NOTIFICATIONS') }}</p>
        <h1>{{ __('セキュリティ通知の初期設定') }}</h1>
        <p class="page-description">{{ __('アカウントに関するセキュリティ通知をメールで受け取るか、通知の種類ごとに設定できます。設定はアカウント設定からいつでも変更できます。') }}</p>

        <form class="question-form" method="POST" action="{{ route('account.security-notifications.setup.update') }}">
            @csrf
            @method('PUT')
            @php($selectedNotificationGroups = (array) old('types', array_keys($notificationTypeGroups)))
            @php($securityNotificationsEnabled = old('enabled', true))

            <label class="remember-option security-notification-switch">
                <input name="enabled" type="checkbox" value="1" data-security-notifications-enabled @checked($securityNotificationsEnabled)>
                {{ __('セキュリティ通知を受け取る') }}
            </label>
            @error('enabled')<p class="form-error" role="alert">{{ __($message) }}</p>@enderror

            <fieldset data-security-notification-types @disabled(! $securityNotificationsEnabled)>
                <legend class="question-label">{{ __('通知の種類') }}</legend>
                @foreach ($notificationTypeGroups as $group => $notificationTypeGroup)
                    <label class="remember-option security-notification-switch">
                        <input name="types[]" type="checkbox" value="{{ $group }}" @checked(in_array($group, $selectedNotificationGroups, true))>
                        {{ __($notificationTypeGroup['label']) }}
                    </label>
                @endforeach
            </fieldset>
            @error('types')<p class="form-error" role="alert">{{ __($message) }}</p>@enderror
            @error('types.*')<p class="form-error" role="alert">{{ __($message) }}</p>@enderror

            <button class="primary-action" type="submit">{{ __('設定を保存して続ける') }}</button>
        </form>
    </section>
@endsection

@push('page_end')
    <script>
        const securityNotificationsToggle = document.querySelector('[data-security-notifications-enabled]');
        const securityNotificationTypes = document.querySelector('[data-security-notification-types]');

        securityNotificationsToggle.addEventListener('change', () => {
            securityNotificationTypes.disabled = !securityNotificationsToggle.checked;
        });
    </script>
@endpush
