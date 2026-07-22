<?php

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('new users receive a uuid member number', function () {
    $user = User::factory()->create();

    expect($user->uuid)->not->toBeNull()
        ->and(Str::isUuid($user->uuid))->toBeTrue();
});

test('member profile is one level above its edit form', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('member-profile.index'))
        ->assertOk()
        ->assertSee('會員資料')
        ->assertSee(route('member-profile.edit'));

    $this->actingAs($user)
        ->get(route('member-profile.edit'))
        ->assertOk()
        ->assertSee('編輯會員資料');
});

test('member qr code contains the uuid profile url', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('member-profile.qrcode'))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/svg+xml');
});

test('another authenticated user can view public member data without private contact data', function () {
    $viewer = User::factory()->create();
    $member = User::factory()->create(['nickname' => '弓箭手小明']);
    UserProfile::create([
        'user_id' => $member->id,
        'phone' => '0912345678',
        'city' => '台北市',
        'emergency_contact_name' => '緊急聯絡人',
        'emergency_contact_phone' => '0987654321',
        'birthdate' => '1990-01-01',
        'handedness' => 'right',
        'bow_type' => 'recurve',
    ]);

    $this->actingAs($viewer)
        ->get(route('members.show', $member->uuid))
        ->assertOk()
        ->assertSee('弓箭手小明')
        ->assertSee('台北市')
        ->assertDontSee('0912345678')
        ->assertDontSee('緊急聯絡人')
        ->assertDontSee('1990-01-01');
});

test('member pages require authentication', function () {
    $member = User::factory()->create();

    $this->get(route('members.scan'))->assertRedirect(route('login.options'));
    $this->get(route('members.show', $member->uuid))->assertRedirect(route('login.options'));
});
