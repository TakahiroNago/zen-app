{{-- Copy / share actions for a consultation. Expects $consultationId; $showSavedLink is optional. --}}
<section class="consultation-share" aria-labelledby="consultation-share-heading">
    <h2 class="sr-only" id="consultation-share-heading">{{ __('ことばをシェア') }}</h2>

    <div class="consultation-share-actions">
        <button
            class="secondary-action"
            type="button"
            data-copy-response="#consultation-copy"
            data-copy-success="{{ __('ことばをコピーしました。') }}"
            data-copy-error="{{ __('コピーできませんでした。本文を選択してコピーしてください。') }}"
        >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"/></svg>
            <span class="action-label-full">{{ __('ことばをコピー') }}</span><span class="action-label-short">{{ __('コピー') }}</span>
        </button>
        <button class="secondary-action consultation-share-button" type="button" data-share-link>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 15V3"/><path d="M7 8l5-5 5 5"/><path d="M5 13v6a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-6"/></svg>
            <span class="action-label-full">{{ __('シェア・リンクをコピー') }}</span><span class="action-label-short">{{ __('シェア') }}</span>
        </button>
        @if ($showSavedLink ?? false)
            <a class="secondary-action" href="{{ route('consultations.index') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 3h12v18l-6-4-6 4z"/></svg>
                {{ __('記録に保存済み') }}
            </a>
        @endif
    </div>
    <p class="consultation-share-note">{{ __('公開用URLにはメールアドレスやユーザーIDは含まれません。URLを知っている人は結果を閲覧できます（有効期限30日）。') }}</p>
    <div class="consultation-share-url" data-share-url-panel hidden>
        <input
            class="consultation-share-url-input"
            id="consultation-share-url"
            type="url"
            value=""
            data-share-endpoint="{{ route('consultations.share-link', $consultationId) }}"
            readonly
            aria-label="{{ __('公開用相談URL') }}"
        >
    </div>
</section>

@push('page_end')
    <script>
        const shareUrlInput = document.querySelector('#consultation-share-url');
        const shareUrlPanel = shareUrlInput.closest('[data-share-url-panel]');
        const shareButton = document.querySelector('[data-share-link]');
        const showShareStatus = (message, isError = false) => window.showToast(message, isError);
        let shareUrlRequest = null;
        let isSharing = false;

        // The public URL is issued only when the user explicitly uses the share controls.
        function ensureShareUrl() {
            if (shareUrlInput.value) {
                return Promise.resolve(shareUrlInput.value);
            }

            shareUrlRequest ??= fetch(shareUrlInput.dataset.shareEndpoint, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': @json(csrf_token()),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            }).then(async (response) => {
                if (!response.ok) {
                    throw new Error('share-link-unavailable');
                }

                const data = await response.json();
                shareUrlInput.value = data.url;

                return data.url;
            }).finally(() => {
                shareUrlRequest = null;
            });

            return shareUrlRequest;
        }

        const isShareUrlError = (error) => error instanceof Error && error.message === 'share-link-unavailable';

        function showShareUrlError() {
            showShareStatus(@json(__('公開用URLを作成できませんでした。時間をおいて再度お試しください。')), true);
        }

        // Reveals the issued URL so it can be copied by hand when the clipboard is unavailable.
        function selectShareUrl() {
            shareUrlPanel.hidden = false;
            shareUrlInput.focus();
            shareUrlInput.select();
        }

        // Must be called synchronously from the click handler: Safari only allows clipboard writes that
        // start within the user gesture, so the URL is handed to ClipboardItem as a pending promise.
        async function writeShareUrlToClipboard() {
            if (!window.isSecureContext || !navigator.clipboard) {
                return false;
            }

            if (!shareUrlInput.value && window.ClipboardItem && navigator.clipboard.write) {
                const urlRequest = ensureShareUrl();
                try {
                    const urlBlob = urlRequest.then((url) => new Blob([url], { type: 'text/plain' }));
                    await navigator.clipboard.write([new ClipboardItem({ 'text/plain': urlBlob })]);

                    return true;
                } catch {
                    // Rethrows when issuing the URL failed; otherwise the clipboard write itself was refused.
                    await urlRequest;
                }
            }

            try {
                await navigator.clipboard.writeText(await ensureShareUrl());

                return true;
            } catch (error) {
                if (isShareUrlError(error)) {
                    throw error;
                }

                return false;
            }
        }

        async function copyShareUrl() {
            if (await writeShareUrlToClipboard()) {
                return true;
            }

            await ensureShareUrl();
            selectShareUrl();

            return document.execCommand('copy');
        }

        shareUrlInput.addEventListener('click', async () => {
            try {
                showShareStatus(await copyShareUrl()
                    ? @json(__('公開用URLをコピーしました。'))
                    : @json(__('URLを選択しました。コピーしてご利用ください。')));
            } catch (error) {
                if (isShareUrlError(error)) {
                    showShareUrlError();
                    return;
                }

                showShareStatus(@json(__('コピーできませんでした。上記URLを手動でコピーしてください。')), true);
            }
        });

        async function shareWithShareSheet() {
            // Without an issued URL, the share sheet can only open after the request completes, and
            // browsers such as Safari may reject it by then. The URL is kept, so the next tap works.
            const shareUrl = shareUrlInput.value || await ensureShareUrl();

            try {
                await navigator.share({ url: shareUrl });
            } catch (error) {
                if (error instanceof DOMException && error.name === 'NotAllowedError') {
                    selectShareUrl();
                    showShareStatus(@json(__('公開用URLを作成しました。もう一度「シェア・リンクをコピー」を押してください。')));
                    return;
                }

                throw error;
            }
        }

        shareButton.addEventListener('click', async () => {
            if (isSharing) {
                return;
            }

            isSharing = true;
            try {
                if (navigator.share) {
                    await shareWithShareSheet();
                    return;
                }

                showShareStatus(await copyShareUrl()
                    ? @json(__('公開用URLをコピーしました。共有先に貼り付けてください。'))
                    : @json(__('URLを選択しました。コピーして共有先に貼り付けてください。')));
            } catch (error) {
                if (error instanceof DOMException && error.name === 'AbortError') {
                    return;
                }

                if (isShareUrlError(error)) {
                    showShareUrlError();
                    return;
                }

                selectShareUrl();
                showShareStatus(@json(__('共有できませんでした。上記URLを手動でコピーしてください。')), true);
            } finally {
                isSharing = false;
            }
        });
    </script>
@endpush
