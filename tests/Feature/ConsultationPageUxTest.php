<?php

use App\Models\User;
use App\Models\ZenConsultation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;

uses(RefreshDatabase::class);

test('the consultation form shows the privacy note before the input and a character counter', function () {
    $this->get(route('zen'))
        ->assertOk()
        ->assertSeeInOrder([
            'id="concern-privacy-note"',
            '<textarea',
            'aria-describedby="concern-privacy-note concern-character-count"',
            'id="concern-character-count"',
        ], false)
        ->assertSee('maxlength="'.ZenConsultation::CONCERN_MAX_LENGTH.'"', false)
        ->assertSee('<span data-character-count>0</span> / '.ZenConsultation::CONCERN_MAX_LENGTH, false);
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
            'value="'.$response->viewData('shareUrl').'"',
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
