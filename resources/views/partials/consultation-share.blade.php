<section class="consultation-share" aria-labelledby="consultation-share-heading">
    <h2 class="section-label" id="consultation-share-heading">{{ __('ことばをシェア') }}</h2>

    <div class="consultation-share-actions">
        <button class="consultation-share-button" type="button" data-share-link aria-label="{{ __('リンクでシェア') }}">{{ __('シェア') }}</button>
    </div>
    <details class="consultation-share-details">
        <summary>{{ __('公開用URLを表示') }}</summary>
        <p class="consultation-share-note">{{ __('公開用URLにはメールアドレスやユーザーIDは含まれません。URLを知っている人は結果を閲覧できます（有効期限30日）。') }}</p>
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
    </details>
</section>

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

            shareUrlInput.closest('details')?.setAttribute('open', '');
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
