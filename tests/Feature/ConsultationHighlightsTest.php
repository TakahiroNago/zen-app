<?php

use App\Models\User;
use App\Models\ZenConsultation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;

uses(RefreshDatabase::class);

const HIGHLIGHT_ANSWER = "『日日是好日』という禅の言葉があります。\n\n今日は、好きな飲み物をゆっくり味わってみましょう。";

/**
 * @param  array<string, mixed>  $metadata
 */
function fakeConsultationWithMetadata(array $metadata, string $answer = HIGHLIGHT_ANSWER): void
{
    OpenAI::fake([
        CreateResponse::fake(['choices' => [['message' => ['content' => $answer]]]]),
        CreateResponse::fake(['choices' => [['message' => ['content' => json_encode($metadata, JSON_UNESCAPED_UNICODE)]]]]),
    ]);
}

/**
 * @return array<string, mixed>
 */
function highlightMetadata(array $overrides = []): array
{
    return [
        'topic' => '自分自身',
        'summary' => '日々の小さな味わいに目を向ける助言です。',
        'zengo' => ['text' => '日日是好日', 'reading' => 'にちにち これ こうじつ', 'meaning' => '良い日も悪い日も、その日をそのまま味わう'],
        'daily_action' => '好きな飲み物をひとつ選び、ゆっくり味わってみる。',
        ...$overrides,
    ];
}

test('the zen saying and the one thing for today are saved and shown on the result, detail and shared pages', function () {
    fakeConsultationWithMetadata(highlightMetadata());
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->post(route('zen.respond'), ['concern' => '毎日を楽しみたいです'])
        ->assertOk()
        ->assertSeeInOrder(['今日の禅語', '日日是好日', 'にちにち これ こうじつ', '良い日も悪い日も、その日をそのまま味わう', 'id="consultation-copy"', '今日のひとつ', '好きな飲み物をひとつ選び、ゆっくり味わってみる。'], false);

    expect($user->zenConsultations()->sole()->only(['zengo', 'zengo_reading', 'zengo_meaning', 'daily_action']))->toBe([
        'zengo' => '日日是好日',
        'zengo_reading' => 'にちにち これ こうじつ',
        'zengo_meaning' => '良い日も悪い日も、その日をそのまま味わう',
        'daily_action' => '好きな飲み物をひとつ選び、ゆっくり味わってみる。',
    ]);

    $consultationId = $response->viewData('consultationId');
    $this->get(route('consultations.show', $consultationId))
        ->assertOk()
        ->assertSee('日日是好日')
        ->assertSee('好きな飲み物をひとつ選び、ゆっくり味わってみる。');

    $this->get(issueShareUrl($this, $consultationId))
        ->assertOk()
        ->assertSee('日日是好日')
        ->assertSee('好きな飲み物をひとつ選び、ゆっくり味わってみる。');
});

test('a reading the answer wrote in parentheses after the saying is removed from the saying', function (string $zengoText) {
    fakeConsultationWithMetadata(
        highlightMetadata(['zengo' => ['text' => $zengoText, 'reading' => 'へいじょうしん これどう', 'meaning' => '迷いや焦りを含む心も、扱い方で道になる']]),
        '『平常心是道（へいじょうしんこれどう）』という禅の言葉があります。

今日は、ひと呼吸おいてから返事をしてみましょう。',
    );
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('zen.respond'), ['concern' => '焦ってしまいます'])
        ->assertOk()
        ->assertSeeInOrder(['今日の禅語', '平常心是道</blockquote>', 'へいじょうしん これどう'], false)
        ->assertDontSee('（へいじょうしんこれどう）</blockquote>', false);

    expect($user->zenConsultations()->sole())
        ->zengo->toBe('平常心是道')
        ->zengo_reading->toBe('へいじょうしん これどう');
})->with([
    'full-width parentheses' => ['平常心是道（へいじょうしんこれどう）'],
    'half-width parentheses' => ['平常心是道 (へいじょうしんこれどう)'],
]);

test('ungrounded or malformed highlights are dropped without failing the consultation', function (array $overrides, bool $showsZengo, bool $showsDailyAction) {
    fakeConsultationWithMetadata(highlightMetadata($overrides));
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->post(route('zen.respond'), ['concern' => '毎日を楽しみたいです'])
        ->assertOk();

    $showsZengo ? $response->assertSee('今日の禅語') : $response->assertDontSee('今日の禅語');
    $showsDailyAction ? $response->assertSee('今日のひとつ') : $response->assertDontSee('今日のひとつ');

    $consultation = $user->zenConsultations()->sole();
    expect($consultation->zengo !== null)->toBe($showsZengo)
        ->and($consultation->daily_action !== null)->toBe($showsDailyAction)
        ->and($consultation->topic)->toBe('自分自身');
})->with([
    'a saying the answer never introduces' => [['zengo' => ['text' => '行雲流水', 'reading' => 'こううん りゅうすい', 'meaning' => '執着せずに流れに任せる']], false, true],
    'a reading that is not kana' => [['zengo' => ['text' => '日日是好日', 'reading' => 'nichinichi kore koujitsu', 'meaning' => '毎日が好い日']], false, true],
    'no saying in the answer' => [['zengo' => null], false, true],
    'an action that is too long' => [['daily_action' => str_repeat('あ', 101)], true, false],
    'an action with personal information' => [['daily_action' => 'boss@example.com に連絡してみる。'], true, false],
    'no highlights at all' => [['zengo' => null, 'daily_action' => null], false, false],
]);

test('English consultations show only the one thing for today', function () {
    fakeConsultationWithMetadata(
        highlightMetadata(['daily_action' => 'Pick one drink you enjoy and savor it slowly.']),
        'There is a Zen saying, 日日是好日. Today, savor a drink you enjoy.',
    );
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('zen.respond', ['locale' => 'en']), ['concern' => 'How can I enjoy each day?'])
        ->assertOk()
        ->assertSee('One thing for today')
        ->assertSee('Pick one drink you enjoy and savor it slowly.')
        ->assertDontSee("Today's Zen saying");

    expect($user->zenConsultations()->sole()->zengo)->toBeNull();
});

test('premium consultations extract the highlights with the summary', function () {
    OpenAI::fake([
        CreateResponse::fake(['choices' => [['message' => ['content' => '自分自身']]]]),
        CreateResponse::fake(['choices' => [['message' => ['content' => HIGHLIGHT_ANSWER]]]]),
        CreateResponse::fake(['choices' => [['message' => ['content' => json_encode(
            array_diff_key(highlightMetadata(), ['topic' => true]),
            JSON_UNESCAPED_UNICODE,
        )]]]]),
    ]);
    $user = User::factory()->create();
    $user->forceFill(['plan' => User::PLAN_PREMIUM])->save();

    $this->actingAs($user)
        ->post(route('zen.respond'), ['concern' => '毎日を楽しみたいです'])
        ->assertOk()
        ->assertSee('日日是好日')
        ->assertSee('今日のひとつ');

    expect($user->zenConsultations()->sole())
        ->zengo->toBe('日日是好日')
        ->daily_action->toBe('好きな飲み物をひとつ選び、ゆっくり味わってみる。');
});

test('guest highlights stay in the session and are saved when the guest registers', function () {
    fakeConsultationWithMetadata(highlightMetadata());

    $this->post(route('zen.respond'), ['concern' => '毎日を楽しみたいです'])
        ->assertOk()
        ->assertSee('日日是好日');

    $this->get(route('consultations.show', 'session-0'))
        ->assertOk()
        ->assertSee('日日是好日')
        ->assertSee('好きな飲み物をひとつ選び、ゆっくり味わってみる。');

    $this->post(route('register.store'), [
        'name' => '山田 花子',
        'email' => 'hanako@example.com',
        'password' => 'a-long-enough-password',
        'password_confirmation' => 'a-long-enough-password',
    ]);

    expect(ZenConsultation::sole())
        ->zengo->toBe('日日是好日')
        ->zengo_reading->toBe('にちにち これ こうじつ')
        ->daily_action->toBe('好きな飲み物をひとつ選び、ゆっくり味わってみる。');
});
