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

    public function test_admin_can_resync_existing_events_for_an_already_active_subscription(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $organizer = User::factory()->create();
        $subscription = OrganizerSubscription::create([
            'user_id' => $organizer->id,
            'plan_code' => EventPlanCatalog::SUBSCRIPTION,
            'status' => OrganizerSubscription::STATUS_ACTIVE,
            'starts_at' => now()->subDay(),
        ]);
        $event = Event::factory()->create(['plan_code' => EventPlanCatalog::FREE]);
        $event->staff()->create(['user_id' => $organizer->id, 'role' => 'owner', 'status' => 'active']);

        $this->actingAs($admin)->patch(route('admin.users.subscription.update', $organizer), [
            'action' => 'sync',
        ])->assertSessionHas('success', fn (string $message) => str_contains($message, '解鎖 1 場'));

        $event->refresh();
        $this->assertSame(EventPlanCatalog::SUBSCRIPTION, $event->plan_code);
        $this->assertSame('subscription:'.$subscription->id, $event->plan_order_reference);
        $this->assertTrue($event->hasPlanFeature('individual_elimination'));
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

    public function test_activating_subscription_unlocks_existing_free_events_owned_by_user(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $organizer = User::factory()->create();
        $otherOwner = User::factory()->create();
        $ownedEvent = Event::factory()->create(['name' => '訂閱前建立']);
        $managedEvent = Event::factory()->create(['name' => '僅協助管理']);
        $completedEvent = Event::factory()->create(['name' => '訂閱前已完成']);
        $cancelledEvent = Event::factory()->create(['name' => '訂閱前已取消', 'cancelled_at' => now()->subDay()]);
        $paidEvent = Event::factory()->create([
            'name' => '原本單場付費',
            'plan_code' => EventPlanCatalog::EVENT_PASS,
            'plan_limits_snapshot' => EventPlanCatalog::limits(EventPlanCatalog::EVENT_PASS),
            'plan_features_snapshot' => EventPlanCatalog::features(EventPlanCatalog::EVENT_PASS),
        ]);

        $ownedEvent->staff()->create(['user_id' => $organizer->id, 'role' => 'owner', 'status' => 'active']);
        $managedEvent->staff()->create(['user_id' => $otherOwner->id, 'role' => 'owner', 'status' => 'active']);
        $managedEvent->staff()->create(['user_id' => $organizer->id, 'role' => 'manager', 'status' => 'active']);
        $paidEvent->staff()->create(['user_id' => $organizer->id, 'role' => 'owner', 'status' => 'active']);
        $completedEvent->staff()->create(['user_id' => $organizer->id, 'role' => 'owner', 'status' => 'active']);
        $cancelledEvent->staff()->create(['user_id' => $organizer->id, 'role' => 'owner', 'status' => 'active']);
        $completedEvent->auditLogs()->create([
            'user_id' => $organizer->id,
            'action' => 'event.completed',
            'subject_type' => Event::class,
            'subject_id' => $completedEvent->id,
        ]);

        $this->actingAs($admin)->patch(route('admin.users.subscription.update', $organizer), [
            'action' => 'activate',
        ])->assertSessionHas('success', fn (string $message) => str_contains($message, '解鎖 1 場'));

        $this->assertSame(EventPlanCatalog::SUBSCRIPTION, $ownedEvent->fresh()->plan_code);
        $this->assertTrue($ownedEvent->fresh()->hasPlanFeature('individual_elimination'));
        $this->assertSame(EventPlanCatalog::FREE, $managedEvent->fresh()->plan_code);
        $this->assertSame(EventPlanCatalog::EVENT_PASS, $paidEvent->fresh()->plan_code);
        $this->assertSame(EventPlanCatalog::FREE, $completedEvent->fresh()->plan_code);
        $this->assertSame(EventPlanCatalog::FREE, $cancelledEvent->fresh()->plan_code);

        $this->actingAs($admin)->patch(route('admin.users.subscription.update', $organizer), [
            'action' => 'cancel',
        ]);

        $this->assertTrue($ownedEvent->fresh()->hasPlanFeature('individual_elimination'));
    }

    public function test_free_organizer_cannot_create_72_arrow_group_but_subscriber_can(): void
    {
        $freeOrganizer = $this->approvedOrganizer('免費主辦方');
        $subscriber = $this->approvedOrganizer('訂閱主辦方');

        OrganizerSubscription::create([
            'user_id' => $subscriber->id,
            'plan_code' => EventPlanCatalog::SUBSCRIPTION,
            'status' => OrganizerSubscription::STATUS_ACTIVE,
            'starts_at' => now(),
        ]);

        $payload = array_merge($this->eventPayload('72 箭賽事'), [
            'submit_mode' => 'publish',
            'groups' => [[
                'name' => '反曲弓公開組',
                'bow_type' => 'recurve',
                'gender' => 'open',
                'distance' => '70m',
                'arrow_count' => 72,
                'arrows_per_end' => 6,
                'quota' => 32,
                'fee' => 0,
                'is_team' => 0,
            ]],
        ]);

        $this->actingAs($freeOrganizer)
            ->from(route('organizer.events.create'))
            ->post(route('organizer.events.store'), $payload)
            ->assertRedirect(route('organizer.events.create'))
            ->assertSessionHasErrors('groups.0.arrow_count');

        $this->assertDatabaseMissing('events', ['name' => '72 箭賽事']);

        $this->actingAs($subscriber)
            ->post(route('organizer.events.store'), $payload)
            ->assertRedirect();

        $event = Event::where('name', '72 箭賽事')->firstOrFail();
        $this->assertSame(EventPlanCatalog::SUBSCRIPTION, $event->plan_code);
        $this->assertDatabaseHas('event_groups', ['event_id' => $event->id, 'arrow_count' => 72]);
    }

    private function approvedOrganizer(string $organizationName): User
    {
        $user = User::factory()->create();
        OrganizerProfile::create([
            'user_id' => $user->id,
            'organization_name' => $organizationName,
            'organization_type' => 'club',
            'contact_name' => $user->name,
            'contact_email' => $user->email,
            'contact_phone' => '0912345678',
            'application_reason' => '測試',
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        return $user;
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
