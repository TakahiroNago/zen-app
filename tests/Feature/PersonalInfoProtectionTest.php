<?php

use App\Models\User;
use App\Models\ZenConsultation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Resources\Chat;
use OpenAI\Responses\Chat\CreateResponse;

uses(RefreshDatabase::class);

function fakeConsultationResponses(): mixed
{
    return OpenAI::fake([
        CreateResponse::fake(['choices' => [['message' => ['content' => 'A calm answer.']]]]),
        CreateResponse::fake(['choices' => [['message' => ['content' => '{"topic":"仕事","summary":"要約です。"}']]]]),
    ]);
}

test('a concern with personal information is stopped before calling OpenAI', function () {
    $openAiFake = OpenAI::fake();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('zen'))
        ->post(route('zen.respond'), ['concern' => '上司の連絡先は boss@example.com です'])
        ->assertUnprocessable()
        ->assertSee('メールアドレス・電話番号・住所などの個人情報と思われる内容が含まれています。')
        ->assertSee('>上司の連絡先は boss@example.com です</textarea>', false)
        ->assertSee('<span data-character-count>'.mb_strlen('上司の連絡先は boss@example.com です').'</span>', false)
        ->assertSee('name="confirm_personal_info"', false);

    $openAiFake->assertNothingSent();
    expect($user->zenConsultations()->count())->toBe(0);
});

test('a concern with personal information is never stored in the session', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('zen.respond'), ['concern' => '同僚の田中さんの番号は 090-1234-5678 です'])
        ->assertUnprocessable();

    expect(json_encode(session()->all(), JSON_UNESCAPED_UNICODE))
        ->not->toContain('090-1234-5678')
        ->not->toContain('田中')
        ->not->toContain('[氏名]');
});

test('a concern failing validation is not flashed into the session', function () {
    $tooLongConcern = str_repeat('秘', ZenConsultation::CONCERN_MAX_LENGTH + 1);

    $this->from(route('zen'))
        ->post(route('zen.respond'), ['concern' => $tooLongConcern, 'confirm_personal_info' => '1'])
        ->assertRedirect(route('zen'))
        ->assertSessionHasErrors('concern')
        ->assertSessionHasInput('confirm_personal_info', '1')
        ->assertSessionMissing('_old_input.concern');

    expect(json_encode(session()->all(), JSON_UNESCAPED_UNICODE))->not->toContain($tooLongConcern);
});

test('a confirmed concern with personal information is sent but hidden on the shared url', function () {
    $openAiFake = fakeConsultationResponses();
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->post(route('zen.respond'), [
            'concern' => '上司の連絡先は boss@example.com です',
            'confirm_personal_info' => '1',
        ])
        ->assertOk();

    $openAiFake->assertSent(Chat::class, fn (string $method, array $parameters): bool => $method === 'create'
        && str_contains($parameters['messages'][0]['content'], '個人情報'));

    $this->get(issueShareUrl($this, $response->viewData('consultationId')))
        ->assertOk()
        ->assertDontSee('boss@example.com')
        ->assertSee('プライバシー保護のため、相談文は表示されません。')
        ->assertSee('A calm answer.');
});

test('a concern without personal information remains visible on the shared url', function () {
    fakeConsultationResponses();
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->post(route('zen.respond'), ['concern' => '仕事に迷っています'])
        ->assertOk();

    $this->get(issueShareUrl($this, $response->viewData('consultationId')))
        ->assertOk()
        ->assertSee('仕事に迷っています');
});

test('an anonymized suggestion is offered and can be sent without being hidden', function () {
    $openAiFake = fakeConsultationResponses();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('zen.respond'), ['concern' => '同僚の田中さんと上手くいきません'])
        ->assertUnprocessable()
        ->assertViewHas('anonymizedConcern', '同僚の[氏名]と上手くいきません')
        ->assertSee('この内容で相談する')
        ->assertSee('<input type="hidden" name="concern" value="同僚の[氏名]と上手くいきません">', false)
        ->assertSeeInOrder(['data-submitting-label="読み解いています..."', 'data-submitting-label="読み解いています..."'], false);

    $response = $this->actingAs($user)
        ->post(route('zen.respond'), ['concern' => '同僚の[氏名]と上手くいきません'])
        ->assertOk();

    $this->get(issueShareUrl($this, $response->viewData('consultationId')))->assertSee('同僚の[氏名]と上手くいきません');
});
