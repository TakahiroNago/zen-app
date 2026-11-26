<?php

use App\Services\LlmInspectScanner;
use App\Services\PersonalInfoDetector;
use App\Services\PersonalInfoScanner;

/*
 * Runs against a real llm-inspect-server started with `--pack personal-ja`, and is skipped unless
 * LLM_INSPECT_TEST_URL points at one:
 *
 *   llm-inspect-server --bind 127.0.0.1:8787 --pack personal-ja
 *   LLM_INSPECT_TEST_URL=http://127.0.0.1:8787 vendor/bin/pest tests/Integration
 *
 * It checks the agreement between the two sides: that the rule ids this application knows are the
 * ones the server reports, for the kinds of text people write here. The rules themselves are
 * tested in llm-inspect.
 */

beforeEach(function () {
    $url = env('LLM_INSPECT_TEST_URL');
    if (! is_string($url) || $url === '') {
        $this->markTestSkipped('LLM_INSPECT_TEST_URL is not set.');
    }

    config([
        'services.llm_inspect.url' => $url,
        'services.llm_inspect.token' => env('LLM_INSPECT_TEST_TOKEN'),
    ]);
    $this->app->forgetInstance(PersonalInfoScanner::class);
    $this->app->offsetUnset(PersonalInfoScanner::class);
});

test('the application resolves the real scanner outside of tests', function () {
    expect(app(PersonalInfoScanner::class))->toBeInstanceOf(LlmInspectScanner::class);
});

test('the server reports each kind of personal information under a rule id known here', function (string $text, string $type) {
    expect(app(PersonalInfoDetector::class)->detect($text))->toContain($type);
})->with([
    'email' => ['連絡は taro.yamada@example.com まで', PersonalInfoDetector::EMAIL],
    'mobile phone' => ['090-1234-5678に電話が来ます', PersonalInfoDetector::PHONE],
    'full-width phone' => ['０９０－１２３４－５６７８', PersonalInfoDetector::PHONE],
    'phone without separators' => ['09012345678です', PersonalInfoDetector::PHONE],
    'landline' => ['03-1234-5678', PersonalInfoDetector::PHONE],
    'postal code' => ['〒150-0001 に住んでいます', PersonalInfoDetector::POSTAL_CODE],
    'url' => ['https://example.com/me を見て', PersonalInfoDetector::URL],
    'name' => ['同僚の田中さんと上手くいきません', PersonalInfoDetector::NAME],
    'name with president title' => ['山田社長に相談しました', PersonalInfoDetector::NAME],
    'name with department manager title' => ['佐藤部長に確認してください', PersonalInfoDetector::NAME],
    'name with section manager title' => ['鈴木課長が対応します', PersonalInfoDetector::NAME],
    'name with senior managing director title' => ['高橋専務から連絡がありました', PersonalInfoDetector::NAME],
    'company' => ['株式会社サンプル商事で働いています', PersonalInfoDetector::COMPANY],
    'company suffix' => ['山田商事株式会社に勤めています', PersonalInfoDetector::COMPANY],
    'address' => ['東京都渋谷区神南1丁目2番3号に住んでいます', PersonalInfoDetector::ADDRESS],
    'credit card' => ['4111 1111 1111 1111', PersonalInfoDetector::CREDIT_CARD],
]);

test('ordinary concerns are not flagged', function (string $text) {
    expect(app(PersonalInfoDetector::class)->detect($text))->toBe([]);
})->with([
    'age and salary' => ['35歳で年収500万円ですが転職すべきでしょうか'],
    'year' => ['2026年に新しい一歩を踏み出したい'],
    'role words' => ['上司さんやお客様、部長さんとの関係が不安です'],
    'prefecture only' => ['東京都に引っ越すべきか迷っています'],
    'plain' => ['人間関係に疲れてしまいました'],
    'english' => ['I feel stuck at work and do not know what to do next.'],
]);

test('personal information is replaced with placeholders, and nothing is left to find', function () {
    app()->setLocale('ja');
    $detector = app(PersonalInfoDetector::class);

    $redacted = $detector->redact('田中さんに090-1234-5678で連絡し、株式会社サンプル商事の東京都渋谷区神南1丁目2番3号へ行く。mail: a@example.com');

    expect($redacted)->toBe('[氏名]に[電話番号]で連絡し、[会社名]の[住所]へ行く。mail: [メールアドレス]')
        ->and($detector->detect($redacted))->toBe([])
        ->and($detector->redact('山田社長に相談しました'))->toBe('[氏名]に相談しました')
        ->and($detector->redact('090-1111-2222,080-3333-4444'))->toBe('[電話番号],[電話番号]');
});

test('byte offsets from the server line up with multi-byte text', function () {
    app()->setLocale('ja');

    expect(app(PersonalInfoDetector::class)->redact('😀 連絡は　ｔａｒｏ＠ｅｘａｍｐｌｅ．ｃｏｍ　まで'))
        ->toBe('😀 連絡は　[メールアドレス]　まで');
});
