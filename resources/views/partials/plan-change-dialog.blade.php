<dialog class="confirm-dialog" data-plan-change-dialog aria-labelledby="plan-change-dialog-title">
    <h2 id="plan-change-dialog-title">{{ __('プラン変更の確認') }}</h2>
    <p>{{ __('以下の内容をご確認のうえ、プラン変更を確定してください。') }}</p>
    <fieldset class="plan-billing-options" data-plan-change-options="premium" hidden>
        <legend>{{ __('お支払い方法') }}</legend>
        @if (config('services.stripe.prices.premium'))
            <label class="plan-billing-option">
                <input type="radio" name="dialog-premium-plan" value="premium" data-plan-dialog-option data-confirmation-note="{{ __('変更後のプラン') }}：{{ __('プレミアムプラン') }}({{ __('月額') }})" checked>
                <span>{{ __('月額プラン') }}（{{ app()->isLocale('ja') ? '580円' : '¥580' }}/{{ __('月') }}）</span>
            </label>
        @endif
        @if (config('services.stripe.prices.premium_yearly'))
            <label class="plan-billing-option">
                <input type="radio" name="dialog-premium-plan" value="premium_yearly" data-plan-dialog-option data-confirmation-note="{{ __('変更後のプラン') }}：{{ __('プレミアムプラン') }}({{ __('年額') }})" @checked(! config('services.stripe.prices.premium'))>
                <span>{{ __('年額プラン') }}（{{ app()->isLocale('ja') ? '5,800円' : '¥5,800' }}/{{ __('年') }}）</span>
            </label>
        @endif
    </fieldset>
    <fieldset class="plan-billing-options" data-plan-change-options="lite" hidden>
        <legend>{{ __('お支払い方法') }}</legend>
        @if (config('services.stripe.prices.lite'))
            <label class="plan-billing-option">
                <input type="radio" name="dialog-lite-plan" value="lite" data-plan-dialog-option data-confirmation-note="{{ __('変更後のプラン') }}：{{ __('ライトプラン') }}({{ __('月額') }})" checked>
                <span>{{ __('月額プラン') }}（{{ app()->isLocale('ja') ? '220円' : '¥220' }}/{{ __('月') }}）</span>
            </label>
        @endif
        @if (config('services.stripe.prices.lite_yearly'))
            <label class="plan-billing-option">
                <input type="radio" name="dialog-lite-plan" value="lite_yearly" data-plan-dialog-option data-confirmation-note="{{ __('変更後のプラン') }}：{{ __('ライトプラン') }}({{ __('年額') }})" @checked(! config('services.stripe.prices.lite'))>
                <span>{{ __('年額プラン') }}（{{ app()->isLocale('ja') ? '2,200円' : '¥2,200' }}/{{ __('年') }}）</span>
            </label>
        @endif
    </fieldset>
    <fieldset class="plan-billing-options" data-plan-change-timing="lite" hidden>
        <legend>{{ __('切り替え時期') }}</legend>
        <label class="plan-billing-option">
            <input type="radio" name="timing" value="period_end" form="account-lite-plan-change-form" data-plan-dialog-timing data-confirmation-note="{{ __('現在のプレミアム期間終了後にライトプランへ切り替えます。') }}" checked required>
            <span>{{ __('契約期間終了時に切り替える') }}</span>
        </label>
        <label class="plan-billing-option">
            <input type="radio" name="timing" value="immediate" form="account-lite-plan-change-form" data-plan-dialog-timing data-confirmation-note="{{ __('返金対象となるお支払いがある場合は未使用期間分を返金し、すぐにライトプランへ切り替えます。ライトプランの新しい契約期間が開始します。') }}">
            <span>{{ __('すぐに切り替えて未使用期間分を返金') }}</span>
        </label>
    </fieldset>
    <p data-plan-change-note></p>
    <div class="confirm-dialog-actions">
        <button class="secondary-action" type="button" data-plan-change-cancel>{{ __('キャンセル') }}</button>
        <button class="primary-action" type="button" data-plan-change-confirm>{{ __('変更を確定する') }}</button>
    </div>
</dialog>
