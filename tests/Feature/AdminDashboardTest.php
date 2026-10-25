<?php

use App\Models\ContactInquiry;
use App\Models\User;
use App\Models\ZenConsultation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

test('guests and non admin users cannot open the admin dashboard', function () {
    $this->get(route('admin.dashboard'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

test('admins see users, billing, usage and operations on the dashboard', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->create(['plan' => User::PLAN_LITE, 'stripe_subscription_id' => 'sub_1', 'stripe_subscription_status' => 'active']);
    User::factory()->create([
        'name' => 'Past Due Reader',
        'plan' => User::PLAN_PREMIUM,
        'stripe_subscription_id' => 'sub_2',
        'stripe_subscription_status' => 'past_due',
    ]);
    User::factory()->inactive()->create();
    ZenConsultation::create(['user_id' => $admin->id, 'concern' => 'x', 'response' => 'y', 'topic' => '仕事']);
    ContactInquiry::factory()->create(['subject' => 'Need help']);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Past Due Reader')
        ->assertSee('past_due')
        ->assertSee('Need help')
        ->assertSee(route('admin.users.index'));
});

test('the account menu links to the dashboard only for admins', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertDontSee(route('admin.dashboard'));

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('home'))
        ->assertSee(route('admin.dashboard'));
});

test('admins can mark an inquiry as resolved', function () {
    $inquiry = ContactInquiry::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->patch(route('admin.inquiries.resolve', $inquiry))
        ->assertRedirect();

    expect($inquiry->fresh()->resolved_at)->not->toBeNull();

    $this->actingAs(User::factory()->create())
        ->patch(route('admin.inquiries.resolve', $inquiry))
        ->assertForbidden();
});

test('contact submissions are stored for the admin dashboard', function () {
    Mail::fake();

    $this->post(route('contact.store'), [
        'name' => 'Visitor',
        'email' => 'visitor@example.com',
        'subject' => 'Hello',
        'message' => 'A question',
    ])->assertRedirect();

    $this->assertDatabaseHas('contact_inquiries', ['email' => 'visitor@example.com', 'mail_sent' => true]);
});
