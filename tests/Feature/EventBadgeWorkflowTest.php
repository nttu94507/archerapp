<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventBadge;
use App\Models\EventBadgeClaim;
use App\Models\EventGroup;
use App\Models\EventRegistration;
use App\Models\EventStaff;
use App\Models\User;
use App\Models\UserEventBadge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventBadgeWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_owner_can_create_badge_but_unrelated_user_cannot_manage_it(): void
    {
        [$owner, $event] = $this->eventWithOwner();

        $response = $this->actingAs($owner)->post(route('organizer.events.badges.store', $event), [
            'name' => '公開賽參賽者',
            'description' => '完成現場報到',
            'type' => 'participant',
            'eligibility' => 'checked_in',
            'claim_enabled' => '1',
        ]);

        $badge = EventBadge::firstOrFail();
        $response->assertRedirect(route('organizer.events.badges.show', [$event, $badge]));
        $this->assertTrue($badge->claim_enabled);

        $this->actingAs(User::factory()->create())
            ->get(route('organizer.events.badges.show', [$event, $badge]))
            ->assertForbidden();
    }

    public function test_registered_member_can_scan_and_submit_only_one_claim(): void
    {
        [$owner, $event] = $this->eventWithOwner();
        $member = User::factory()->create();
        $this->register($event, $member, 'registered');
        $badge = EventBadge::create([
            'event_id' => $event->id,
            'created_by' => $owner->id,
            'name' => '參賽者',
            'type' => 'participant',
            'eligibility' => 'registered',
            'claim_enabled' => true,
        ]);

        $this->actingAs($member)->post(route('badge-claims.store', $badge->claim_token))->assertSessionHas('success');
        $this->actingAs($member)->post(route('badge-claims.store', $badge->claim_token))->assertSessionHas('success');

        $this->assertDatabaseCount('event_badge_claims', 1);
        $this->assertDatabaseHas('event_badge_claims', [
            'event_badge_id' => $badge->id,
            'user_id' => $member->id,
            'status' => 'pending',
            'is_eligible' => true,
        ]);
    }

    public function test_unregistered_member_is_sent_to_manual_review(): void
    {
        [$owner, $event] = $this->eventWithOwner();
        $member = User::factory()->create();
        $badge = EventBadge::create([
            'event_id' => $event->id,
            'created_by' => $owner->id,
            'name' => '參賽者',
            'type' => 'participant',
            'eligibility' => 'registered',
            'claim_enabled' => true,
        ]);

        $this->actingAs($member)->post(route('badge-claims.store', $badge->claim_token));

        $this->assertDatabaseHas('event_badge_claims', [
            'event_badge_id' => $badge->id,
            'user_id' => $member->id,
            'status' => 'needs_review',
            'is_eligible' => false,
        ]);
    }

    public function test_owner_can_bulk_approve_claim_and_badge_appears_on_member_profile(): void
    {
        [$owner, $event] = $this->eventWithOwner();
        $member = User::factory()->create();
        $badge = EventBadge::create([
            'event_id' => $event->id,
            'created_by' => $owner->id,
            'name' => '完賽 Badge',
            'type' => 'finisher',
            'eligibility' => 'registered',
            'claim_enabled' => true,
        ]);
        $claim = EventBadgeClaim::create([
            'event_badge_id' => $badge->id,
            'user_id' => $member->id,
            'status' => 'pending',
            'is_eligible' => true,
            'eligibility_note' => '已有有效報名',
        ]);

        $this->actingAs($owner)->post(route('organizer.events.badges.review', [$event, $badge]), [
            'action' => 'approve',
            'claim_ids' => [$claim->id],
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('user_event_badges', [
            'event_badge_id' => $badge->id,
            'user_id' => $member->id,
            'revoked_at' => null,
        ]);
        $this->actingAs($member)->get(route('member-profile.index'))->assertOk()->assertSee('完賽 Badge');
    }

    public function test_platform_admin_can_disable_badge_and_revoke_award_with_audit_reason(): void
    {
        [$owner, $event] = $this->eventWithOwner();
        $admin = User::factory()->create(['is_admin' => true]);
        $member = User::factory()->create();
        $badge = EventBadge::create([
            'event_id' => $event->id,
            'created_by' => $owner->id,
            'name' => '測試 Badge',
            'type' => 'special',
            'eligibility' => 'any',
            'claim_enabled' => true,
        ]);
        $award = UserEventBadge::create([
            'event_badge_id' => $badge->id,
            'user_id' => $member->id,
            'awarded_by' => $owner->id,
            'awarded_at' => now(),
        ]);

        $this->actingAs($admin)->patch(route('admin.badges.toggle', $badge));
        $this->assertFalse($badge->fresh()->is_active);
        $this->assertFalse($badge->fresh()->claim_enabled);

        $this->actingAs($admin)->patch(route('admin.badge-awards.revoke', $award), ['reason' => '主辦方誤發']);
        $this->assertNotNull($award->fresh()->revoked_at);
        $this->assertSame('主辦方誤發', $award->fresh()->revoked_reason);
        $this->actingAs($member)->get(route('member-profile.index'))->assertDontSee('測試 Badge');
    }

    /** @return array{User,Event} */
    private function eventWithOwner(): array
    {
        $owner = User::factory()->create();
        $event = Event::factory()->create();
        EventStaff::create([
            'event_id' => $event->id,
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
            'invited_by' => $owner->id,
            'invited_at' => now(),
            'accepted_at' => now(),
        ]);

        return [$owner, $event];
    }

    private function register(Event $event, User $user, string $status): EventRegistration
    {
        $group = EventGroup::factory()->create(['event_id' => $event->id]);

        return EventRegistration::create([
            'event_id' => $event->id,
            'event_group_id' => $group->id,
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => $status,
        ]);
    }
}
