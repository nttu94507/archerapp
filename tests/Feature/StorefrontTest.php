<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_must_sign_in_to_open_store(): void
    {
        $this->get(route('store.index'))
            ->assertRedirect(route('login.options'));
    }

    public function test_organizer_can_select_only_an_event_they_manage(): void
    {
        $user = User::factory()->create();
        $managedEvent = Event::factory()->create(['name' => '我的測試賽事']);
        $otherEvent = Event::factory()->create(['name' => '其他主辦方賽事']);

        $managedEvent->staff()->create([
            'user_id' => $user->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->get(route('store.index', ['event' => $managedEvent->uuid]))
            ->assertOk()
            ->assertSee('方案商店')
            ->assertSee('我的測試賽事')
            ->assertDontSee('其他主辦方賽事')
            ->assertSee('付款功能準備中');

        $this->actingAs($user)
            ->get(route('store.index', ['event' => $otherEvent->uuid]))
            ->assertOk()
            ->assertSee('找不到可由你管理的賽事');
    }
}
