<?php

namespace App\Services;

/**
 * Tells which kinds of personal information a text seems to contain, and masks them.
 *
 * The detection rules are not here. They are maintained in llm-inspect (its default rules and the
 * personal-ja pack) and reached through a PersonalInfoScanner. This class only decides how the
 * findings are named and shown in this application.
 */
class PersonalInfoDetector
{
    public const EMAIL = 'email';

    public const PHONE = 'phone';

    public const POSTAL_CODE = 'postal_code';

    public const URL = 'url';

    public const CREDIT_CARD = 'credit_card';

    public const COMPANY = 'company';

    public const ADDRESS = 'address';

    public const NAME = 'name';

    /**
     * llm-inspect rule id => the kind of personal information it stands for here. A finding of a
     * rule that is not listed (an IP address, an API key, a prompt-injection pattern) is not
     * personal information for this application and is ignored.
     */
    private const RULE_TYPES = [
        'pii.email' => self::EMAIL,
        'pii.ja.url' => self::URL,
        'pii.ja.sns_handle' => self::URL,
        'pii.ja.company' => self::COMPANY,
        'pii.ja.address' => self::ADDRESS,
        'pii.ja.postal_code' => self::POSTAL_CODE,
        'pii.credit_card' => self::CREDIT_CARD,
        'pii.phone_jp' => self::PHONE,
        'pii.phone_intl' => self::PHONE,
        'pii.ja.phone' => self::PHONE,
        'pii.ja.person_name' => self::NAME,
    ];

    /**
     * Translation keys of the placeholders, in the order detect() reports the kinds.
     */
    private const LABELS = [
        self::EMAIL => 'メールアドレス',
        self::URL => 'URL',
        self::COMPANY => '会社名',
        self::ADDRESS => '住所',
        self::POSTAL_CODE => '郵便番号',
        self::CREDIT_CARD => 'カード番号',
        self::PHONE => '電話番号',
        self::NAME => '氏名',
    ];

    public function __construct(private readonly PersonalInfoScanner $scanner) {}

    /**
     * @return list<string>
     *
     * @throws PersonalInfoDetectionUnavailable
     */
    public function detect(#[\SensitiveParameter] string $text): array
    {
        $found = array_column($this->findings($text), 'type', 'type');

        return array_values(array_filter(
            array_keys(self::LABELS),
            fn (string $type): bool => isset($found[$type]),
        ));
    }

    /**
     * Replaces detected personal information with placeholders such as [氏名]. Everything else is
     * returned exactly as it was written.
     *
     * @throws PersonalInfoDetectionUnavailable
     */
    public function redact(#[\SensitiveParameter] string $text): string
    {
        $findings = $this->findings($text);
        usort($findings, fn (array $a, array $b): int => [$a['start'], $b['end']] <=> [$b['start'], $a['end']]);

        $redacted = '';
        $cursor = 0;

        foreach ($findings as $finding) {
            // Overlapping findings share the placeholder of the one that starts first.
            if ($finding['start'] < $cursor) {
                if ($finding['end'] > $cursor) {
                    $cursor = $finding['end'];
                }

                continue;
            }

            $redacted .= substr($text, $cursor, $finding['start'] - $cursor)
                .'['.__(self::LABELS[$finding['type']]).']';
            $cursor = $finding['end'];
        }

        return $redacted.substr($text, $cursor);
    }

    /**
     * @return list<array{type: string, start: int, end: int}>
     */
    private function findings(#[\SensitiveParameter] string $text): array
    {
        $findings = [];

        foreach ($this->scanner->scan($text) as $finding) {
            $type = self::RULE_TYPES[$finding['rule_id']] ?? null;

            if ($type !== null) {
                $findings[] = ['type' => $type, 'start' => $finding['start'], 'end' => $finding['end']];
            }
        }

        return $findings;
    }
}
