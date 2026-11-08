<?php

use App\Models\User;
use App\Services\ConsultationCreditService;
use App\Services\ConsultationUsageLimiter;
use App\Services\GuestTrialCreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Resources\Chat;
use OpenAI\Responses\Chat\CreateResponse;

uses(RefreshDatabase::class);

/**
 * @return list<CreateResponse>
 */
function fakeStandardReadingResponses(int $readings): array
{
    $responses = [];
    foreach (range(1, $readings) as $index) {
        $responses[] = CreateResponse::fake(['choices' => [['message' => ['content' => "Reading {$index}."]]]]);
        $responses[] = CreateResponse::fake(['choices' => [['message' => ['content' => '{"topic":"その他","summary":"テスト要約"}']]]]);
    }

    return $responses;
}

test('consultation requests are throttled per IP address with 429 and Retry-After across accounts', function () {
    OpenAI::fake();
    $firstMember = User::factory()->create();
    $secondMember = User::factory()->create();

    foreach (range(1, 10) as $index) {
        $this->actingAs($index % 2 === 0 ? $firstMember : $secondMember)
            ->withServerVariables(['REMOTE_ADDR' => '192.0.2.40'])
            ->post(route('zen.respond'), ['concern' => ''])
            ->assertSessionHasErrors('concern');
    }

    $this->actingAs($firstMember)
        ->withServerVariables(['REMOTE_ADDR' => '192.0.2.40'])
        ->post(route('zen.respond'), ['concern' => ''])
        ->assertStatus(429)
        ->assertHeader('Retry-After');

    $this->actingAs(User::factory()->create())
        ->withServerVariables(['REMOTE_ADDR' => '192.0.2.41'])
        ->post(route('zen.respond'), ['concern' => ''])
        ->assertSessionHasErrors('concern');

    $this->travel(61)->seconds();
    $this->actingAs($firstMember)
        ->withServerVariables(['REMOTE_ADDR' => '192.0.2.40'])
        ->post(route('zen.respond'), ['concern' => ''])
        ->assertSessionHasErrors('concern');

    OpenAI::assertNothingSent();
});

test('suspended members cannot start a consultation', function () {
    OpenAI::fake();
    $member = User::factory()->inactive()->create();

    $this->actingAs($member)
        ->post(route('zen.respond'), ['concern' => 'Can I still consult?'])
        ->assertRedirect(route('login'));

    $this->assertGuest();
    OpenAI::assertNothingSent();
});

test('members are blocked for an hour after five failed readings and keep their credit', function () {
    OpenAI::fake([
        ...array_fill(0, 5, new RuntimeException('OpenAI is unavailable.')),
        ...fakeStandardReadingResponses(1),
    ]);
    $member = User::factory()->create();

    foreach (range(1, 5) as $index) {
        $this->actingAs($member)
            ->post(route('zen.respond'), ['concern' => "Attempt {$index}?"])
            ->assertServerError();
    }
    expect(app(ConsultationCreditService::class)->balance($member)['total'])->toBe(1);

    $this->post(route('zen.respond'), ['concern' => 'Sixth attempt?'])
        ->assertRedirect(route('home'))
        ->assertSessionHas('consultation_rate_limited', '相談の処理に繰り返し失敗したため、一時的に相談を制限しています。1時間ほど時間をおいてから再度お試しください。');
    expect(app(ConsultationCreditService::class)->balance($member)['total'])->toBe(1);

    $this->travel(61)->minutes();
    $this->post(route('zen.respond'), ['concern' => 'After an hour?'])->assertOk();

    expect(app(ConsultationCreditService::class)->balance($member)['total'])->toBe(0);
});

test('one member reaching the failure limit does not affect other members', function () {
    $blockedMember = User::factory()->create();
    foreach (range(1, ConsultationUsageLimiter::MEMBER_HOURLY_FAILURE_LIMIT) as $unused) {
        app(ConsultationUsageLimiter::class)->recordMemberFailure($blockedMember);
    }
    OpenAI::fake(fakeStandardReadingResponses(1));

    $this->actingAs(User::factory()->create())
        ->post(route('zen.respond'), ['concern' => 'May I consult?'])
        ->assertOk();
});

test('the application-wide limit stops consultations without spending credits and logs once', function (string $window) {
    config(["zen.global_consultation_limits.{$window}" => 2]);
    Log::spy();
    OpenAI::fake(fakeStandardReadingResponses(3));

    foreach (['192.0.2.50', '192.0.2.51'] as $index => $ipAddress) {
        $this->flushSession();
        $this->withServerVariables(['REMOTE_ADDR' => $ipAddress])
            ->withCookie('guest_trial_device', str_repeat((string) $index, 64))
            ->post(route('zen.respond'), ['concern' => 'Question?'])
            ->assertOk();
    }

    $member = User::factory()->create();
    foreach (range(1, 2) as $unused) {
        $this->actingAs($member)
            ->withServerVariables(['REMOTE_ADDR' => '192.0.2.52'])
            ->post(route('zen.respond'), ['concern' => 'One more?'])
            ->assertRedirect(route('home'))
            ->assertSessionHas('consultation_rate_limited', '現在、相談の受付を一時的に停止しています。しばらく時間をおいてから再度お試しください。');
    }
    expect(app(ConsultationCreditService::class)->balance($member)['total'])->toBe(1);
    Log::shouldHaveReceived('warning')->once()->withArgs(
        fn (string $message, array $context): bool => $context === ['window' => $window, 'limit' => 2],
    );

    $this->travelTo($window === 'hourly' ? now()->addHour()->startOfHour() : now()->addDay()->startOfDay()->addHour());
    $this->actingAs($member)
        ->withServerVariables(['REMOTE_ADDR' => '192.0.2.52'])
        ->post(route('zen.respond'), ['concern' => 'Next window?'])
        ->assertOk();
})->with(['hourly', 'daily']);

test('the emergency stop blocks every consultation before any credit or AI call is used', function () {
    config(['zen.consultations_enabled' => false]);
    OpenAI::fake();
    $member = User::factory()->create();

    $this->actingAs($member)
        ->post(route('zen.respond'), ['concern' => 'Are you open?'])
        ->assertRedirect(route('home'))
        ->assertSessionHas('consultation_rate_limited');

    expect(app(ConsultationCreditService::class)->balance($member)['total'])->toBe(1);
    OpenAI::assertNothingSent();
});

test('a guest whose device has no trial left does not use up the IP address allowance', function () {
    $deviceToken = str_repeat('c', 64);
    app(GuestTrialCreditService::class)->consume($deviceToken);
    OpenAI::fake();

    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.60'])
        ->withCookie('guest_trial_device', $deviceToken)
        ->post(route('zen.respond'), ['concern' => 'Again?'])
        ->assertRedirect(route('home'))
        ->assertSessionHas('credit_exhausted');

    $guestIpRateLimitKey = 'guest-ip-consultation:'.hash_hmac('sha256', '192.0.2.60', config('app.key'));
    expect(RateLimiter::attempts($guestIpRateLimitKey))->toBe(0);
});

test('guest IP reservations never exceed the daily limit', function () {
    $usageLimiter = app(ConsultationUsageLimiter::class);
    $reservations = [];
    foreach (range(1, ConsultationUsageLimiter::GUEST_IP_DAILY_LIMIT + 3) as $unused) {
        $reservations[] = $usageLimiter->reserveGuestIpTrial('192.0.2.70');
    }

    expect(array_filter($reservations))->toHaveCount(ConsultationUsageLimiter::GUEST_IP_DAILY_LIMIT)
        ->and(RateLimiter::attempts('guest-ip-consultation:'.hash_hmac('sha256', '192.0.2.70', config('app.key'))))
        ->toBe(ConsultationUsageLimiter::GUEST_IP_DAILY_LIMIT);
});

test('registrations are throttled per IP address', function () {
    foreach (range(1, 10) as $unused) {
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.80'])
            ->post(route('register.store'), [])
            ->assertSessionHasErrors();
    }

    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.80'])
        ->post(route('register.store'), [])
        ->assertStatus(429);
});

test('the output token cap is sent to every AI call only when configured', function (?int $maxCompletionTokens) {
    config(['zen.openai_max_completion_tokens' => $maxCompletionTokens]);
    OpenAI::fake(fakeStandardReadingResponses(1));

    $this->actingAs(User::factory()->create())
        ->post(route('zen.respond'), ['concern' => 'How is my work?'])
        ->assertOk();

    OpenAI::assertSent(Chat::class, 2);
    OpenAI::assertNotSent(Chat::class, fn (string $method, array $parameters): bool => ($parameters['max_completion_tokens'] ?? null) !== $maxCompletionTokens);
})->with([
    'configured' => [2000],
    'not configured' => [null],
]);
