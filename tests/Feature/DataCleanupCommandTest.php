<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventBadge;
use App\Models\EventBadgeClaim;
use App\Models\EventGroup;
use App\Models\EventRegistration;
use App\Models\User;
use App\Models\UserEventBadge;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DataCleanupCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('rounds')) {
            Schema::create('rounds', function (Blueprint $table): void {
                $table->id();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('archers')) {
            Schema::create('archers', function (Blueprint $table): void {
                $table->id();
                $table->timestamps();
            });
        }
    }

    public function test_event_cleanup_previews_then_clears_only_selected_event_data(): void
    {
        Storage::fake('public');

        $owner = User::factory()->create();
        $member = User::factory()->create();
        $selectedEvent = Event::factory()->create();
        $otherEvent = Event::factory()->create();
        $selectedGroup = EventGroup::factory()->create(['event_id' => $selectedEvent->id]);
        $otherGroup = EventGroup::factory()->create(['event_id' => $otherEvent->id]);
        $selectedBadge = $this->makeBadge($owner, $selectedEvent, 'badges/selected.png');
        $otherBadge = $this->makeBadge($owner, $otherEvent, 'badges/other.png');

        EventRegistration::create([
            'event_id' => $selectedEvent->id,
            'event_group_id' => $selectedGroup->id,
            'user_id' => $member->id,
            'name' => $member->name,
            'email' => $member->email,
            'status' => 'registered',
        ]);
        EventRegistration::create([
            'event_id' => $otherEvent->id,
            'event_group_id' => $otherGroup->id,
            'user_id' => $member->id,
            'name' => $member->name,
            'email' => $member->email,
            'status' => 'registered',
        ]);
        $selectedAward = $this->award($selectedBadge, $member, $owner);
        $otherAward = $this->award($otherBadge, $member, $owner);

        Storage::disk('public')->put('badges/selected.png', 'selected');
        Storage::disk('public')->put('badges/other.png', 'other');

        $this->artisan('data:clear-events', ['--event' => [$selectedEvent->id]])
            ->expectsOutputToContain('目前是預覽模式')
            ->assertSuccessful();

        $this->assertDatabaseHas('events', ['id' => $selectedEvent->id]);
        $this->assertDatabaseHas('user_event_badges', ['id' => $selectedAward->id]);

        $this->artisan('data:clear-events', [
            '--event' => [$selectedEvent->id],
            '--execute' => true,
            '--yes' => true,
        ])->expectsOutputToContain('已清除 1 場賽事')->assertSuccessful();

        $this->assertDatabaseMissing('events', ['id' => $selectedEvent->id]);
        $this->assertDatabaseMissing('event_badges', ['id' => $selectedBadge->id]);
        $this->assertDatabaseMissing('user_event_badges', ['id' => $selectedAward->id]);
        $this->assertDatabaseHas('events', ['id' => $otherEvent->id]);
        $this->assertDatabaseHas('event_badges', ['id' => $otherBadge->id]);
        $this->assertDatabaseHas('user_event_badges', ['id' => $otherAward->id]);
        Storage::disk('public')->assertMissing('badges/selected.png');
        Storage::disk('public')->assertExists('badges/other.png');
    }

    public function test_badge_cleanup_clears_claims_and_member_awards_but_keeps_other_badges(): void
    {
        Storage::fake('public');

        $owner = User::factory()->create();
        $member = User::factory()->create();
        $event = Event::factory()->create();
        $selectedBadge = $this->makeBadge($owner, $event, 'badges/selected.png');
        $otherBadge = $this->makeBadge($owner, $event, 'badges/other.png');
        $claim = EventBadgeClaim::create([
            'event_badge_id' => $selectedBadge->id,
            'user_id' => $member->id,
            'status' => 'pending',
            'is_eligible' => true,
        ]);
        $selectedAward = $this->award($selectedBadge, $member, $owner);
        $otherAward = $this->award($otherBadge, $member, $owner);

        Storage::disk('public')->put('badges/selected.png', 'selected');
        Storage::disk('public')->put('badges/other.png', 'other');

        $this->artisan('data:clear-badges', ['--badge' => [$selectedBadge->id]])
            ->expectsOutputToContain('目前是預覽模式')
            ->assertSuccessful();

        $this->assertDatabaseHas('event_badge_claims', ['id' => $claim->id]);
        $this->assertDatabaseHas('user_event_badges', ['id' => $selectedAward->id]);

        $this->artisan('data:clear-badges', [
            '--badge' => [$selectedBadge->id],
            '--execute' => true,
            '--yes' => true,
        ])->expectsOutputToContain('已清除 1 個 Badge')->assertSuccessful();

        $this->assertDatabaseMissing('event_badges', ['id' => $selectedBadge->id]);
        $this->assertDatabaseMissing('event_badge_claims', ['id' => $claim->id]);
        $this->assertDatabaseMissing('user_event_badges', ['id' => $selectedAward->id]);
        $this->assertDatabaseHas('event_badges', ['id' => $otherBadge->id]);
        $this->assertDatabaseHas('user_event_badges', ['id' => $otherAward->id]);
        Storage::disk('public')->assertMissing('badges/selected.png');
        Storage::disk('public')->assertExists('badges/other.png');
    }

    public function test_cleanup_refuses_unknown_ids_without_deleting_anything(): void
    {
        $event = Event::factory()->create();

        $this->artisan('data:clear-events', [
            '--event' => [$event->id, 999999],
            '--execute' => true,
            '--yes' => true,
        ])->expectsOutputToContain('包含不存在的賽事 ID')->assertFailed();

        $this->assertDatabaseHas('events', ['id' => $event->id]);
    }

    private function makeBadge(User $owner, Event $event, string $iconPath): EventBadge
    {
        return EventBadge::create([
            'event_id' => $event->id,
            'created_by' => $owner->id,
            'name' => fake()->unique()->words(2, true),
            'icon_path' => $iconPath,
            'type' => 'special',
            'eligibility' => 'any',
            'award_rule' => 'manual',
        ]);
    }

    private function award(EventBadge $badge, User $member, User $owner): UserEventBadge
    {
        return UserEventBadge::create([
            'event_badge_id' => $badge->id,
            'user_id' => $member->id,
            'awarded_by' => $owner->id,
            'awarded_at' => now(),
        ]);
    }
}
