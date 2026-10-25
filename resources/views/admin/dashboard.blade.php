<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('管理者ダッシュボード') }} | {{ __('心の庵') }} ZEN REFLECTIONS</title>
    @vite(['resources/css/app.css'])
</head>
<body>
    <div class="page-frame">
        @include('partials.site-header')
        <main class="page-content admin-content">
            <section class="admin-panel">
                <p class="eyebrow">{{ __('ADMINISTRATION') }}</p>
                <h1>{{ __('管理者ダッシュボード') }}</h1>
                @if (session('status'))@include('partials.toast', ['message' => __(session('status'))])@endif

                <div class="dashboard-section">
                    <h2>{{ __('ユーザー管理') }}</h2>
                    <div class="metric-grid">
                        <div class="metric"><span>{{ __('登録ユーザー') }}</span><strong>{{ number_format($users['total']) }}</strong></div>
                        <div class="metric"><span>{{ __('利用中') }}</span><strong>{{ number_format($users['active']) }}</strong></div>
                        <div class="metric"><span>{{ __('停止中') }}</span><strong>{{ number_format($users['suspended']) }}</strong></div>
                        <div class="metric"><span>{{ __('メール未認証') }}</span><strong>{{ number_format($users['unverified']) }}</strong></div>
                        <div class="metric"><span>{{ __('本日の新規登録') }}</span><strong>{{ number_format($users['registered_today']) }}</strong></div>
                        <div class="metric"><span>{{ __('7日間の新規登録') }}</span><strong>{{ number_format($users['registered_7_days']) }}</strong></div>
                    </div>
                    <div class="admin-table-wrap">
                        <table class="admin-table">
                            <thead>
                                <tr><th>{{ __('ユーザー') }}</th><th>{{ __('登録日時') }}</th><th>{{ __('アカウント状態') }}</th><th></th></tr>
                            </thead>
                            <tbody>
                                @forelse ($users['recent'] as $user)
                                    <tr>
                                        <td><strong>{{ $user->name }}</strong><span>{{ $user->email }}</span></td>
                                        <td>{{ $user->created_at->format('Y/m/d H:i') }}</td>
                                        <td><span class="status-label {{ $user->is_active ? 'is-active' : 'is-inactive' }}">{{ $user->is_active ? __('利用中') : __('停止中') }}</span></td>
                                        <td><a class="table-link" href="{{ route('admin.users.show', $user) }}">{{ __('詳細') }}</a></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="empty-state">{{ __('該当するユーザーはいません。') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <a class="text-link" href="{{ route('admin.users.index') }}">{{ __('ユーザー一覧へ') }}</a>
                </div>

                <div class="dashboard-section">
                    <h2>{{ __('課金管理') }}</h2>
                    <div class="metric-grid">
                        <div class="metric"><span>{{ __('無料プラン') }}</span><strong>{{ number_format($billing['plans']['free']) }}</strong></div>
                        <div class="metric"><span>{{ __('ライトプラン') }}</span><strong>{{ number_format($billing['plans']['lite']) }}</strong></div>
                        <div class="metric"><span>{{ __('プレミアムプラン') }}</span><strong>{{ number_format($billing['plans']['premium']) }}</strong></div>
                        <div class="metric"><span>{{ __('Stripe顧客') }}</span><strong>{{ number_format($billing['customers']) }}</strong></div>
                        <div class="metric"><span>{{ __('期間終了時に解約予定') }}</span><strong>{{ number_format($billing['cancel_at_period_end']) }}</strong></div>
                        <div class="metric"><span>{{ __('30日間のクレジット購入') }}</span><strong>{{ number_format($billing['credit_purchases_30_days']) }}</strong></div>
                    </div>
                    <h3>{{ __('Stripe契約状態') }}</h3>
                    <div class="metric-grid">
                        @foreach ($billing['statuses'] as $status => $total)
                            <div class="metric"><span>{{ $status }}</span><strong>{{ number_format($total) }}</strong></div>
                        @endforeach
                    </div>
                    @if ($billing['problem_subscriptions']->isNotEmpty())
                        <h3>{{ __('支払いに問題のある契約') }}</h3>
                        <div class="admin-table-wrap">
                            <table class="admin-table">
                                <thead>
                                    <tr><th>{{ __('ユーザー') }}</th><th>{{ __('プラン') }}</th><th>{{ __('Stripe契約状態') }}</th><th>{{ __('現在の期間終了日') }}</th></tr>
                                </thead>
                                <tbody>
                                    @foreach ($billing['problem_subscriptions'] as $user)
                                        <tr>
                                            <td><strong>{{ $user->name }}</strong><span>{{ $user->email }}</span></td>
                                            <td>{{ $user->plan }}</td>
                                            <td><span class="status-label is-inactive">{{ $user->stripe_subscription_status }}</span></td>
                                            <td>{{ $user->stripe_current_period_end?->format('Y/m/d') ?? '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                <div class="dashboard-section">
                    <h2>{{ __('利用状況') }}</h2>
                    <div class="metric-grid">
                        <div class="metric"><span>{{ __('相談回数（累計）') }}</span><strong>{{ number_format($usage['total']) }}</strong></div>
                        <div class="metric"><span>{{ __('本日') }}</span><strong>{{ number_format($usage['today']) }}</strong></div>
                        <div class="metric"><span>{{ __('直近7日間') }}</span><strong>{{ number_format($usage['last_7_days']) }}</strong></div>
                        <div class="metric"><span>{{ __('直近30日間') }}</span><strong>{{ number_format($usage['last_30_days']) }}</strong></div>
                        <div class="metric"><span>{{ __('7日間の利用ユーザー') }}</span><strong>{{ number_format($usage['active_users_7_days']) }}</strong></div>
                    </div>
                    <div class="admin-table-wrap">
                        <table class="admin-table">
                            <thead>
                                <tr><th>{{ __('日付') }}</th><th>{{ __('相談回数') }}</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($usage['daily'] as $date => $total)
                                    <tr><td>{{ str_replace('-', '/', $date) }}</td><td>{{ number_format($total) }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($usage['topics']->isNotEmpty())
                        <h3>{{ __('相談テーマ上位') }}</h3>
                        <div class="metric-grid">
                            @foreach ($usage['topics'] as $topic => $total)
                                <div class="metric"><span>{{ $topic }}</span><strong>{{ number_format($total) }}</strong></div>
                            @endforeach
                        </div>
                    @endif

                    <h3>{{ __('エラー状況') }}</h3>
                    <div class="metric-grid">
                        <div class="metric"><span>{{ __('ログのエラー（24時間）') }}</span><strong>{{ number_format($errors['log_errors_24h']) }}</strong></div>
                        <div class="metric"><span>{{ __('失敗ジョブ（24時間）') }}</span><strong>{{ number_format($errors['failed_jobs_24h']) }}</strong></div>
                        <div class="metric"><span>{{ __('失敗ジョブ（累計）') }}</span><strong>{{ number_format($errors['failed_jobs_total']) }}</strong></div>
                        <div class="metric"><span>{{ __('メール送信失敗') }}</span><strong>{{ number_format($errors['mail_failures']) }}</strong></div>
                    </div>
                    @if (! $errors['log_readable'])
                        <p class="settings-note">{{ __('ログファイルを読み取れませんでした。') }}</p>
                    @endif
                    @if ($errors['log_recent'] !== [])
                        <ul class="error-log">
                            @foreach ($errors['log_recent'] as $entry)
                                <li><time>{{ $entry['at'] }}</time> <strong>{{ $entry['level'] }}</strong> {{ $entry['message'] }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                <div class="dashboard-section">
                    <h2>{{ __('運用管理') }}</h2>
                    <h3>{{ __('システムの稼働状況') }}</h3>
                    <div class="metric-grid">
                        @foreach ($operations['checks'] as $check)
                            <div class="metric">
                                <span>{{ $check['label'] }}</span>
                                <strong><span class="status-label {{ $check['ok'] ? 'is-active' : 'is-inactive' }}">{{ $check['ok'] ? __('正常') : __('要確認') }}</span></strong>
                                <small>{{ $check['detail'] }}</small>
                            </div>
                        @endforeach
                        <div class="metric"><span>{{ __('待機中のジョブ') }}</span><strong>{{ number_format($operations['pending_jobs']) }}</strong></div>
                        <div class="metric">
                            <span>{{ __('最後のStripe Webhook') }}</span>
                            <strong class="metric-text">{{ $operations['last_webhook'] ? \Illuminate\Support\Carbon::parse($operations['last_webhook']->processed_at)->format('Y/m/d H:i') : '-' }}</strong>
                            @if ($operations['last_webhook'])<small>{{ $operations['last_webhook']->type }}</small>@endif
                        </div>
                    </div>

                    <h3>{{ __('問い合わせ') }}</h3>
                    <div class="metric-grid">
                        <div class="metric"><span>{{ __('未対応') }}</span><strong>{{ number_format($operations['unresolved_inquiries']) }}</strong></div>
                        <div class="metric"><span>{{ __('直近7日間') }}</span><strong>{{ number_format($operations['inquiries_7_days']) }}</strong></div>
                    </div>
                    <div class="admin-table-wrap">
                        <table class="admin-table">
                            <thead>
                                <tr><th>{{ __('お名前') }}</th><th>{{ __('件名') }}</th><th>{{ __('受信日時') }}</th><th>{{ __('状態') }}</th><th></th></tr>
                            </thead>
                            <tbody>
                                @forelse ($operations['recent_inquiries'] as $inquiry)
                                    <tr>
                                        <td><strong>{{ $inquiry->name }}</strong><span>{{ $inquiry->email }}</span></td>
                                        <td>
                                            <details>
                                                <summary>{{ $inquiry->subject }}</summary>
                                                <p class="inquiry-message">{{ $inquiry->message }}</p>
                                            </details>
                                        </td>
                                        <td>{{ $inquiry->created_at->format('Y/m/d H:i') }}</td>
                                        <td>
                                            <span class="status-label {{ $inquiry->resolved_at ? 'is-active' : 'is-inactive' }}">{{ $inquiry->resolved_at ? __('対応済み') : __('未対応') }}</span>
                                            @unless ($inquiry->mail_sent)<span class="status-label is-inactive">{{ __('メール送信失敗') }}</span>@endunless
                                        </td>
                                        <td>
                                            @unless ($inquiry->resolved_at)
                                                <form method="POST" action="{{ route('admin.inquiries.resolve', $inquiry) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button class="secondary-action" type="submit">{{ __('対応済みにする') }}</button>
                                                </form>
                                            @endunless
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="empty-state">{{ __('問い合わせはまだありません。') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </main>
        @include('partials.site-footer')
    </div>
</body>
</html>
