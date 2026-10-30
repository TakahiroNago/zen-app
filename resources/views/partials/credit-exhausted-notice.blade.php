<a class="form-notice credit-notice credit-notice-link" href="{{ $isGuest ? route('register') : route('plans') }}"@isset($role) role="{{ $role }}"@endisset>
    {{ $isGuest ? __('無料体験を使い切りました。無料会員登録すると毎日1回相談できます。') : __('本日利用可能なクレジットを使い切りました。') }}{{ app()->isLocale('ja') ? '→ ' : ' → ' }}{{ $isGuest ? __('会員登録はこちら') : __('有料プランを見る') }}
</a>
