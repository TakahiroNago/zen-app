<?php

namespace App\Services;

use App\Models\ZenConsultation;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use UnexpectedValueException;

/**
 * Public share links are issued only when the owner explicitly asks for one.
 *
 * - Saved consultations: the token is stored encrypted (APP_KEY) on the row together with its hash
 *   and expiry, so the owner can retrieve the same link again. Nothing is copied into the cache.
 * - Guest (session) consultations: the token lives in the encrypted session, and the public payload
 *   is cached under `public_consultation_share:v2:{hash}`, encrypted with a key derived from the token.
 *   The cache alone therefore never reveals consultation content.
 * - Legacy links: plaintext payloads under `public_consultation_share:{hash}` created before this design
 *   are still readable until they expire, and are removed whenever their consultation is revoked.
 */
class PublicConsultationShareService
{
    public const EXPIRATION_DAYS = 30;

    private const LEGACY_CACHE_PREFIX = 'public_consultation_share:';

    private const CACHE_PREFIX = 'public_consultation_share:v2:';

    private const KEY_DERIVATION_INFO = 'zen-public-consultation-share-v2';

    public function __construct(
        private readonly PersonalInfoDetector $personalInfoDetector,
        private readonly ConsultationHighlights $highlights,
    ) {}

    /**
     * Returns the active share token of a saved consultation, issuing a new one when none is active.
     */
    public function shareSavedConsultation(ZenConsultation $consultation): string
    {
        $activeToken = $this->activeSavedToken($consultation);
        if ($activeToken !== null) {
            return $activeToken;
        }

        $previousTokenHash = $consultation->share_token_hash;
        $token = $this->newToken();
        $consultation->forceFill([
            'share_token' => $token,
            'share_token_hash' => $this->tokenHash($token),
            'share_expires_at' => now()->addDays(self::EXPIRATION_DAYS),
        ]);

        // Compare-and-swap on the previous hash: when concurrent requests race, only the first one
        // stores its token and the others return that same token instead of invalidating it.
        $updatedRows = ZenConsultation::query()
            ->whereKey($consultation->getKey())
            ->where(fn ($query) => $previousTokenHash === null
                ? $query->whereNull('share_token_hash')
                : $query->where('share_token_hash', $previousTokenHash))
            ->toBase()
            ->update(Arr::only($consultation->getAttributes(), ['share_token', 'share_token_hash', 'share_expires_at']));

        if ($updatedRows === 0) {
            return $this->activeSavedToken($consultation->refresh())
                ?? throw new RuntimeException('The share link was changed concurrently and could not be issued.');
        }

        $consultation->syncOriginal();
        $this->revoke($previousTokenHash);

        return $token;
    }

    /**
     * Returns the session consultation with an active share token, issuing a new one when none is active.
     *
     * @param  array{concern: string, response: string, image_number?: int|null, share_token?: string, share_expires_at?: int}  $consultation
     * @return array{concern: string, response: string, image_number?: int|null, share_token: string, share_expires_at: int}
     */
    public function shareSessionConsultation(#[\SensitiveParameter] array $consultation): array
    {
        $token = $consultation['share_token'] ?? null;
        $expiresAt = $consultation['share_expires_at'] ?? null;
        if (is_string($token) && is_int($expiresAt) && $expiresAt > now()->getTimestamp() && Cache::has($this->cacheKey($token))) {
            return $consultation;
        }

        $token = $this->newToken();
        $expiresAt = now()->addDays(self::EXPIRATION_DAYS);
        $payload = $this->publicPayload($consultation);
        Cache::put(
            $this->cacheKey($token),
            $this->encrypter($token)->encryptString(json_encode($payload, JSON_THROW_ON_ERROR)),
            $expiresAt,
        );

        return [...$consultation, 'share_token' => $token, 'share_expires_at' => $expiresAt->getTimestamp()];
    }

    /**
     * @return array{concern: ?string, response: string, concern_hidden: bool, image_number: ?int, zengo: ?string, zengo_reading: ?string, zengo_meaning: ?string, daily_action: ?string}|null
     */
    public function find(#[\SensitiveParameter] string $token): ?array
    {
        $savedConsultation = ZenConsultation::query()
            ->where('share_token_hash', $this->tokenHash($token))
            ->whereNotNull('share_token')
            ->where('share_expires_at', '>', now())
            ->first();
        if ($savedConsultation !== null) {
            return $this->publicPayload($savedConsultation->only(['concern', 'response', 'image_number', ...ConsultationHighlights::KEYS]));
        }

        $encryptedPayload = Cache::get($this->cacheKey($token));
        if ($encryptedPayload !== null) {
            if (! is_string($encryptedPayload)) {
                throw new UnexpectedValueException('Cached public consultation has an invalid format.');
            }

            return $this->validatedPayload(json_decode(
                $this->encrypter($token)->decryptString($encryptedPayload),
                true,
                flags: JSON_THROW_ON_ERROR,
            ));
        }

        $legacyPayload = Cache::get(self::LEGACY_CACHE_PREFIX.$this->tokenHash($token));

        return $legacyPayload === null ? null : $this->validatedPayload($legacyPayload);
    }

    public function tokenHash(#[\SensitiveParameter] string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Removes the cached public payloads (current and legacy format) for a token hash.
     * Links of saved consultations are revoked by clearing or deleting the row itself.
     */
    public function revoke(?string $tokenHash): void
    {
        if ($tokenHash !== null) {
            Cache::forget(self::CACHE_PREFIX.$tokenHash);
            Cache::forget(self::LEGACY_CACHE_PREFIX.$tokenHash);
        }
    }

    /**
     * Returns the stored token while it is still valid. A token that can no longer be decrypted
     * (for example after APP_KEY was changed without APP_PREVIOUS_KEYS) is treated as absent,
     * so the owner gets a new link instead of an error.
     */
    private function activeSavedToken(ZenConsultation $consultation): ?string
    {
        if (! $consultation->share_expires_at?->isFuture()) {
            return null;
        }

        try {
            $token = $consultation->share_token;
        } catch (DecryptException) {
            return null;
        }

        return is_string($token) ? $token : null;
    }

    private function newToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * @param  array{concern: string, response: string, image_number?: mixed, zengo?: mixed, zengo_reading?: mixed, zengo_meaning?: mixed, daily_action?: mixed}  $consultation
     * @return array{concern: ?string, response: string, concern_hidden: bool, image_number: ?int, zengo: ?string, zengo_reading: ?string, zengo_meaning: ?string, daily_action: ?string}
     */
    private function publicPayload(#[\SensitiveParameter] array $consultation): array
    {
        $hideConcern = $this->personalInfoDetector->detect($consultation['concern']) !== [];
        $imageNumber = $consultation['image_number'] ?? null;

        return [
            'concern' => $hideConcern ? null : $consultation['concern'],
            'response' => $consultation['response'],
            'concern_hidden' => $hideConcern,
            'image_number' => is_int($imageNumber) ? $imageNumber : null,
            ...$this->highlights->fromStored($consultation),
        ];
    }

    /**
     * @return array{concern: ?string, response: string, concern_hidden: bool, image_number: ?int, zengo: ?string, zengo_reading: ?string, zengo_meaning: ?string, daily_action: ?string}
     */
    private function validatedPayload(#[\SensitiveParameter] mixed $payload): array
    {
        if (
            ! is_array($payload)
            || ! (is_string($payload['concern'] ?? null) || ($payload['concern_hidden'] ?? false) === true)
            || ! is_string($payload['response'] ?? null)
        ) {
            throw new UnexpectedValueException('Cached public consultation has an invalid format.');
        }

        return [
            'concern' => is_string($payload['concern'] ?? null) ? $payload['concern'] : null,
            'response' => $payload['response'],
            'concern_hidden' => ($payload['concern_hidden'] ?? false) === true,
            'image_number' => is_int($payload['image_number'] ?? null) ? $payload['image_number'] : null,
            ...$this->highlights->fromStored($payload),
        ];
    }

    private function cacheKey(#[\SensitiveParameter] string $token): string
    {
        return self::CACHE_PREFIX.$this->tokenHash($token);
    }

    private function encrypter(#[\SensitiveParameter] string $token): Encrypter
    {
        return new Encrypter(hash_hkdf('sha256', $token, 32, self::KEY_DERIVATION_INFO), 'aes-256-gcm');
    }
}
