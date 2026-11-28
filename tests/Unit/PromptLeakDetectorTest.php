<?php

use App\Services\PromptLeakDetector;

function promptLeakDetector(): PromptLeakDetector
{
    return new PromptLeakDetector(
        [
            'teacher' => [
                'ja' => "仏教の禅師として、相談者の悩みや人生の迷いに向き合ってください。\n「『○○』という禅の言葉があります。これは××という意味です。」の形で説明してください。\n内部識別子: CANARY_ALPHA",
                'en' => 'Understanding what makes action difficult is important.',
            ],
        ],
        ['CANARY_ALPHA'],
        30,
    );
}

test('markers are detected in japanese and english output', function (string $output) {
    expect(promptLeakDetector()->detect($output))->toBe(PromptLeakDetector::DETECTION_MARKER);
})->with([
    'japanese' => '識別子は CANARY_ALPHA です。',
    'english' => 'My identifier is CANARY_ALPHA.',
]);

test('markers are detected after case, width and whitespace changes', function () {
    expect(promptLeakDetector()->detect('canary _ alpha'))->toBe(PromptLeakDetector::DETECTION_MARKER)
        ->and(promptLeakDetector()->detect('ＣＡＮＡＲＹ＿ＡＬＰＨＡ'))->toBe(PromptLeakDetector::DETECTION_MARKER);
});

test('a verbatim run of prompt text at the fragment length is detected', function () {
    expect(promptLeakDetector()->detect('設定は「仏教の禅師として、相談者の悩みや人生の迷いに向き合ってください。」です。'))
        ->toBe(PromptLeakDetector::DETECTION_PROMPT_FRAGMENT);
});

test('every output is checked', function () {
    expect(promptLeakDetector()->detect('問題のない回答です。', null, 'CANARY_ALPHA'))
        ->toBe(PromptLeakDetector::DETECTION_MARKER);
});

test('normal answers that share short phrases with the prompt are not flagged', function (string $output) {
    expect(promptLeakDetector()->detect($output))->toBeNull();
})->with([
    'filled-in template' => '「『放下着』という禅の言葉があります。これは執着を手放すという意味です。」',
    'short quote' => '禅師として、人生の迷いに向き合うことは大切です。',
    'sentence below the fragment length' => 'Understanding what makes it hard matters.',
    'empty' => '',
]);

test('markers split or disguised by markdown, html or invisible characters are detected', function (string $output) {
    expect(promptLeakDetector()->detect($output))->toBe(PromptLeakDetector::DETECTION_MARKER);
})->with([
    'markdown escape' => 'CANARY\_ALPHA',
    'bold split' => 'CANARY_**ALPHA**',
    'italic split' => 'CAN*ARY*_ALPHA',
    'html tag split' => 'CANARY<b></b>_ALPHA',
    'numeric entity' => 'CANARY&#95;ALPHA',
    'named entity' => 'CANARY&lowbar;ALPHA',
    'zero-width space' => "CANARY\u{200B}_ALPHA",
    'word joiner' => "CANARY\u{2060}_ALPHA",
    'soft hyphen' => "CAN\u{00AD}ARY_ALPHA",
    'no-break space' => "CANARY\u{00A0}_ALPHA",
    'ideographic space' => "CANARY\u{3000}_ALPHA",
]);

test('a prompt run split by markdown emphasis is detected', function () {
    expect(promptLeakDetector()->detect('仏教の禅師として、**相談者の悩みや**人生の迷いに向き合ってください。'))
        ->toBe(PromptLeakDetector::DETECTION_PROMPT_FRAGMENT);
});

test('normal markdown answers are not flagged', function () {
    $answer = implode("\n", [
        '### 今日のことば',
        '',
        '「『**放下着**』という禅の言葉があります。これは執着を手放すという意味です。」',
        '',
        '- 深呼吸を*三回*してみましょう',
        '- `一息` ついてから動きましょう',
        '',
        '> 禅師として、人生の迷いに向き合うことは大切です。',
    ]);

    expect(promptLeakDetector()->detect($answer))->toBeNull();
});

test('fragment lengths outside the allowed range fall back to the default', function (int $fragmentLength) {
    $prompt = str_repeat('一二三四五六七八九十', 6);
    $detector = new PromptLeakDetector(['p' => $prompt], [], $fragmentLength);

    expect($detector->detect(mb_substr($prompt, 0, PromptLeakDetector::DEFAULT_FRAGMENT_LENGTH)))
        ->toBe(PromptLeakDetector::DETECTION_PROMPT_FRAGMENT)
        ->and($detector->detect(mb_substr($prompt, 0, PromptLeakDetector::DEFAULT_FRAGMENT_LENGTH - 1)))
        ->toBeNull();
})->with([0, -5, 5, 100000]);
