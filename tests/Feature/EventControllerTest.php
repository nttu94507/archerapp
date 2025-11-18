<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_supports_keyword_mode_and_verified_filters(): void
    {
        $user = User::factory()->create(['profile_completed_at' => now()]);

        $matching = Event::factory()->create([
            'name' => '台北城市盃',
            'mode' => 'outdoor',
            'verified' => true,
            'organizer' => 'Taipei Archers',
        ]);

        Event::factory()->create([
            'name' => '高雄室內挑戰賽',
            'mode' => 'indoor',
            'verified' => false,
        ]);

        $response = $this->actingAs($user)
            ->get(route('events.index', [
                'q' => '台北',
                'mode' => 'outdoor',
                'verified' => 1,
            ]));

        $response->assertOk();
        $response->assertSee($matching->name);
        $response->assertDontSee('高雄室內挑戰賽');
    }

    public function test_store_creates_event_and_assigns_owner_as_staff(): void
    {
        $user = User::factory()->create(['profile_completed_at' => now()]);

        $payload = [
            'name' => '全國巡迴賽',
            'start_date' => '2025-03-01',
            'end_date' => '2025-03-02',
            'mode' => 'indoor',
            'verified' => 1,
            'level' => 'regional',
            'organizer' => 'Archery Taiwan',
            'reg_start' => now()->subDay()->format('Y-m-d H:i:s'),
            'reg_end' => now()->addDays(5)->format('Y-m-d H:i:s'),
            'venue' => '台北體育場',
            'map_link' => 'https://example.com/map',
        ];

        $response = $this->actingAs($user)->post(route('events.store'), $payload);

        $event = Event::first();

        $response->assertRedirect(route('events.groups.create', $event));

        $this->assertNotNull($event);
        $this->assertDatabaseHas('events', [
            'name' => '全國巡迴賽',
            'organizer' => 'Archery Taiwan',
            'verified' => true,
        ]);

        $this->assertDatabaseHas('event_staff', [
            'event_id' => $event->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'status' => 'active',
        ]);
    }
}
