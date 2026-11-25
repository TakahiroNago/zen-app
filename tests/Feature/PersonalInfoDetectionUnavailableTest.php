<?php

use App\Models\User;
use App\Services\ConsultationHighlights;
use App\Services\PublicConsultationShareService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;

uses(RefreshDatabase::class);

/*
 * While llm-inspect-server cannot be reached, nothing that has not been checked is sent to the
 * model or made public.
 */

test('a concern that cannot be checked is not sent to OpenAI and costs no credit', function () {
    $openAiFake = OpenAI::fake();
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('home'))->assertOk();
    $creditsBefore = [$user->refresh()->daily_credits, $user->purchased_credits];
    personalInfoScanner()->unavailable();

    $this->actingAs($user)
        ->post(route('zen.respond'), ['concern' => '仕事に迷っています'])
        ->assertStatus(503)
        ->assertSee('現在、相談内容の確認ができないため、相談を受け付けられません。')
        ->assertSee('>仕事に迷っています</textarea>', false);

    $openAiFake->assertNothingSent();
    $user->refresh();
    expect($user->zenConsultations()->count())->toBe(0)
        ->and([$user->daily_credits, $user->purchased_credits])->toBe($creditsBefore);
});

test('confirming personal information does not get an unchecked concern through', function () {
    $openAiFake = OpenAI::fake();
    personalInfoScanner()->unavailable();

    $this->actingAs(User::factory()->create())
        ->post(route('zen.respond'), ['concern' => '連絡先は boss@example.com', 'confirm_personal_info' => '1'])
        ->assertStatus(503);

    $openAiFake->assertNothingSent();
});

test('a concern that cannot be checked is not stored in the session', function () {
    personalInfoScanner()->unavailable();

    $this->post(route('zen.respond'), ['concern' => '連絡先は boss@example.com'])->assertStatus(503);

    expect(json_encode(session()->all(), JSON_UNESCAPED_UNICODE))->not->toContain('boss@example.com');
});

test('consultations work again once the check is back', function () {
    OpenAI::fake([
        CreateResponse::fake(['choices' => [['message' => ['content' => 'A calm answer.']]]]),
        CreateResponse::fake(['choices' => [['message' => ['content' => '{"topic":"仕事","summary":"要約です。"}']]]]),
    ]);
    $user = User::factory()->create();

    personalInfoScanner()->unavailable();
    $this->actingAs($user)->post(route('zen.respond'), ['concern' => '仕事に迷っています'])->assertStatus(503);

    personalInfoScanner()->unavailable(false);
    $this->actingAs($user)->post(route('zen.respond'), ['concern' => '仕事に迷っています'])->assertOk()->assertSee('A calm answer.');
});

test('a guest share link is not issued while the concern cannot be checked', function () {
    $this->withSession(['zen_consultations' => [['concern' => '仕事に迷っています', 'response' => '回答']]]);
    personalInfoScanner()->unavailable();

    $this->postJson(route('consultations.share-link', 'session-0'))
        ->assertStatus(503)
        ->assertJsonMissingPath('url');

    expect(session('zen_consultations.0'))->not->toHaveKey('share_token');
});

test('an existing share link of a saved consultation stays up with the concern hidden', function () {
    $consultation = User::factory()->create()->zenConsultations()->create([
        'concern' => '仕事に迷っています',
        'response' => '回答です。',
    ]);
    $token = app(PublicConsultationShareService::class)->shareSavedConsultation($consultation);

    $this->get(route('consultations.share', ['token' => $token]))->assertOk()->assertSee('仕事に迷っています');

    personalInfoScanner()->unavailable();

    $this->get(route('consultations.share', ['token' => $token]))
        ->assertOk()
        ->assertDontSee('仕事に迷っています')
        ->assertSee('プライバシー保護のため、相談文は表示されません。')
        ->assertSee('回答です。');
});

test('the one thing for today is left out while it cannot be checked', function () {
    $stored = ['response' => '回答です。', 'daily_action' => '好きな飲み物をゆっくり味わう。'];
    $highlights = app(ConsultationHighlights::class);

    expect($highlights->fromStored($stored)['daily_action'])->toBe('好きな飲み物をゆっくり味わう。');

    personalInfoScanner()->unavailable();

    expect($highlights->fromStored($stored)['daily_action'])->toBeNull();
});
