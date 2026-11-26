<?php

use App\Services\PersonalInfoDetectionUnavailable;
use App\Services\PersonalInfoDetector;
use Tests\Support\FakePersonalInfoScanner;
use Tests\TestCase;

uses(TestCase::class);

/*
 * The rules themselves are in llm-inspect and tested there. These tests cover what this
 * application does with the findings: which rule counts as which kind of personal information,
 * and how the text is masked.
 */

dataset('rule ids', [
    'email' => ['pii.email', PersonalInfoDetector::EMAIL],
    'hyphenated phone' => ['pii.phone_jp', PersonalInfoDetector::PHONE],
    'international phone' => ['pii.phone_intl', PersonalInfoDetector::PHONE],
    'phone without separators' => ['pii.ja.phone', PersonalInfoDetector::PHONE],
    'postal code' => ['pii.ja.postal_code', PersonalInfoDetector::POSTAL_CODE],
    'url' => ['pii.ja.url', PersonalInfoDetector::URL],
    'account handle' => ['pii.ja.sns_handle', PersonalInfoDetector::URL],
    'name' => ['pii.ja.person_name', PersonalInfoDetector::NAME],
    'company' => ['pii.ja.company', PersonalInfoDetector::COMPANY],
    'address' => ['pii.ja.address', PersonalInfoDetector::ADDRESS],
    'credit card' => ['pii.credit_card', PersonalInfoDetector::CREDIT_CARD],
]);

test('each llm-inspect rule is reported as its kind of personal information', function (string $ruleId, string $type) {
    $detector = new PersonalInfoDetector((new FakePersonalInfoScanner)->flag('XXXX', $ruleId));

    expect($detector->detect('before XXXX after'))->toBe([$type]);
})->with('rule ids');

test('findings that are not personal information here are ignored', function (string $ruleId) {
    $detector = new PersonalInfoDetector((new FakePersonalInfoScanner)->flag('XXXX', $ruleId));

    expect($detector->detect('before XXXX after'))->toBe([])
        ->and($detector->redact('before XXXX after'))->toBe('before XXXX after');
})->with(['pii.ipv4', 'secret.aws_access_key', 'injection.ignore_instructions', 'obfuscation.invisible_chars']);

test('kinds are reported once each, in a fixed order', function () {
    $detector = new PersonalInfoDetector((new FakePersonalInfoScanner)
        ->flag('田中さん', 'pii.ja.person_name')
        ->flag('鈴木さん', 'pii.ja.person_name')
        ->flag('090-1234-5678', 'pii.ja.phone')
        ->flag('a@example.com', 'pii.email'));

    expect($detector->detect('田中さんと鈴木さんに090-1234-5678か a@example.com で'))
        ->toBe([PersonalInfoDetector::EMAIL, PersonalInfoDetector::PHONE, PersonalInfoDetector::NAME]);
});

test('text without findings has no personal information', function () {
    $detector = new PersonalInfoDetector(new FakePersonalInfoScanner);

    expect($detector->detect('人間関係に疲れてしまいました'))->toBe([])
        ->and($detector->redact('人間関係に疲れてしまいました'))->toBe('人間関係に疲れてしまいました');
});

test('it replaces personal information with placeholders and leaves the rest untouched', function () {
    app()->setLocale('ja');
    $detector = new PersonalInfoDetector((new FakePersonalInfoScanner)
        ->flag('田中さん', 'pii.ja.person_name')
        ->flag('090-1234-5678', 'pii.ja.phone')
        ->flag('株式会社サンプル商事', 'pii.ja.company')
        ->flag('東京都渋谷区神南1丁目2番3号', 'pii.ja.address')
        ->flag('a@example.com', 'pii.email'));

    expect($detector->redact('田中さんに090-1234-5678で連絡し、株式会社サンプル商事の東京都渋谷区神南1丁目2番3号へ行く。mail: a@example.com'))
        ->toBe('[氏名]に[電話番号]で連絡し、[会社名]の[住所]へ行く。mail: [メールアドレス]');
});

test('placeholders follow the current language', function () {
    app()->setLocale('en');
    $detector = new PersonalInfoDetector((new FakePersonalInfoScanner)->flag('a@example.com', 'pii.email'));

    expect($detector->redact('mail a@example.com please'))->toBe('mail [email address] please');
});

test('overlapping findings are masked once, with the placeholder of the first', function () {
    app()->setLocale('ja');
    $detector = new PersonalInfoDetector((new FakePersonalInfoScanner)
        ->flag('03-1234-5678', 'pii.phone_jp')
        ->flag('234-5678', 'pii.ja.postal_code')
        ->flag('5678です', 'pii.ja.person_name'));

    expect($detector->redact('電話は03-1234-5678です。'))->toBe('電話は[電話番号]。');
});

test('two findings on the same text are masked once', function () {
    app()->setLocale('ja');
    $scanner = new class extends FakePersonalInfoScanner
    {
        public function scan(#[SensitiveParameter] string $text): array
        {
            return [
                ['rule_id' => 'pii.phone_jp', 'start' => 0, 'end' => 13],
                ['rule_id' => 'pii.ja.phone', 'start' => 0, 'end' => 13],
            ];
        }
    };

    expect((new PersonalInfoDetector($scanner))->redact('090-1234-5678 です'))->toBe('[電話番号] です');
});

test('a failed scan is passed on instead of being read as nothing found', function () {
    $detector = new PersonalInfoDetector((new FakePersonalInfoScanner)->unavailable());

    expect(fn () => $detector->detect('a@example.com'))->toThrow(PersonalInfoDetectionUnavailable::class)
        ->and(fn () => $detector->redact('a@example.com'))->toThrow(PersonalInfoDetectionUnavailable::class);
});
