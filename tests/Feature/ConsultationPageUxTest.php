<?php

use App\Models\User;
use App\Models\ZenConsultation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;

uses(RefreshDatabase::class);

test('the home page hosts the consultation form with a privacy note and a character counter', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSeeInOrder([
            'action="'.route('zen.respond').'"',
            '<textarea',
            'aria-describedby="concern-privacy-note concern-character-count"',
            'id="concern-privacy-note"',
            'id="concern-character-count"',
            'data-requires-input',
        ], false)
        ->assertSee('maxlength="'.ZenConsultation::CONCERN_MAX_LENGTH.'"', false)
        ->assertSee('<span data-character-count>0</span> / '.ZenConsultation::CONCERN_MAX_LENGTH, false);
});

test('the old consultation form url redirects to the home page and keeps flashed messages', function () {
    $this->withSession(['_flash' => ['old' => ['consultation_rate_limited'], 'new' => []], 'consultation_rate_limited' => '別の相談に回答中です。'])
        ->get(route('zen'))
        ->assertRedirect(route('home'));

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('別の相談に回答中です。');
});

test('the consultation form offers the configured hint chips', function () {
    config(['zen.concern_hints' => ['眠れない夜が続いている']]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('data-concern-hint="眠れない夜が続いている"', false)
        ->assertDontSee('data-concern-hint="仕事に疲れてしまった"', false);
});

test('concerns longer than the shared maximum length are rejected', function () {
    $this->post(route('zen.respond'), ['concern' => str_repeat('あ', ZenConsultation::CONCERN_MAX_LENGTH + 1)])
        ->assertSessionHasErrors('concern');
});

test('the result page leads to another consultation and keeps the share url collapsed', function () {
    OpenAI::fake([
        CreateResponse::fake(['choices' => [['message' => ['content' => '静かな答えです。']]]]),
        CreateResponse::fake(['choices' => [['message' => ['content' => '{"topic":"仕事","summary":"要約です。"}']]]]),
    ]);

    $response = $this->actingAs(User::factory()->create(['purchased_credits' => 5]))
        ->post(route('zen.respond'), ['concern' => '仕事に迷っています'])
        ->assertOk();

    $response
        ->assertSeeInOrder([
            'data-share-link',
            '<details class="consultation-share-details">',
            '公開用URLを表示',
            'value=""',
            'data-share-endpoint="'.route('consultations.share-link', $response->viewData('consultationId')).'"',
            '</details>',
        ], false)
        ->assertSeeInOrder([
            '<div class="result-actions">',
            '<a class="primary-action" href="'.route('zen').'">',
            '<a class="text-link" href="'.route('consultations.index').'">相談の記録を見る</a>',
        ], false);
});

test('the saved consultation page uses the same collapsible share section', function () {
    $user = User::factory()->create();
    $consultation = $user->zenConsultations()->create(['concern' => '家族のこと', 'response' => '回答です。']);

    $this->actingAs($user)
        ->get(route('consultations.show', $consultation->id))
        ->assertOk()
        ->assertSee('<details class="consultation-share-details">', false)
        ->assertSee('data-share-link', false)
        ->assertSee("const shareUrlInput = document.querySelector('#consultation-share-url');", false);
});

test('the result page does not lead with another consultation once credits are exhausted', function () {
    OpenAI::fake([
        CreateResponse::fake(['choices' => [['message' => ['content' => '静かな答えです。']]]]),
        CreateResponse::fake(['choices' => [['message' => ['content' => '{"topic":"仕事","summary":"要約です。"}']]]]),
    ]);

    $this->actingAs(User::factory()->create())
        ->post(route('zen.respond'), ['concern' => '仕事に迷っています'])
        ->assertOk()
        ->assertSee('<a class="text-link" href="'.route('zen').'">もう一度相談する</a>', false)
        ->assertDontSee('<a class="primary-action" href="'.route('zen').'">', false);
});
