<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventGroup;
use App\Models\EventRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventRegistrationQuickRegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_quick_register_during_active_window(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'reg_start' => now()->subDay(),
            'reg_end' => now()->addDay(),
        ]);
        $group = EventGroup::factory()->create([
            'event_id' => $event->id,
            'quota' => 5,
        ]);

        $response = $this->actingAs($user)->post(route('events.quick_register', [$event, $group]));

        $response->assertRedirect(route('events.show', $event));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('event_registrations', [
            'event_id' => $event->id,
            'event_group_id' => $group->id,
            'user_id' => $user->id,
            'status' => 'registered',
        ]);
    }

    public function test_quick_register_validates_registration_window(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'reg_start' => now()->addDay(),
            'reg_end' => now()->addDays(2),
        ]);
        $group = EventGroup::factory()->create(['event_id' => $event->id]);

        $response = $this->from(route('events.show', $event))
            ->actingAs($user)
            ->post(route('events.quick_register', [$event, $group]));

        $response->assertRedirect(route('events.show', $event));
        $response->assertSessionHas('error');

        $this->assertDatabaseCount('event_registrations', 0);
    }

    public function test_quick_register_honors_group_quota(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'reg_start' => now()->subDay(),
            'reg_end' => now()->addDay(),
        ]);
        $group = EventGroup::factory()->create([
            'event_id' => $event->id,
            'quota' => 1,
        ]);

        EventRegistration::create([
            'event_id' => $event->id,
            'event_group_id' => $group->id,
            'user_id' => User::factory()->create()->id,
            'name' => '已報名選手',
            'email' => 'registered@example.com',
            'status' => 'registered',
        ]);

        $response = $this->from(route('events.show', $event))
            ->actingAs($user)
            ->post(route('events.quick_register', [$event, $group]));

        $response->assertRedirect(route('events.show', $event));
        $response->assertSessionHas('error');

        $this->assertDatabaseCount('event_registrations', 1);
    }
}
