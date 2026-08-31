<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventGroup;
use App\Models\User;
use App\Models\OrganizerProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_routes_use_random_uuid_instead_of_incrementing_id(): void
    {
        $event = Event::factory()->create();

        $this->assertNotNull($event->uuid);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $event->uuid
        );
        $this->assertSame(url('/events/'.$event->uuid), route('events.show', $event));

        $this->get(route('events.show', $event))->assertOk()->assertSee('返回上一頁');
        $this->get('/events/'.$event->id)->assertNotFound();
    }

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
        OrganizerProfile::create(['user_id'=>$user->id,'organization_name'=>'Archery Taiwan','organization_type'=>'association','contact_name'=>$user->name,'contact_email'=>$user->email,'contact_phone'=>'0912345678','application_reason'=>'既有主辦方','status'=>'approved','approved_at'=>now()]);

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

        $response->assertRedirect(route('organizer.events.show', $event));

        $this->assertNotNull($event);
        $this->assertDatabaseHas('events', [
            'name' => '全國巡迴賽',
            'organizer' => 'Archery Taiwan',
            'verified' => false,
            'status' => 'draft',
        ]);

        $this->assertDatabaseHas('event_staff', [
            'event_id' => $event->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'status' => 'active',
        ]);
    }

    public function test_index_uses_one_featured_stream_then_history_without_duplicates(): void
    {
        $ongoing = Event::factory()->create(['name'=>'今日進行中賽事', 'start_date'=>today(), 'end_date'=>today()]);
        $open = Event::factory()->create([
            'name'=>'目前開放報名賽事', 'start_date'=>today()->addDays(10), 'end_date'=>today()->addDays(10),
            'reg_start'=>now()->subDay(), 'reg_end'=>now()->addDays(3),
        ]);
        $upcoming = Event::factory()->create([
            'name'=>'尚未開放預告賽事', 'start_date'=>today()->addDays(5), 'end_date'=>today()->addDays(5),
            'reg_start'=>now()->addDay(), 'reg_end'=>now()->addDays(3),
        ]);
        $past = Event::factory()->create(['name'=>'最近歷史賽事', 'start_date'=>today()->subDays(3), 'end_date'=>today()->subDays(2)]);
        foreach ([$ongoing, $open, $upcoming, $past] as $event) {
            EventGroup::factory()->create(['event_id'=>$event->id]);
        }

        $response = $this->get(route('events.index'));
        $response->assertOk()
            ->assertSeeInOrder(['現在值得關注', '今日進行中賽事', '目前開放報名賽事', '尚未開放預告賽事', '歷史賽事', '最近歷史賽事'])
            ->assertSee('現正進行')
            ->assertSee('報名中')
            ->assertSee('即將開始');

        $this->assertSame(1, substr_count($response->getContent(), '今日進行中賽事'));
        $this->assertSame(1, substr_count($response->getContent(), '目前開放報名賽事'));
        $this->assertSame(1, substr_count($response->getContent(), '尚未開放預告賽事'));
    }
}
