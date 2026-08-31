<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\OrganizerProfile;
use App\Models\OrganizerSubscription;
use App\Models\User;
use App\Support\EventPlanCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_unlisted_event_is_hidden_from_listing_but_available_by_uuid_link(): void
    {
        $event = Event::factory()->create([
            'name' => '連結限定校內賽',
            'visibility' => 'unlisted',
        ]);

        $this->get(route('events.index'))->assertOk()->assertDontSee($event->name);
        $this->get(route('events.show', $event))->assertOk()->assertSee($event->name);
    }

    public function test_free_organizer_cannot_create_an_unlisted_event(): void
    {
        $organizer = $this->approvedOrganizer();

        $this->actingAs($organizer)
            ->post(route('organizer.events.store'), $this->payload('免費隱藏賽事'))
            ->assertSessionHasErrors('visibility');

        $this->assertDatabaseMissing('events', ['name' => '免費隱藏賽事']);
    }

    public function test_subscriber_can_create_an_unlisted_event(): void
    {
        $organizer = $this->approvedOrganizer();
        OrganizerSubscription::create([
            'user_id' => $organizer->id,
            'plan_code' => EventPlanCatalog::SUBSCRIPTION,
            'status' => OrganizerSubscription::STATUS_ACTIVE,
            'starts_at' => now()->subMinute(),
        ]);

        $this->actingAs($organizer)
            ->post(route('organizer.events.store'), $this->payload('訂閱隱藏賽事'))
            ->assertRedirect();

        $this->assertDatabaseHas('events', [
            'name' => '訂閱隱藏賽事',
            'visibility' => 'unlisted',
            'plan_code' => EventPlanCatalog::SUBSCRIPTION,
        ]);
    }

    private function approvedOrganizer(): User
    {
        $user = User::factory()->create();
        OrganizerProfile::create([
            'user_id' => $user->id,
            'organization_name' => '校園弓社',
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

    private function payload(string $name): array
    {
        return [
            'name' => $name,
            'start_date' => now()->addMonth()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'mode' => 'outdoor',
            'organizer' => '校園弓社',
            'visibility' => 'unlisted',
        ];
    }
}
