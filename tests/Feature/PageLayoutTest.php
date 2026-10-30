<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('public pages share a description, favicon, and open graph metadata', function () {
    $this->get(route('home', ['locale' => 'ja']))
        ->assertOk()
        ->assertSee('<title>心の庵 ZEN REFLECTIONS</title>', false)
        ->assertSee('<meta name="description" content="AIとの対話を通じて、禅の考え方を手がかりに自分の心を見つめ直すための場所です。">', false)
        ->assertSee('<link rel="icon" href="'.asset('favicon.ico').'">', false)
        ->assertSee('<meta property="og:title" content="心の庵 ZEN REFLECTIONS">', false)
        ->assertSee('<meta property="og:image" content="'.asset('images/logo.png').'">', false)
        ->assertSee('src="'.asset('images/logo.webp').'"', false)
        ->assertSee('<meta property="og:locale" content="ja_JP">', false)
        ->assertDontSee('name="robots"', false);

    $this->get(route('home', ['locale' => 'en']))
        ->assertOk()
        ->assertSee('<meta name="description" content="A quiet place to look within through conversation with AI, guided by Zen perspectives.">', false)
        ->assertSee('<meta property="og:locale" content="en_US">', false);
});

test('page titles are prefixed to the site name and escaped only once', function () {
    $this->get(route('legal.terms'))
        ->assertOk()
        ->assertSee('<title>利用規約 | 心の庵 ZEN REFLECTIONS</title>', false)
        ->assertSee('<meta property="og:title" content="利用規約 | 心の庵 ZEN REFLECTIONS">', false);

    $admin = User::factory()->admin()->create();
    $user = User::factory()->create(['name' => 'Tom & Jerry']);

    $this->actingAs($admin)
        ->get(route('admin.users.show', $user))
        ->assertOk()
        ->assertSee('<title>Tom &amp; Jerry | ユーザー管理 | 心の庵 ZEN REFLECTIONS</title>', false);
});

test('private pages are not indexable and expose no open graph metadata', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('consultations.index'))
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex, nofollow, noarchive">', false)
        ->assertDontSee('og:', false)
        ->assertDontSee('twitter:', false);
});
