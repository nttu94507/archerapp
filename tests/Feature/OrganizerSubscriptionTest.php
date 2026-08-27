<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\OrganizerProfile;
use App\Models\OrganizerSubscription;
use App\Models\User;
use App\Support\EventPlanCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizerSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_activate_and_cancel_an_organizer_subscription(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $organizer = User::factory()->create();

        $this->actingAs($admin)->patch(route('admin.users.subscription.update', $organizer), [
            'action' => 'activate',
            'ends_at' => now()->addYear()->format('Y-m-d H:i:s'),
        ])->assertSessionHas('success');

        $subscription = $organizer->organizerSubscription()->firstOrFail();
        $this->assertTrue($subscription->isActive());
        $this->assertSame($admin->id, $subscription->activated_by);

        $this->actingAs($admin)->patch(route('admin.users.subscription.update', $organizer), [
            'action' => 'cancel',
        ])->assertSessionHas('success');

        $this->assertSame(OrganizerSubscription::STATUS_CANCELLED, $subscription->fresh()->status);
        $this->assertFalse($organizer->hasActiveOrganizerSubscription());
    }

    public function test_event_created_during_subscription_keeps_paid_snapshot_after_cancellation(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $organizer = User::factory()->create();
        OrganizerProfile::create([
            'user_id' => $organizer->id,
            'organization_name' => '訂閱測試弓社',
            'organization_type' => 'club',
            'contact_name' => $organizer->name,
            'contact_email' => $organizer->email,
            'contact_phone' => '0912345678',
            'application_reason' => '測試',
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        $this->actingAs($admin)->patch(route('admin.users.subscription.update', $organizer), [
            'action' => 'activate',
        ]);

        $this->actingAs($organizer)->post(route('organizer.events.store'), $this->eventPayload('訂閱期間賽事'))
            ->assertRedirect();

        $subscribedEvent = Event::where('name', '訂閱期間賽事')->firstOrFail();
        $this->assertSame(EventPlanCatalog::SUBSCRIPTION, $subscribedEvent->plan_code);
        $this->assertTrue($subscribedEvent->hasPlanFeature('individual_elimination'));
        $this->assertNull($subscribedEvent->planLimit('groups'));

        $this->actingAs($admin)->patch(route('admin.users.subscription.update', $organizer), [
            'action' => 'cancel',
        ]);

        $this->assertTrue($subscribedEvent->fresh()->hasPlanFeature('individual_elimination'));

        $this->actingAs($organizer)->post(route('organizer.events.store'), $this->eventPayload('訂閱停止後賽事'))
            ->assertRedirect();

        $freeEvent = Event::where('name', '訂閱停止後賽事')->firstOrFail();
        $this->assertSame(EventPlanCatalog::FREE, $freeEvent->plan_code);
        $this->assertFalse($freeEvent->hasPlanFeature('individual_elimination'));
    }

    private function eventPayload(string $name): array
    {
        return [
            'name' => $name,
            'start_date' => now()->addMonth()->toDateString(),
            'end_date' => now()->addMonth()->addDay()->toDateString(),
            'mode' => 'outdoor',
            'organizer' => '訂閱測試弓社',
            'reg_start' => now()->toDateTimeString(),
            'reg_end' => now()->addWeeks(2)->toDateTimeString(),
        ];
    }
}
