@extends('layouts.app')

@section('title', __('相談の記録'))
@section('robots', 'noindex, nofollow, noarchive')
@section('main_class', 'history-content')

@section('content')
    <article class="consultation-detail consultation-detail">
        @if ($imageUrl = App\Models\ZenConsultation::imageUrl($consultation['image_number'] ?? null))
            <div class="art-stage consultation-image-stage">
                <img class="consultation-image" src="{{ $imageUrl }}" alt="">
            </div>
        @endif
        <div class="consultation-detail-copy">
            <p class="eyebrow consultation-detail-eyebrow">YOUR REFLECTION</p>
            <h1 class="consultation-detail-title">{{ __('禅師からのことば') }}</h1>
            <section class="result-section">
                <h2 class="section-label">{{ __('あなたの思い') }}</h2>
                <p class="question-quote">{{ $consultation['concern'] }}</p>
            </section>
            <section class="result-section">
                <h2 class="section-label">{{ __('お答え') }}</h2>
                <div class="consultation-copy">{!! app(App\Services\ConsultationResponseFormatter::class)->toHtml($consultation['response']) !!}</div>
            </section>
            <section class="consultation-share" aria-labelledby="consultation-share-heading">
                <h2 class="section-label" id="consultation-share-heading">{{ __('ことばをシェア') }}</h2>
                <p class="consultation-share-note">{{ __('公開用URLにはメールアドレスやユーザーIDは含まれません。URLを知っている人は結果を閲覧できます（有効期限30日）。') }}</p>

                <div class="consultation-share-actions">
                    <button class="consultation-share-button" type="button" data-share-link aria-label="{{ __('リンクでシェア') }}">{{ __('シェア') }}</button>
                </div>
                <div class="consultation-share-url">
                    <input
                        class="consultation-share-url-input"
                        id="consultation-share-url"
                        type="url"
                        value="{{ $shareUrl }}"
                        readonly
                        aria-label="{{ __('公開用相談URL') }}"
                    >
                </div>
            </section>
            <div class="history-actions">
                <form method="POST" action="{{ route('consultations.destroy', $consultationId) }}" onsubmit="return confirm(this.dataset.confirm)" data-confirm="{{ __('この相談の記録を削除しますか？公開用URLも無効になります。') }}">
                    @csrf
                    @method('DELETE')
                    <button class="text-link" type="submit">{{ __('この記録を削除する') }}</button>
                </form>
                <a class="text-link" href="{{ route('consultations.index') }}">{{ __('履歴一覧へ戻る') }}</a>
                <a class="text-link" href="{{ route('zen') }}">{{ __('もう一度相談する') }}</a>
            </div>
        </div>
    </article>
@endsection

@push('page_end')
    <script>
        const shareUrlInput = document.querySelector('#consultation-share-url');
        const showShareStatus = (message, isError = false) => window.showToast(message, isError);

        async function copyConsultationUrl() {
            if (navigator.clipboard && window.isSecureContext) {
                try {
                    await navigator.clipboard.writeText(shareUrlInput.value);
                    showShareStatus(@json(__('公開用URLをコピーしました。')));
                    return true;
                } catch {
                }
            }

            shareUrlInput.focus();
            shareUrlInput.select();
            const copied = document.execCommand('copy');
            showShareStatus(copied
                ? @json(__('公開用URLをコピーしました。'))
                : @json(__('URLを選択しました。コピーしてご利用ください。')));

            return copied;
        }

        shareUrlInput.addEventListener('click', async () => {
            try {
                await copyConsultationUrl();
            } catch (error) {
                showShareStatus(@json(__('コピーできませんでした。上記URLを手動でコピーしてください。')), true);
            }
        });

        document.querySelector('[data-share-link]').addEventListener('click', async () => {
            try {
                if (navigator.share) {
                    await navigator.share({ url: shareUrlInput.value });
                    return;
                }

                const copied = await copyConsultationUrl();
                showShareStatus(copied
                    ? @json(__('公開用URLをコピーしました。共有先に貼り付けてください。'))
                    : @json(__('URLを選択しました。コピーして共有先に貼り付けてください。')));
            } catch (error) {
                if (error instanceof DOMException && error.name === 'AbortError') {
                    return;
                }

                showShareStatus(@json(__('共有できませんでした。上記URLを手動でコピーしてください。')), true);
            }
        });
    </script>
@endpush
