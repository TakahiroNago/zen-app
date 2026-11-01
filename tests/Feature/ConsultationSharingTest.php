<?php

use App\Models\User;
use App\Models\ZenConsultation;
use App\Services\PublicConsultationShareService;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

function shareableConsultation(User $user, array $attributes = []): ZenConsultation
{
    return $user->zenConsultations()->create([
        'concern' => '仕事に迷っています',
        'response' => 'ゆっくり考えましょう。',
        ...$attributes,
    ]);
}

function shareTokenFrom(string $shareUrl): string
{
    return basename(parse_url($shareUrl, PHP_URL_PATH));
}

test('viewing a saved consultation does not issue a public share link', function () {
    $user = User::factory()->create();
    $consultation = shareableConsultation($user);

    $this->actingAs($user)->get(route('consultations.show', $consultation->id))->assertOk();

    $consultation->refresh();
    expect($consultation->share_token)->toBeNull()
        ->and($consultation->share_token_hash)->toBeNull()
        ->and($consultation->share_expires_at)->toBeNull();
});

test('a saved consultation share link is reused, stored encrypted and never cached', function () {
    $user = User::factory()->create();
    $consultation = shareableConsultation($user);

    $this->actingAs($user);
    $shareUrl = issueShareUrl($this, $consultation->id);
    $token = shareTokenFrom($shareUrl);

    expect(issueShareUrl($this, $consultation->id))->toBe($shareUrl);

    $this->actingAs($user)->get(route('consultations.show', $consultation->id))->assertOk();
    expect(issueShareUrl($this, $consultation->id))->toBe($shareUrl);

    $storedRow = DB::table('zen_consultations')->where('id', $consultation->id)->first();
    expect($storedRow->share_token)->not->toContain($token)
        ->and($storedRow->share_token_hash)->toBe(hash('sha256', $token))
        ->and($consultation->refresh()->share_token)->toBe($token)
        ->and($consultation->toArray())->not->toHaveKeys(['share_token', 'share_token_hash'])
        ->and(Cache::has('public_consultation_share:v2:'.hash('sha256', $token)))->toBeFalse()
        ->and(Cache::has('public_consultation_share:'.hash('sha256', $token)))->toBeFalse();

    $this->get($shareUrl)
        ->assertOk()
        ->assertSee('仕事に迷っています')
        ->assertSee('ゆっくり考えましょう。')
        ->assertHeader('Cache-Control', 'no-store, private');
});

test('share links can only be issued by the owner of a visible consultation', function () {
    $owner = User::factory()->create();
    $consultation = shareableConsultation($owner);

    $this->postJson(route('consultations.share-link', $consultation->id))->assertForbidden();

    $this->actingAs(User::factory()->unverified()->create())
        ->postJson(route('consultations.share-link', $consultation->id))
        ->assertForbidden();

    $this->actingAs(User::factory()->create())
        ->postJson(route('consultations.share-link', $consultation->id))
        ->assertNotFound();

    $hiddenByPlan = shareableConsultation($owner);
    $hiddenByPlan->forceFill(['created_at' => now()->subMonths(4)])->saveQuietly();
    $this->actingAs($owner)
        ->postJson(route('consultations.share-link', $hiddenByPlan->id))
        ->assertNotFound();

    $this->postJson(route('consultations.share-link', 'session-0'))->assertNotFound();

    expect($consultation->refresh()->share_token)->toBeNull();
});

test('share links expire after thirty days and a new link can then be issued', function () {
    $user = User::factory()->create(['plan' => User::PLAN_PREMIUM]);
    $consultation = shareableConsultation($user);
    $this->actingAs($user);
    $shareUrl = issueShareUrl($this, $consultation->id);

    $this->travel(PublicConsultationShareService::EXPIRATION_DAYS)->days();
    $this->travel(-1)->minutes();
    $this->get($shareUrl)->assertOk();

    $this->travel(2)->minutes();
    $this->get($shareUrl)->assertNotFound();

    $renewedUrl = issueShareUrl($this, $consultation->id);
    expect($renewedUrl)->not->toBe($shareUrl);
    $this->get($renewedUrl)->assertOk();
    $this->get($shareUrl)->assertNotFound();
});

test('a share token that can no longer be decrypted is replaced by a new link', function () {
    $user = User::factory()->create();
    $consultation = shareableConsultation($user);
    $this->actingAs($user);
    $shareUrl = issueShareUrl($this, $consultation->id);

    $otherKeyEncrypter = new Encrypter(random_bytes(32), config('app.cipher'));
    DB::table('zen_consultations')->where('id', $consultation->id)
        ->update(['share_token' => $otherKeyEncrypter->encryptString(shareTokenFrom($shareUrl))]);

    $renewedUrl = issueShareUrl($this, $consultation->id);

    expect($renewedUrl)->not->toBe($shareUrl)
        ->and($consultation->refresh()->share_token)->toBe(shareTokenFrom($renewedUrl));
    $this->get($renewedUrl)->assertOk();
    $this->get($shareUrl)->assertNotFound();
});

test('concurrent share requests for a saved consultation return the same link', function () {
    $user = User::factory()->create();
    $consultation = shareableConsultation($user);
    $staleCopy = ZenConsultation::findOrFail($consultation->id);
    $shareService = app(PublicConsultationShareService::class);

    $firstToken = $shareService->shareSavedConsultation($consultation);
    $racingToken = $shareService->shareSavedConsultation($staleCopy);

    expect($racingToken)->toBe($firstToken)
        ->and($consultation->refresh()->share_token)->toBe($firstToken);
    $this->get(route('consultations.share', ['token' => $firstToken]))->assertOk();
});

test('share requests of one session are processed one at a time', function () {
    $route = Route::getRoutes()->getByName('consultations.share-link');

    expect($route->locksFor())->toBe(10)
        ->and($route->waitsFor())->toBe(5);
});

test('a guest session share link is cached only in encrypted form', function () {
    $this->withSession(['zen_consultations' => [[
        'concern' => 'ゲストの秘密の相談',
        'response' => 'ゲストへの回答',
        'image_number' => 3,
    ]]]);

    $shareUrl = issueShareUrl($this, 'session-0');
    $token = shareTokenFrom($shareUrl);
    expect(issueShareUrl($this, 'session-0'))->toBe($shareUrl)
        ->and(session('zen_consultations.0.share_token'))->toBe($token);

    $cachedPayload = Cache::get('public_consultation_share:v2:'.hash('sha256', $token));
    expect($cachedPayload)->toBeString()
        ->not->toContain('ゲスト')
        ->not->toContain(base64_encode('ゲストの秘密の相談'));

    $this->get($shareUrl)
        ->assertOk()
        ->assertSee('ゲストの秘密の相談')
        ->assertSee('ゲストへの回答');

    $this->travel(PublicConsultationShareService::EXPIRATION_DAYS)->days();
    $this->travel(1)->minutes();
    $this->get($shareUrl)->assertNotFound();
});

test('a guest session share link hides a concern containing personal information', function () {
    $this->withSession(['zen_consultations' => [['concern' => '連絡先は boss@example.com', 'response' => '回答']]]);

    $this->get(issueShareUrl($this, 'session-0'))
        ->assertOk()
        ->assertDontSee('boss@example.com')
        ->assertSee('プライバシー保護のため、相談文は表示されません。');
});

test('deleting a guest session consultation revokes its share link', function () {
    $this->withSession(['zen_consultations' => [['concern' => 'a', 'response' => 'b']]]);
    $shareUrl = issueShareUrl($this, 'session-0');

    $this->delete(route('consultations.destroy', 'session-0'))->assertRedirect(route('consultations.index'));

    $this->get($shareUrl)->assertNotFound();
});

test('deleting all consultations revokes guest and saved share links', function () {
    $user = User::factory()->create();
    $consultation = shareableConsultation($user);
    $this->withSession(['zen_consultations' => [['concern' => 'a', 'response' => 'b']]]);
    $guestShareUrl = issueShareUrl($this, 'session-0');
    $this->actingAs($user);
    $savedShareUrl = issueShareUrl($this, $consultation->id);

    $this->delete(route('consultations.destroy-all'))->assertRedirect(route('consultations.index'));

    $this->get($guestShareUrl)->assertNotFound();
    $this->get($savedShareUrl)->assertNotFound();
});

test('a guest share link keeps working after the guest logs in and can then be revoked', function () {
    $user = User::factory()->create();
    $this->withSession(['zen_consultations' => [['concern' => 'ログイン前の相談', 'response' => '回答']]]);
    $shareUrl = issueShareUrl($this, 'session-0');
    $tokenHash = hash('sha256', shareTokenFrom($shareUrl));

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertRedirect();

    $consultation = $user->zenConsultations()->sole();
    expect($consultation->share_token)->toBe(shareTokenFrom($shareUrl))
        ->and(Cache::has('public_consultation_share:v2:'.$tokenHash))->toBeFalse()
        ->and(issueShareUrl($this, $consultation->id))->toBe($shareUrl);
    $this->get($shareUrl)->assertOk()->assertSee('ログイン前の相談');

    $this->delete(route('consultations.destroy', $consultation->id));
    $this->get($shareUrl)->assertNotFound();
});

test('legacy plaintext share links stay readable and are revoked with their consultation', function () {
    $user = User::factory()->create();
    $legacyToken = str_repeat('a', 64);
    $legacyHash = hash('sha256', $legacyToken);
    Cache::put('public_consultation_share:'.$legacyHash, [
        'concern' => '以前の相談',
        'response' => '以前の回答',
        'concern_hidden' => false,
        'image_number' => 2,
    ], now()->addDays(30));
    $consultation = shareableConsultation($user, ['share_token_hash' => $legacyHash]);

    $this->get(route('consultations.share', ['token' => $legacyToken]))
        ->assertOk()
        ->assertSee('以前の相談');

    $this->actingAs($user)->get(route('consultations.show', $consultation->id))->assertOk();
    $this->get(route('consultations.share', ['token' => $legacyToken]))->assertOk();

    $this->actingAs($user)->delete(route('consultations.destroy', $consultation->id));
    $this->get(route('consultations.share', ['token' => $legacyToken]))->assertNotFound();
});

test('issuing a new link for a legacy consultation replaces the legacy link', function () {
    $user = User::factory()->create();
    $legacyToken = str_repeat('b', 64);
    $legacyHash = hash('sha256', $legacyToken);
    Cache::put('public_consultation_share:'.$legacyHash, ['concern' => '以前', 'response' => '回答', 'concern_hidden' => false], now()->addDays(30));
    $consultation = shareableConsultation($user, ['share_token_hash' => $legacyHash]);

    $this->actingAs($user);
    $shareUrl = issueShareUrl($this, $consultation->id);

    expect(shareTokenFrom($shareUrl))->not->toBe($legacyToken);
    $this->get($shareUrl)->assertOk();
    $this->get(route('consultations.share', ['token' => $legacyToken]))->assertNotFound();
});

test('unknown share tokens are not found', function () {
    $this->get(route('consultations.share', ['token' => str_repeat('0', 64)]))->assertNotFound();
});
