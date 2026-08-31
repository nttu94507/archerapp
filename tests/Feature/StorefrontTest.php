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

    public function test_completed_or_cancelled_event_cannot_be_selected_for_single_event_upgrade(): void
    {
        $user = User::factory()->create();
        $pastButOpen = Event::factory()->create([
            'name'=>'日期已過但尚未結案',
            'start_date'=>now()->subDays(2),
            'end_date'=>now()->subDay(),
        ]);
        $completed = Event::factory()->create(['name'=>'已正式完成賽事', 'completed_at'=>now()]);
        $cancelled = Event::factory()->create(['name'=>'已取消賽事', 'cancelled_at'=>now()]);
        foreach ([$pastButOpen, $completed, $cancelled] as $event) {
            $event->staff()->create(['user_id'=>$user->id, 'role'=>'owner', 'status'=>'active']);
        }

        $this->actingAs($user)->get(route('store.index'))
            ->assertOk()
            ->assertSee('日期已過但尚未結案')
            ->assertSee('不可升級的賽事')
            ->assertSee('已正式完成賽事')
            ->assertSee('已取消賽事');

        $this->actingAs($user)->get(route('store.index', ['event'=>$completed->uuid]))
            ->assertOk()
            ->assertSee('賽事已正式完成，無法再套用執行中的進階功能。')
            ->assertDontSee('<option value="'.$completed->uuid.'"', false);

        $this->assertTrue($pastButOpen->canUpgradeToEventPass());
        $this->assertFalse($completed->canUpgradeToEventPass());
        $this->assertFalse($cancelled->canUpgradeToEventPass());
    }
}
