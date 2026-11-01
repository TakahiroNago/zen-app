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
                value=""
                data-share-endpoint="{{ route('consultations.share-link', $consultationId) }}"
                readonly
                aria-label="{{ __('公開用相談URL') }}"
            >
        </div>
    </details>
</section>

@push('page_end')
    <script>
        const shareUrlInput = document.querySelector('#consultation-share-url');
        const shareDetails = shareUrlInput.closest('details');
        const shareButton = document.querySelector('[data-share-link]');
        const showShareStatus = (message, isError = false) => window.showToast(message, isError);
        let shareUrlRequest = null;
        let isSharing = false;

        // The public URL is issued only when the user explicitly opens or uses the share controls.
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

        function selectShareUrl() {
            shareDetails?.setAttribute('open', '');
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

        shareDetails?.addEventListener('toggle', () => {
            if (shareDetails.open) {
                ensureShareUrl().catch(showShareUrlError);
            }
        });

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
                    showShareStatus(@json(__('公開用URLを作成しました。もう一度「シェア」を押してください。')));
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

                showShareStatus(@json(__('共有できませんでした。上記URLを手動でコピーしてください。')), true);
            } finally {
                isSharing = false;
            }
        });
    </script>
@endpush
