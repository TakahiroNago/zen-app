<?php

namespace App\Services;

/**
 * Validates the "今日の禅語" (Zen saying) and "今日のひとつ" (one thing to try today) that are
 * extracted from a consultation answer. Anything that is not grounded in the answer or does not
 * match the expected shape is dropped, so the page simply omits that block.
 */
class ConsultationHighlights
{
    public const ZENGO_MAX_LENGTH = 20;

    public const ZENGO_READING_MAX_LENGTH = 40;

    public const ZENGO_MEANING_MAX_LENGTH = 60;

    public const DAILY_ACTION_MAX_LENGTH = ['ja' => 100, 'en' => 200];

    public const KEYS = ['zengo', 'zengo_reading', 'zengo_meaning', 'daily_action'];

    public function __construct(private readonly PersonalInfoDetector $personalInfoDetector) {}

    /**
     * Reads the highlights from the decoded JSON returned alongside the consultation metadata.
     *
     * @param  array<string, mixed>  $output
     * @return array{zengo: ?string, zengo_reading: ?string, zengo_meaning: ?string, daily_action: ?string}
     */
    public function fromModelOutput(array $output, #[\SensitiveParameter] string $answer, string $locale): array
    {
        $zengo = is_array($output['zengo'] ?? null) ? $output['zengo'] : [];

        return $this->validated([
            'zengo' => $zengo['text'] ?? null,
            'zengo_reading' => $zengo['reading'] ?? null,
            'zengo_meaning' => $zengo['meaning'] ?? null,
            'daily_action' => $output['daily_action'] ?? null,
        ], $answer, $locale);
    }

    /**
     * Re-validates highlights that were stored in the session or a cached share payload.
     *
     * @param  array<string, mixed>  $consultation
     * @return array{zengo: ?string, zengo_reading: ?string, zengo_meaning: ?string, daily_action: ?string}
     */
    public function fromStored(#[\SensitiveParameter] array $consultation): array
    {
        $answer = is_string($consultation['response'] ?? null) ? $consultation['response'] : '';
        $highlights = array_intersect_key($consultation, array_flip(self::KEYS));

        return $this->validated($highlights, $answer, $this->isJapanese($answer) ? 'ja' : 'en');
    }

    /**
     * @param  array<string, mixed>  $highlights
     * @return array{zengo: ?string, zengo_reading: ?string, zengo_meaning: ?string, daily_action: ?string}
     */
    private function validated(#[\SensitiveParameter] array $highlights, #[\SensitiveParameter] string $answer, string $locale): array
    {
        $zengo = $this->singleLine($this->withoutInlineReading($highlights['zengo'] ?? null), self::ZENGO_MAX_LENGTH);
        $reading = $this->singleLine($highlights['zengo_reading'] ?? null, self::ZENGO_READING_MAX_LENGTH);
        $meaning = $this->singleLine($highlights['zengo_meaning'] ?? null, self::ZENGO_MEANING_MAX_LENGTH);

        // A Zen saying is shown only for Japanese answers that actually introduce it, with a kana reading.
        $hasZengo = $locale === 'ja'
            && $zengo !== null
            && $reading !== null
            && $meaning !== null
            && mb_strpos($answer, $zengo) !== false
            && preg_match('/^[\p{Hiragana}ー ]+$/u', $reading) === 1;

        $dailyAction = $this->singleLine(
            $highlights['daily_action'] ?? null,
            self::DAILY_ACTION_MAX_LENGTH[$locale] ?? self::DAILY_ACTION_MAX_LENGTH['en'],
        );
        if ($dailyAction !== null && $this->personalInfoDetector->detect($dailyAction) !== []) {
            $dailyAction = null;
        }

        return [
            'zengo' => $hasZengo ? $zengo : null,
            'zengo_reading' => $hasZengo ? $reading : null,
            'zengo_meaning' => $hasZengo ? $meaning : null,
            'daily_action' => $dailyAction,
        ];
    }

    private function singleLine(mixed $value, int $maxLength): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' || mb_strlen($value) > $maxLength || preg_match('/[\r\n]/', $value) === 1
            ? null
            : $value;
    }

    /**
     * Drops a kana reading the answer wrote in parentheses right after the saying, such as
     * 「平常心是道（へいじょうしんこれどう）」, so the saying is not shown with its reading twice.
     */
    private function withoutInlineReading(mixed $zengo): mixed
    {
        if (! is_string($zengo)) {
            return $zengo;
        }

        $stripped = trim((string) preg_replace('/\s*[（(][\p{Hiragana}ー\s]+[）)]$/u', '', $zengo));

        return $stripped === '' ? $zengo : $stripped;
    }

    private function isJapanese(#[\SensitiveParameter] string $text): bool
    {
        return preg_match('/[\p{Hiragana}\p{Katakana}]/u', $text) === 1;
    }
}
