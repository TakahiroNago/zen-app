<?php

use App\Models\User;
use App\Notifications\AccountActivityNotification;
use App\Notifications\AccountEmailChangeVerification;
use App\Services\StripeBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Stripe\HttpClient\CurlClient;

uses(RefreshDatabase::class);

test('guests cannot access account settings', function () {
    $this->get(route('account.settings.edit'))
        ->assertRedirect(route('login'));

    $this->put(route('account.settings.update'))
        ->assertRedirect(route('login'));
});

test('users can update their account name independently', function () {
    Notification::fake();
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('account.settings.edit'))
        ->assertOk()
        ->assertSee('アカウントを削除する')
        ->assertSee(route('account.settings.destroy'))
        ->assertSee('type="button" data-delete-account-open', false)
        ->assertSee('name="password" type="password" form="delete-account-form"', false)
        ->assertSee('type="submit" form="delete-account-form" data-delete-account-confirm', false)
        ->assertSee('名前を変更')
        ->assertSee('メールアドレスを変更')
        ->assertSee(route('account.settings.email.update'));

    $this->actingAs($user)
        ->put(route('account.settings.update'), [
            'name' => 'Updated Name',
        ])
        ->assertRedirect(route('account.settings.edit'))
        ->assertSessionHas('status');

    Notification::assertSentTo($user, AccountActivityNotification::class, function (
        AccountActivityNotification $notification,
    ): bool {
        return $notification->activity === 'お名前の変更';
    });

    $this->actingAs($user)
        ->put(route('account.settings.update'), [
            'name' => 'Updated Name',
        ])
        ->assertRedirect(route('account.settings.edit'));

    expect(Notification::sent($user, AccountActivityNotification::class))->toHaveCount(1);

    expect($user->fresh()->name)->toBe('Updated Name')
        ->and($user->fresh()->email)->toBe($user->email)
        ->and($user->fresh()->email_verified_at)->not->toBeNull();
});

test('account settings updates return JSON for in-place feedback', function () {
    Notification::fake();
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user)
        ->putJson(route('account.settings.update'), [
            'name' => '',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');

    $this->actingAs($user)
        ->putJson(route('account.settings.update'), [
            'name' => 'Updated Name',
        ])
        ->assertOk()
        ->assertExactJson(['message' => 'お名前を更新しました。']);

    $this->actingAs($user)
        ->putJson(route('account.settings.security-notifications.update'), [
            'enabled' => '1',
            'types' => ['login_group'],
        ])
        ->assertOk()
        ->assertExactJson(['message' => 'セキュリティ通知の設定を更新しました。']);

    $this->actingAs($user)
        ->putJson(route('account.settings.email.update'), [
            'email' => 'updated@example.com',
        ])
        ->assertOk()
        ->assertExactJson(['message' => '確認用メールを送信しました。メール内のリンクを開くと変更が完了します。']);

    expect($user->fresh()->name)->toBe('Updated Name')
        ->and($user->fresh()->email)->toBe($user->email);
});

test('users can control security notifications globally and by activity type', function () {
    Notification::fake();
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ])->fresh();

    expect($user->security_notifications_enabled)->toBeTrue()
        ->and($user->security_notification_types)->toBeNull()
        ->and($user->receivesSecurityNotification('login'))->toBeTrue()
        ->and($user->receivesSecurityNotification('name_changed'))->toBeTrue();

    $this->actingAs($user)
        ->get(route('account.settings.edit'))
        ->assertOk()
        ->assertSee('セキュリティ通知')
        ->assertSee('name="enabled"', false)
        ->assertSee('remember-option security-notification-switch', false)
        ->assertSee('data-security-notifications-save disabled', false)
        ->assertSee('value="login_group"', false)
        ->assertSee('value="account_status_group"', false)
        ->assertDontSee('value="admin_account_changed"', false)
        ->assertDontSee('管理者によるアカウント情報の変更')
        ->assertSee('ログイン')
        ->assertSee('アカウントの利用停止・削除')
        ->assertDontSee('<fieldset disabled', false);

    $this->actingAs($user)
        ->put(route('account.settings.security-notifications.update'), [
            'enabled' => '1',
            'types' => ['login_group', 'account_status_group'],
        ])
        ->assertRedirect(route('account.settings.edit'))
        ->assertSessionHas('status', 'セキュリティ通知の設定を更新しました。');

    $user = $user->fresh();
    expect($user->receivesSecurityNotification('login'))->toBeTrue()
        ->and($user->receivesSecurityNotification('google_login'))->toBeTrue()
        ->and($user->receivesSecurityNotification('account_suspended'))->toBeTrue()
        ->and($user->receivesSecurityNotification('account_deleted'))->toBeTrue()
        ->and($user->receivesSecurityNotification('name_changed'))->toBeFalse();

    expect((new AccountActivityNotification('ログイン', null))->via($user))->toBe(['mail'])
        ->and((new AccountActivityNotification('Googleログイン', null))->via($user))->toBe(['mail'])
        ->and((new AccountActivityNotification('アカウントの利用停止', null))->via($user))->toBe(['mail'])
        ->and((new AccountActivityNotification('アカウントの削除', null))->via($user))->toBe(['mail'])
        ->and((new AccountActivityNotification('管理者によるアカウント情報の変更', null))->via($user))->toBe(['mail'])
        ->and((new AccountActivityNotification('お名前の変更', null))->via($user))->toBe([]);

    (new AccountActivityNotification('お名前の変更', null))->sendToEmail($user, $user->email);
    Notification::assertNothingSent();

    (new AccountActivityNotification('ログイン', null))->sendToEmail($user, $user->email);
    Notification::assertSentOnDemand(AccountActivityNotification::class, function (
        AccountActivityNotification $notification,
        array $channels,
        $notifiable,
    ) use ($user): bool {
        return $notification->activity === 'ログイン'
            && $channels === ['mail']
            && $notifiable->routeNotificationFor('mail') === $user->email;
    });

    (new AccountActivityNotification('管理者によるアカウント情報の変更', null))->sendToEmail($user, $user->email);
    Notification::assertSentOnDemand(AccountActivityNotification::class, function (
        AccountActivityNotification $notification,
        array $channels,
        $notifiable,
    ) use ($user): bool {
        return $notification->activity === '管理者によるアカウント情報の変更'
            && $channels === ['mail']
            && $notifiable->routeNotificationFor('mail') === $user->email;
    });

    $this->actingAs($user)
        ->put(route('account.settings.security-notifications.update'), [
        ])
        ->assertRedirect(route('account.settings.edit'));

    $user = $user->fresh();
    expect($user->security_notifications_enabled)->toBeFalse()
        ->and($user->security_notification_types)->toBe(['login', 'google_login', 'account_suspended', 'account_deleted'])
        ->and($user->receivesSecurityNotification('login'))->toBeFalse();

    expect($user->receivesSecurityNotification('admin_account_changed'))->toBeTrue();

    $this->actingAs($user)
        ->get(route('account.settings.edit'))
        ->assertOk()
        ->assertSee('<fieldset data-security-notification-types disabled>', false)
        ->assertSee('data-security-notifications-enabled', false)
        ->assertSee('securityNotificationsSaveButton.disabled = false;', false)
        ->assertSee('securityNotificationTypes.disabled = !securityNotificationsToggle.checked;', false);
});

test('email changes are not saved until the new address is confirmed', function () {
    Notification::fake();
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $newEmail = 'updated@example.com';

    $this->actingAs($user)
        ->put(route('account.settings.email.update'), [
            'email' => $newEmail,
        ])
        ->assertRedirect(route('account.settings.edit'))
        ->assertSessionHas('status');

    expect($user->fresh()->email)->toBe($user->email)
        ->and($user->fresh()->name)->toBe($user->name)
        ->and($user->fresh()->email_verified_at)->not->toBeNull();

    Notification::assertSentOnDemand(AccountEmailChangeVerification::class, function ($notification, array $channels, $notifiable) use ($newEmail): bool {
        parse_str((string) parse_url($notification->verificationUrl, PHP_URL_QUERY), $query);

        return $channels === ['mail']
            && $notifiable->routeNotificationFor('mail') === $newEmail
            && ($query['email'] ?? null) === $newEmail
            && URL::hasValidSignature(Request::create($notification->verificationUrl));
    });
});

test('users can confirm their new email address using the signed link', function () {
    Notification::fake();
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $newEmail = 'confirmed@example.com';
    $verificationUrl = URL::temporarySignedRoute(
        'account.email.verify',
        now()->addMinutes(60),
        ['user' => $user->id, 'email' => $newEmail],
    );

    $this->actingAs($user)
        ->get($verificationUrl)
        ->assertRedirect(route('account.settings.edit'))
        ->assertSessionHas('status', 'メールアドレスを変更しました。');

    expect($user->fresh()->email)->toBe($newEmail)
        ->and($user->fresh()->email_verified_at)->not->toBeNull();

    Notification::assertSentOnDemand(AccountActivityNotification::class, function (
        AccountActivityNotification $notification,
        array $channels,
        $notifiable,
    ) use ($user): bool {
        return $notification->activity === 'メールアドレスの変更'
            && $channels === ['mail']
            && $notifiable->routeNotificationFor('mail') === $user->email;
    });
});

test('email confirmation does not save an address that has since been registered', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $existingUser = User::factory()->create(['email' => 'taken@example.com']);
    $verificationUrl = URL::temporarySignedRoute(
        'account.email.verify',
        now()->addMinutes(60),
        ['user' => $user->id, 'email' => $existingUser->email],
    );

    $this->actingAs($user)
        ->get($verificationUrl)
        ->assertSessionHasErrors('email');

    expect($user->fresh()->email)->not->toBe($existingUser->email)
        ->and($user->fresh()->email_verified_at)->not->toBeNull();
});

test('users can manage Stripe billing from account settings', function () {
    $customer = User::factory()->create([
        'stripe_customer_id' => 'cus_settings',
    ]);

    $this->actingAs($customer)
        ->get(route('account.settings.edit'))
        ->assertOk()
        ->assertSee('現在のプラン：無料')
        ->assertSee('お支払い情報を管理')
        ->assertSee(route('billing.portal'));

    $subscriber = User::factory()->create([
        'plan' => User::PLAN_LITE,
        'stripe_customer_id' => 'cus_subscriber_settings',
        'stripe_subscription_id' => 'sub_settings',
    ]);

    $this->actingAs($subscriber)
        ->get(route('account.settings.edit'))
        ->assertOk()
        ->assertSee('現在のプラン：ライト')
        ->assertSee('契約・お支払いを管理(Stripe)')
        ->assertSee(route('billing.portal'));
});

test('account settings show the current free plan without billing actions for users without a Stripe customer', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('account.settings.edit'))
        ->assertOk()
        ->assertSee('現在のプラン：無料')
        ->assertDontSee('お支払い情報を管理')
        ->assertDontSee('契約・お支払いを管理(Stripe)');
});

test('account settings show the current paid plan and scheduled cancellation date', function () {
    $periodEnd = now()->addDays(12);
    $user = User::factory()->create([
        'plan' => User::PLAN_PREMIUM,
        'stripe_customer_id' => 'cus_cancel_scheduled',
        'stripe_subscription_id' => 'sub_cancel_scheduled',
        'stripe_cancel_at_period_end' => true,
        'stripe_current_period_end' => $periodEnd,
    ]);

    $this->actingAs($user)
        ->get(route('account.settings.edit'))
        ->assertOk()
        ->assertSee('現在のプラン：プレミアム')
        ->assertSee('自動更新：オフ')
        ->assertSee($periodEnd->format('Y年n月j日').'に終了予定')
        ->assertSee('自動更新をオンにする')
        ->assertSee('契約・お支払いを管理(Stripe)');
});

test('lite members can select monthly or yearly premium billing in the confirmation dialog', function () {
    config([
        'services.stripe.prices.premium' => 'price_premium',
        'services.stripe.prices.premium_yearly' => 'price_premium_yearly',
    ]);

    $liteMember = User::factory()->create([
        'plan' => User::PLAN_LITE,
        'stripe_customer_id' => 'cus_upgrade',
        'stripe_subscription_id' => 'sub_upgrade',
    ]);

    $this->actingAs($liteMember)
        ->get(route('account.settings.edit'))
        ->assertOk()
        ->assertSeeInOrder([
            '<dialog class="confirm-dialog" data-plan-change-dialog',
            '<fieldset class="plan-billing-options" data-plan-change-options="premium" hidden>',
            'name="dialog-premium-plan" value="premium"',
            'name="dialog-premium-plan" value="premium_yearly"',
        ], false)
        ->assertSee('data-plan-options-in-dialog', false)
        ->assertSee('name="dialog-premium-plan" value="premium"', false)
        ->assertSee('name="dialog-premium-plan" value="premium" data-plan-dialog-option data-confirmation-note="変更後のプラン：プレミアムプラン(月額)" checked', false)
        ->assertSee('name="dialog-premium-plan" value="premium_yearly" data-plan-dialog-option data-confirmation-note="変更後のプラン：プレミアムプラン(年額)"', false)
        ->assertSee('月額プラン', false)
        ->assertSee('年額プラン', false);
});

test('premium members can select monthly or yearly lite billing in the confirmation dialog', function () {
    config([
        'services.stripe.prices.lite' => 'price_lite',
        'services.stripe.prices.lite_yearly' => 'price_lite_yearly',
    ]);
    $premiumMember = User::factory()->create([
        'plan' => User::PLAN_PREMIUM,
        'stripe_customer_id' => 'cus_downgrade_billing',
        'stripe_subscription_id' => 'sub_downgrade_billing',
    ]);

    $this->actingAs($premiumMember)
        ->get(route('account.settings.edit'))
        ->assertOk()
        ->assertSee('data-plan-options-in-dialog="lite"', false)
        ->assertSee('data-plan-timing-in-dialog="lite"', false)
        ->assertSeeInOrder([
            '<fieldset class="plan-billing-options" data-plan-change-options="lite" hidden>',
            'name="dialog-lite-plan" value="lite"',
            'name="dialog-lite-plan" value="lite_yearly"',
        ], false)
        ->assertSee('<fieldset class="plan-billing-options" data-plan-change-timing="lite" hidden>', false)
        ->assertSee('name="timing" value="period_end" form="account-lite-plan-change-form" data-plan-dialog-timing', false)
        ->assertSee('name="timing" value="immediate" form="account-lite-plan-change-form" data-plan-dialog-timing', false)
        ->assertSee('name="dialog-lite-plan" value="lite" data-plan-dialog-option data-confirmation-note="変更後のプラン：ライトプラン(月額)" checked', false)
        ->assertSee('name="dialog-lite-plan" value="lite_yearly" data-plan-dialog-option data-confirmation-note="変更後のプラン：ライトプラン(年額)"', false)
        ->assertSee('ライトプランへ変更');
});

test('account settings offer immediate upgrades and end-of-period downgrades', function () {
    $liteMember = User::factory()->create([
        'plan' => User::PLAN_LITE,
        'stripe_customer_id' => 'cus_upgrade',
        'stripe_subscription_id' => 'sub_upgrade',
    ]);

    $this->actingAs($liteMember)
        ->get(route('account.settings.edit'))
        ->assertOk()
        ->assertSee('プレミアムプランへ変更')
        ->assertSee('data-plan-change-form', false)
        ->assertSee('data-plan-change-dialog', false)
        ->assertSee('自動更新：オン')
        ->assertSee('自動更新をオフにする')
        ->assertSee('日割り計算で今回の請求に充当されます。');

    $periodEnd = now()->addDays(10)->setTime(17, 30);
    $premiumMember = User::factory()->create([
        'plan' => User::PLAN_PREMIUM,
        'stripe_customer_id' => 'cus_downgrade',
        'stripe_subscription_id' => 'sub_downgrade',
        'stripe_current_period_end' => $periodEnd,
    ]);

    $this->actingAs($premiumMember)
        ->get(route('account.settings.edit'))
        ->assertOk()
        ->assertSeeInOrder(['ライトプランへ変更', '契約・お支払いを管理(Stripe)'])
        ->assertSee('data-plan-change-form', false)
        ->assertSee('data-plan-change-dialog', false)
        ->assertSee('現在のプレミアム期間終了後にライトプランへ切り替えます。')
        ->assertSee('すぐに切り替えて未使用期間分を返金')
        ->assertSee('data-confirmation-period-end-label="現在のプレミアム期間終了日時:"', false)
        ->assertSee('data-confirmation-period-end-date="'.$periodEnd->format('Y年n月j日 H:i').'"', false)
        ->assertSee('契約期間終了時に解約します。期間終了までは現在のプランをご利用いただけます。')
        ->assertSee('自動更新をオフにする');
});

test('account settings allow members to schedule a billing frequency change', function () {
    config([
        'services.stripe.secret' => 'sk_test',
        'services.stripe.prices.lite' => 'price_lite',
        'services.stripe.prices.lite_yearly' => 'price_lite_yearly',
    ]);
    $user = User::factory()->create([
        'plan' => User::PLAN_LITE,
        'stripe_customer_id' => 'cus_frequency',
        'stripe_subscription_id' => 'sub_frequency',
        'stripe_subscription_interval' => 'month',
        'stripe_current_period_end' => now()->addDays(12),
    ]);

    $this->actingAs($user)
        ->get(route('account.settings.edit'))
        ->assertOk()
        ->assertSee('年額プランに切り替える')
        ->assertSee('name="plan" value="lite_yearly"', false)
        ->assertDontSee('<legend>お支払い周期</legend>', false)
        ->assertDontSee('type="radio" name="plan"', false)
        ->assertSee('お支払い周期の変更は現在の契約期間終了後に適用されます。')
        ->assertSee('現在の契約期間終了日時:', false);
});

test('account settings offer switching from annual to monthly billing', function () {
    config([
        'services.stripe.secret' => 'sk_test',
        'services.stripe.prices.premium' => 'price_premium',
        'services.stripe.prices.premium_yearly' => 'price_premium_yearly',
    ]);
    $user = User::factory()->create([
        'plan' => User::PLAN_PREMIUM,
        'stripe_customer_id' => 'cus_annual_frequency',
        'stripe_subscription_id' => 'sub_annual_frequency',
        'stripe_subscription_interval' => 'year',
        'stripe_current_period_end' => now()->addDays(12),
    ]);

    $this->actingAs($user)
        ->get(route('account.settings.edit'))
        ->assertOk()
        ->assertSee('月額プランに切り替える')
        ->assertSee('name="plan" value="premium"', false)
        ->assertDontSee('<legend>お支払い周期</legend>', false)
        ->assertDontSee('type="radio" name="plan"', false);
});

test('account settings show and allow cancellation of a scheduled billing frequency change', function () {
    $user = User::factory()->create([
        'plan' => User::PLAN_PREMIUM,
        'stripe_customer_id' => 'cus_frequency_scheduled',
        'stripe_subscription_id' => 'sub_frequency_scheduled',
        'stripe_subscription_interval' => 'month',
        'stripe_scheduled_interval' => 'year',
        'stripe_subscription_schedule_id' => 'sched_frequency',
        'stripe_current_period_end' => now()->addDays(8),
    ]);

    $this->actingAs($user)
        ->get(route('account.settings.edit'))
        ->assertOk()
        ->assertSee('年額へ変更予定')
        ->assertSee('プラン変更の予約を取り消す')
        ->assertSee(route('billing.plan.cancel'))
        ->assertDontSee('年額プランに切り替える')
        ->assertDontSee('月額プランに切り替える');
});

test('account settings show and allow cancellation of a scheduled downgrade', function () {
    $user = User::factory()->create([
        'plan' => User::PLAN_PREMIUM,
        'stripe_customer_id' => 'cus_scheduled',
        'stripe_subscription_id' => 'sub_scheduled',
        'stripe_scheduled_plan' => User::PLAN_LITE,
        'stripe_subscription_schedule_id' => 'sched_scheduled',
        'stripe_current_period_end' => now()->addDays(8),
    ]);

    $this->actingAs($user)
        ->get(route('account.settings.edit'))
        ->assertOk()
        ->assertSee('ライトプランへ切り替わる予定です。')
        ->assertSee('プラン変更の予約を取り消す')
        ->assertSee(route('billing.plan.cancel'));
});

test('users are told Stripe billing changes may take time to appear after returning', function () {
    $user = User::factory()->create([
        'stripe_customer_id' => 'cus_returned',
    ]);

    $this->actingAs($user)
        ->get(route('account.settings.edit', ['billing' => 'returned']))
        ->assertOk()
        ->assertSee('Stripeの管理画面から戻りました。')
        ->assertSee('契約内容の変更はWebhookで反映されるため');
});

test('billing portal returns users to account settings', function () {
    config(['services.stripe.secret' => 'sk_test']);
    $httpClient = new class implements ClientInterface
    {
        public array $requests = [];

        public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
        {
            $this->requests[] = compact('method', 'absUrl', 'params');

            return [
                json_encode([
                    'id' => 'bps_test',
                    'object' => 'billing_portal.session',
                    'url' => 'https://billing.stripe.com/session/test',
                ], JSON_THROW_ON_ERROR),
                200,
                ['Content-Type' => 'application/json'],
            ];
        }
    };
    ApiRequestor::setHttpClient($httpClient);

    try {
        $user = User::factory()->create([
            'stripe_customer_id' => 'cus_return_destination',
        ]);

        $this->actingAs($user)
            ->post(route('billing.portal'))
            ->assertRedirect('https://billing.stripe.com/session/test');

        expect($httpClient->requests)->toHaveCount(1)
            ->and($httpClient->requests[0]['params']['return_url'])
            ->toBe(route('account.settings.edit', ['billing' => 'returned']));
    } finally {
        ApiRequestor::setHttpClient(new CurlClient);
    }
});

test('users can permanently delete their account and associated records after confirming their password', function () {
    Notification::fake();
    $user = User::factory()->create([
        'password' => Hash::make('A-strong-password-2026'),
    ]);
    $reading = $user->zenConsultations()->create([
        'concern' => 'Will I succeed?',
        'response' => 'A hopeful reading.',
    ]);
    DB::table('sessions')->insert([
        'id' => 'account-session',
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'test',
        'payload' => 'payload',
        'last_activity' => now()->timestamp,
    ]);
    DB::table('password_reset_tokens')->insert([
        'email' => $user->email,
        'token' => 'reset-token',
        'created_at' => now(),
    ]);

    $this->actingAs($user)
        ->delete(route('account.settings.destroy'), [
            'password' => 'A-strong-password-2026',
        ])
        ->assertRedirect(route('login'))
        ->assertSessionHas('status');

    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['id' => $user->id]);
    $this->assertDatabaseMissing('zen_consultations', ['id' => $reading->id]);
    $this->assertDatabaseMissing('sessions', ['id' => 'account-session']);
    $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    Notification::assertSentOnDemand(AccountActivityNotification::class, function (
        AccountActivityNotification $notification,
        array $channels,
        $notifiable,
    ) use ($user): bool {
        return $notification->activity === 'アカウントの削除'
            && $channels === ['mail']
            && $notifiable->routeNotificationFor('mail') === $user->email;
    });
});

test('an incorrect password prevents account deletion', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->delete(route('account.settings.destroy'), [
            'password' => 'incorrect-password',
        ])
        ->assertSessionHasErrors('password');

    $this->assertDatabaseHas('users', ['id' => $user->id]);
    $this->assertAuthenticatedAs($user);
});

test('the password can be verified before the deletion confirmation opens', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'password' => Hash::make('A-strong-password-2026'),
    ]);

    $this->actingAs($user)
        ->postJson(route('account.settings.verify-password'), ['password' => 'incorrect-password'])
        ->assertUnprocessable()
        ->assertJson(['message' => 'パスワードが違います。']);

    $this->actingAs($user)
        ->postJson(route('account.settings.verify-password'), ['password' => 'A-strong-password-2026'])
        ->assertOk();

    $this->assertDatabaseHas('users', ['id' => $user->id]);
});

test('the last active administrator cannot delete their account', function () {
    $administrator = User::factory()->admin()->create();

    $this->actingAs($administrator)
        ->delete(route('account.settings.destroy'), [
            'password' => 'password',
        ])
        ->assertSessionHasErrors('account');

    $this->assertDatabaseHas('users', ['id' => $administrator->id]);
});

test('deleting an account cancels its stripe billing before removing its data', function () {
    $user = User::factory()->create([
        'stripe_customer_id' => 'cus_account_deletion',
    ]);
    $billing = Mockery::mock(StripeBillingService::class);
    $billing->shouldReceive('cancelForAccountDeletion')->once()->with(Mockery::on(
        fn (User $account): bool => $account->is($user),
    ));
    $this->app->instance(StripeBillingService::class, $billing);

    $this->actingAs($user)
        ->delete(route('account.settings.destroy'), [
            'password' => 'password',
        ])
        ->assertRedirect(route('login'));

    $this->assertDatabaseMissing('users', ['id' => $user->id]);
});
