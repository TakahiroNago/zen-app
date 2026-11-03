<header class="site-header" data-reveal="header">
    <a class="brand" href="{{ route('home') }}">
        <span class="brand-mark" aria-hidden="true">✦</span>
        <span class="brand-name">{{ __('心の庵') }}</span>
    </a>
    <nav class="account-navigation" aria-label="{{ __('メインメニュー') }}">
        @auth
            <a class="header-history-link" href="{{ route('consultations.index') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l3 2"/></svg>
                {{ __('相談の記録') }}
            </a>
        @endauth
        <div class="language-navigation" aria-label="Language">
            @if (app()->isLocale('ja'))
                <a href="{{ route('home', ['locale' => 'en']) }}" hreflang="en" lang="en">English</a>
            @else
                <a href="{{ route('home', ['locale' => 'ja']) }}" hreflang="ja" lang="ja">日本語</a>
            @endif
        </div>
        @auth
            @if (isset($creditSummary))
                @php($isOutOfCredits = ! $creditSummary['unlimited'] && $creditSummary['total'] === 0)
                <a
                    class="credit-balance-link{{ $isOutOfCredits ? ' is-empty' : '' }}"
                    href="{{ route('plans') }}"
                    aria-label="{{ $creditSummary['unlimited'] ? __('相談回数無制限') : __('利用可能な相談回数').(app()->isLocale('ja') ? ' '.$creditSummary['total'].'回' : ': '.$creditSummary['total'].' '.($creditSummary['total'] === 1 ? 'consultation' : 'consultations')) }}{{ app()->isLocale('ja') ? '。' : '. ' }}{{ __('料金・プランを見る') }}"
                >
                    <span class="credit-balance-dot" aria-hidden="true"></span>
                    @if ($creditSummary['unlimited'])
                        <strong>∞</strong><span class="credit-balance-today">{{ app()->isLocale('ja') ? ' 無制限' : ' unlimited' }}</span>
                    @elseif (app()->isLocale('ja'))
                        <span><span class="credit-balance-today">本日</span>あと <strong>{{ $creditSummary['total'] }}</strong><span class="credit-balance-unit"> 回</span></span>
                    @else
                        <span><strong>{{ $creditSummary['total'] }}</strong><span class="credit-balance-unit"> left</span><span class="credit-balance-today"> today</span></span>
                    @endif
                </a>
            @else
                <a href="{{ route('plans') }}">{{ __('料金・プラン') }}</a>
            @endif
        @endauth
        @auth
            @php($isAccountMenuOpen = request()->routeIs('plans') && request()->query('account_menu') === 'open')
            <div class="account-menu{{ $isAccountMenuOpen ? ' is-open' : '' }}">
                <button
                    type="button"
                    class="account-menu-trigger"
                    aria-label="{{ __('その他のメニュー') }}"
                    aria-controls="account-popover"
                    aria-expanded="{{ $isAccountMenuOpen ? 'true' : 'false' }}"
                >
                    <svg class="account-menu-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                        <path d="M4 7h16M4 12h16M4 17h16"/>
                    </svg>
                </button>
                <section class="account-popover" id="account-popover" aria-label="{{ __('アカウントメニュー') }}">
                    <div class="account-profile">
                        <svg class="account-avatar" aria-hidden="true" viewBox="0 0 48 48">
                            <circle class="account-avatar-background" cx="24" cy="24" r="23"/>
                            <circle class="account-avatar-figure" cx="24" cy="18" r="8"/>
                            <path class="account-avatar-figure" d="M9 41c1.8-8 7.4-12 15-12s13.2 4 15 12"/>
                        </svg>
                        <div class="account-profile-details">
                            <span class="account-profile-name">{{ auth()->user()->name }}</span>
                            <span class="account-profile-email">{{ auth()->user()->email }}</span>
                        </div>
                    </div>
                    <a class="account-popover-link" href="{{ route('consultations.index') }}">{{ __('相談の記録') }}</a>
                    <a class="account-popover-link" href="{{ route('account.settings.edit') }}">{{ __('アカウント設定') }}</a>
                    @if (app()->isLocale('ja'))
                        <a class="account-popover-link account-popover-mobile-only" href="{{ route('home', ['locale' => 'en']) }}" hreflang="en" lang="en">English</a>
                    @else
                        <a class="account-popover-link account-popover-mobile-only" href="{{ route('home', ['locale' => 'ja']) }}" hreflang="ja" lang="ja">日本語</a>
                    @endif
                    @if (auth()->user()->isAdmin())
                        <a class="account-popover-link" href="{{ route('admin.dashboard') }}">{{ __('管理者画面') }}</a>
                        <a class="account-popover-link" href="{{ route('admin.users.index') }}">{{ __('ユーザー管理') }}</a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="account-popover-link" type="submit">{{ __('ログアウト') }}</button>
                    </form>
                </section>
            </div>
        @else
            <a href="{{ route('login') }}">{{ __('ログイン') }}</a>
            <a class="account-register" href="{{ route('register') }}">{{ __('新規登録') }}</a>
        @endauth
    </nav>
</header>
