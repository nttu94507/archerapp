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

    public function test_create_event_shows_team_format_preview_and_persists_mixed_team_group(): void
    {
        $subscriber=$this->approvedOrganizer('混雙主辦方');
        OrganizerSubscription::create(['user_id'=>$subscriber->id,'plan_code'=>EventPlanCatalog::SUBSCRIPTION,'status'=>OrganizerSubscription::STATUS_ACTIVE,'starts_at'=>now()]);

        $this->actingAs($subscriber)->get(route('organizer.events.create'))
            ->assertOk()->assertSee('室外標準賽')->assertSee('室外短距離')->assertSee('室內賽')
            ->assertSee('勾選報名組別')->assertSee('裸弓')->assertSee('將建立')
            ->assertSee('賽事內容')->assertSee('3 人團體賽')->assertSee('男女混雙')->assertSee('建立架構預覽')
            ->assertSee('反曲弓 70m')->assertSee('複合弓 50m')->assertSee('室內反曲弓 18m')->assertSee('自訂賽制')
            ->assertSee('反曲弓 70 公尺男子組')->assertSee('反曲弓 30 公尺女子組')->assertSee('複合弓 50 公尺公開組');

        $payload=array_merge($this->eventPayload('混雙快速賽事'),['submit_mode'=>'publish','groups'=>[0=>[
            'name'=>'反曲弓公開組','bow_type'=>'recurve','gender'=>'open','distance'=>'70m','arrow_count'=>72,
            'arrows_per_end'=>6,'fee'=>0,'is_team'=>1,'standard_team_enabled'=>1,'mixed_team_enabled'=>1,
        ]]]);
        $this->actingAs($subscriber)->post(route('organizer.events.store'),$payload)->assertRedirect();

        $event=Event::where('name','混雙快速賽事')->firstOrFail();
        $this->assertDatabaseHas('event_groups',['event_id'=>$event->id,'is_team'=>1,'standard_team_enabled'=>1,'mixed_team_enabled'=>1]);
    }

    public function test_free_event_hides_and_ignores_registration_fee(): void
    {
        $organizer = $this->approvedOrganizer('免費主辦方');

        $this->actingAs($organizer)
            ->get(route('organizer.events.create'))
            ->assertOk()
            ->assertDontSee('報名費</label>', false);

        $payload = array_merge($this->eventPayload('免費無收費賽事'), ['submit_mode'=>'publish', 'groups'=>[0=>[
            'name'=>'反曲弓 30 公尺公開組', 'bow_type'=>'recurve', 'gender'=>'open',
            'distance'=>'30m', 'arrow_count'=>36, 'arrows_per_end'=>6, 'fee'=>1500,
        ]]]);

        $this->actingAs($organizer)->post(route('organizer.events.store'), $payload)->assertRedirect();

        $event = Event::where('name', '免費無收費賽事')->firstOrFail();
        $this->assertSame(0, $event->groups()->firstOrFail()->fee);
        $this->assertSame(16, $event->groups()->firstOrFail()->quota);
        $this->assertTrue($event->groups()->firstOrFail()->live_results_visible);
    }

    public function test_free_event_only_allows_preset_groups(): void
    {
        $organizer = $this->approvedOrganizer('模板主辦方');

        $this->actingAs($organizer)
            ->get(route('organizer.events.create'))
            ->assertOk()
            ->assertSee('id="free-bow"', false)
            ->assertSee('id="free-distance"', false)
            ->assertDontSee('id="event-template-grid"', false)
            ->assertDontSee('data-preset="custom"', false);

        $payload = array_merge($this->eventPayload('免費自訂賽事'), ['submit_mode'=>'publish', 'groups'=>[0=>[
            'name'=>'自訂 25 公尺組', 'bow_type'=>'barebow', 'gender'=>'open',
            'distance'=>'25m', 'arrow_count'=>36, 'arrows_per_end'=>6, 'fee'=>0,
        ]]]);

        $this->actingAs($organizer)
            ->post(route('organizer.events.store'), $payload)
            ->assertSessionHasErrors('groups');
        $this->assertDatabaseMissing('events', ['name'=>'免費自訂賽事']);
    }

    public function test_free_event_uses_one_date_and_cannot_be_created_as_multi_day(): void
    {
        $organizer = $this->approvedOrganizer('單日主辦方');

        $this->actingAs($organizer)
            ->get(route('organizer.events.create'))
            ->assertOk()
            ->assertSee('賽事日期')
            ->assertDontSee('開始日期')
            ->assertDontSee('結束日期');

        $payload = array_merge($this->eventPayload('免費單日賽事'), [
            'start_date'=>'2026-10-10', 'end_date'=>'2026-10-12', 'submit_mode'=>'publish',
            'free_reg_end_time'=>'09:30',
            'groups'=>[0=>[
                'name'=>'反曲弓 30 公尺公開組', 'bow_type'=>'recurve', 'gender'=>'open',
                'distance'=>'30m', 'arrow_count'=>36, 'arrows_per_end'=>6, 'fee'=>0,
            ]],
        ]);

        $this->actingAs($organizer)->post(route('organizer.events.store'), $payload)->assertRedirect();
        $event = Event::where('name', '免費單日賽事')->firstOrFail();
        $this->assertSame('2026-10-10', $event->start_date->toDateString());
        $this->assertSame('2026-10-10', $event->end_date->toDateString());
        $this->assertSame('2026-10-10 09:30', $event->reg_end->format('Y-m-d H:i'));
        $this->assertTrue($event->reg_start->between(now()->subMinute(), now()->addMinute()));
    }

    public function test_free_event_can_be_created_and_registered_on_the_same_day(): void
    {
        $this->travelTo(now()->startOfDay()->setHour(12));
        $organizer = $this->approvedOrganizer('當日賽主辦方');
        $payload = array_merge($this->eventPayload('當日建立賽事'), [
            'start_date'=>today()->toDateString(), 'end_date'=>today()->toDateString(),
            'free_reg_end_time'=>'23:59', 'submit_mode'=>'publish',
            'groups'=>[0=>[
                'name'=>'反曲弓 30 公尺公開組', 'bow_type'=>'recurve', 'gender'=>'open',
                'distance'=>'30m', 'arrow_count'=>36, 'arrows_per_end'=>6, 'fee'=>0,
            ]],
        ]);

        $this->actingAs($organizer)->post(route('organizer.events.store'), $payload)->assertRedirect();
        $event = Event::where('name', '當日建立賽事')->firstOrFail();
        $this->assertTrue($event->isPublished());
        $this->assertSame(today()->toDateString().' 23:59', $event->reg_end->format('Y-m-d H:i'));
        $this->travelBack();
    }

    public function test_paid_quick_event_derives_hidden_date_defaults_on_first_submission(): void
    {
        $organizer = $this->approvedOrganizer('付費快速建立主辦方');
        OrganizerSubscription::create([
            'user_id'=>$organizer->id,
            'plan_code'=>EventPlanCatalog::SUBSCRIPTION,
            'status'=>OrganizerSubscription::STATUS_ACTIVE,
            'starts_at'=>now(),
        ]);
        $startDate = now()->addMonth()->toDateString();

        $this->actingAs($organizer)->post(route('organizer.events.store'), [
            'name'=>'第一次送出即可建立',
            'start_date'=>$startDate,
            'end_date'=>now()->toDateString(),
            'mode'=>'outdoor',
            'organizer'=>'付費快速建立主辦方',
            'reg_start'=>now()->toDateTimeString(),
            'reg_end'=>now()->toDateTimeString(),
            'quick_date_defaults'=>1,
            'submit_mode'=>'draft',
        ])->assertRedirect()->assertSessionDoesntHaveErrors();

        $event = Event::where('name', '第一次送出即可建立')->firstOrFail();
        $this->assertSame($startDate, $event->end_date->toDateString());
        $this->assertSame($startDate.' 23:59', $event->reg_end->format('Y-m-d H:i'));
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
