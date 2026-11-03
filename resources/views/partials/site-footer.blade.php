<footer class="site-footer" data-reveal="footer">
    <small class="site-footer-copyright">© 2026 Art Full Stack</small>
    <nav class="site-footer-links" aria-label="{{ __('サイト情報') }}">
        <a href="{{ route('about') }}">{{ __('心の庵について') }}</a>
        <a href="{{ route('legal.terms') }}">{{ __('利用規約') }}</a>
        <a href="{{ route('legal.privacy') }}">{{ __('プライバシー') }}</a>
        <a href="{{ route('legal.commercial-transactions') }}">{{ __('特定商取引法表記') }}</a>
        <a href="{{ route('legal.refund-credit-policy') }}">{{ __('返金ポリシー') }}</a>
        <a href="{{ route('contact.create') }}">{{ __('お問い合わせ') }}</a>
    </nav>
</footer>
@vite(['resources/js/app.js'])
