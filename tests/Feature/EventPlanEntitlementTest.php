<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventGroup;
use App\Models\User;
use App\Support\EventPlanCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventPlanEntitlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_event_uses_free_plan_limits_and_features(): void
    {
        $event = Event::factory()->create();

        $this->assertSame(EventPlanCatalog::FREE, $event->plan_code);
        $this->assertSame(EventPlanCatalog::STATUS_ACTIVE, $event->plan_status);
        $this->assertSame(1, $event->planLimit('groups'));
        $this->assertSame(2, $event->planLimit('staff_members'));
        $this->assertSame(32, $event->planLimit('athletes'));
        $this->assertSame(8, $event->planLimit('targets'));
        $this->assertSame(36, $event->planLimit('arrows_per_phase'));
        $this->assertSame(1, $event->planLimit('badges'));
        $this->assertTrue($event->hasPlanFeature('qualification'));
        $this->assertFalse($event->hasPlanFeature('individual_elimination'));
        $this->assertTrue($event->hasPlanFeature('internal_visibility'));
    }

    public function test_paid_plan_snapshot_unlocks_elimination_and_unlimited_resources(): void
    {
        $event = Event::factory()->create([
            'plan_code'=>EventPlanCatalog::EVENT_PASS,
            'plan_features_snapshot'=>EventPlanCatalog::features(EventPlanCatalog::EVENT_PASS),
            'plan_limits_snapshot'=>EventPlanCatalog::limits(EventPlanCatalog::EVENT_PASS),
        ]);

        $this->assertTrue($event->hasPlanFeature('individual_elimination'));
        $this->assertTrue($event->hasPlanFeature('multiple_rounds'));
        $this->assertNull($event->planLimit('groups'));
        $this->assertNull($event->planLimit('arrows_per_phase'));
    }

    public function test_inactive_plan_disables_plan_features(): void
    {
        $event = Event::factory()->create(['plan_status'=>EventPlanCatalog::STATUS_EXPIRED]);

        $this->assertFalse($event->hasPlanFeature('qualification'));
        $this->assertFalse($event->planIsActive());
    }

    public function test_expired_activation_window_disables_plan_features(): void
    {
        $event = Event::factory()->create(['plan_expires_at'=>now()->subMinute()]);

        $this->assertFalse($event->hasPlanFeature('qualification'));
        $this->assertFalse($event->planIsActive());
    }

    public function test_free_event_cannot_add_a_second_group_but_paid_event_can(): void
    {
        $owner = User::factory()->create();
        $freeEvent = Event::factory()->create();
        $freeEvent->staff()->create(['user_id'=>$owner->id, 'role'=>'owner', 'status'=>'active']);
        EventGroup::factory()->create(['event_id'=>$freeEvent->id, 'name'=>'第一組', 'arrow_count'=>36]);

        $this->actingAs($owner)
            ->get(route('events.groups.index', $freeEvent))
            ->assertOk()
            ->assertDontSee('免費方案最多只能建立 1 個組別。')
            ->assertSee('升級以新增組別')
            ->assertSee(route('store.index', ['event'=>$freeEvent->uuid]), false);
        $this->actingAs($owner)
            ->get(route('events.groups.create', $freeEvent))
            ->assertRedirect(route('events.groups.index', $freeEvent))
            ->assertSessionHas('error');
        $this->actingAs($owner)
            ->post(route('events.groups.store', $freeEvent), ['groups'=>[$this->groupPayload('第二組')]])
            ->assertRedirect(route('events.groups.index', $freeEvent))
            ->assertSessionHas('error');
        $this->assertSame(1, $freeEvent->groups()->count());

        $paidEvent = Event::factory()->create([
            'plan_code'=>EventPlanCatalog::EVENT_PASS,
            'plan_features_snapshot'=>EventPlanCatalog::features(EventPlanCatalog::EVENT_PASS),
            'plan_limits_snapshot'=>EventPlanCatalog::limits(EventPlanCatalog::EVENT_PASS),
        ]);
        $paidEvent->staff()->create(['user_id'=>$owner->id, 'role'=>'owner', 'status'=>'active']);
        EventGroup::factory()->create(['event_id'=>$paidEvent->id, 'name'=>'第一組', 'arrow_count'=>72]);

        $this->actingAs($owner)
            ->post(route('events.groups.store', $paidEvent), ['groups'=>[$this->groupPayload('第二組', 72)]])
            ->assertSessionHas('success');
        $this->assertSame(2, $paidEvent->groups()->count());
    }

    private function groupPayload(string $name, int $arrows = 36): array
    {
        return [
            'name'=>$name,
            'bow_type'=>'recurve',
            'gender'=>'open',
            'distance'=>'70m',
            'arrow_count'=>$arrows,
            'arrows_per_end'=>6,
            'quota'=>32,
            'fee'=>0,
            'is_team'=>false,
        ];
    }
}
