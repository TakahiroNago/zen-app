@extends('layouts.app')

@section('title', __('ユーザー管理'))
@section('main_class', 'admin-content')

@section('content')
    <section class="admin-panel">
        <p class="eyebrow">{{ __('ADMINISTRATION') }}</p>
        <h1>{{ __('ユーザー管理') }}</h1>
        @if (session('status'))@include('partials.toast', ['message' => __(session('status'))])@endif
        <form class="user-search" method="GET" action="{{ route('admin.users.index') }}">
            <label class="sr-only" for="search">{{ __('名前またはメールアドレスで検索') }}</label>
            <input class="question-input" id="search" name="search" type="search" value="{{ $search }}" placeholder="{{ __('名前・メールアドレス') }}">
            <button class="secondary-action" type="submit">{{ __('検索') }}</button>
            @if ($search !== '')<a class="text-link" href="{{ route('admin.users.index') }}">{{ __('解除') }}</a>@endif
        </form>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr><th>{{ __('ユーザー') }}</th><th>{{ __('権限') }}</th><th>{{ __('状態') }}</th><th>{{ __('登録日') }}</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td><strong>{{ $user->name }}</strong><span>{{ $user->email }}</span></td>
                            <td>{{ $user->isAdmin() ? 'Admin' : 'User' }}</td>
                            <td><span class="status-label {{ $user->is_active ? 'is-active' : 'is-inactive' }}">{{ $user->is_active ? __('利用中') : __('停止中') }}</span></td>
                            <td>{{ $user->created_at->format('Y/m/d') }}</td>
                            <td><a class="table-link" href="{{ route('admin.users.show', $user) }}">{{ __('詳細') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty-state">{{ __('該当するユーザーはいません。') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $users->links() }}
    </section>
@endsection
