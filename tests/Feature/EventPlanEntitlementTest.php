<?php

namespace Tests\Feature;

use App\Models\Event;
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
}
