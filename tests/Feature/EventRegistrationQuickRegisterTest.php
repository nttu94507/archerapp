<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventGroup;
use App\Models\EventRegistration;
use App\Models\EventStaff;
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

    public function test_user_sees_registration_confirmation_before_submitting(): void
    {
        $user = User::factory()->create(['name'=>'確認選手']);
        $event = Event::factory()->create([
            'name'=>'確認流程公開賽', 'reg_start'=>now()->subDay(), 'reg_end'=>now()->addDay(),
        ]);
        $group = EventGroup::factory()->create([
            'event_id'=>$event->id, 'name'=>'反曲公開組', 'fee'=>500, 'quota'=>20,
        ]);

        $this->actingAs($user)
            ->get(route('events.registration.confirm', [$event, $group]))
            ->assertOk()
            ->assertSee('確認並完成報名')
            ->assertSee('反曲公開組')
            ->assertSee('NT$ 500');

        $this->assertDatabaseCount('event_registrations', 0);
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

    public function test_admin_can_register_when_group_registration_window_is_open(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $event = Event::factory()->create(['reg_start' => null, 'reg_end' => null]);
        $group = EventGroup::factory()->create([
            'event_id' => $event->id,
            'reg_start' => now()->subHour(),
            'reg_end' => now()->addHour(),
            'quota' => 5,
        ]);

        $this->actingAs($admin)->get(route('events.show', $event))
            ->assertOk()->assertSee('立即報名');
        $this->actingAs($admin)->post(route('events.quick_register', [$event, $group]))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('event_registrations', [
            'event_id' => $event->id,
            'event_group_id' => $group->id,
            'user_id' => $admin->id,
            'status' => 'registered',
        ]);
    }

    public function test_event_registration_status_uses_effective_group_windows(): void
    {
        $event = Event::factory()->create([
            'reg_start' => now()->subDays(3), 'reg_end' => now()->subDays(2),
        ]);
        EventGroup::factory()->create([
            'event_id'=>$event->id, 'reg_start'=>now()->subHour(), 'reg_end'=>now()->addHour(),
        ]);
        EventGroup::factory()->create([
            'event_id'=>$event->id, 'reg_start'=>null, 'reg_end'=>null,
        ]);

        $this->assertSame('open', $event->fresh()->registrationStatus());
        $this->assertTrue($event->groups()->first()->isRegistrationOpen());
    }

    public function test_custom_group_registration_window_requires_both_dates(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->create();
        EventStaff::create(['event_id'=>$event->id,'user_id'=>$owner->id,'role'=>'owner','status'=>'active','invited_by'=>$owner->id]);

        $this->actingAs($owner)->post(route('events.groups.store',$event), [
            'groups'=>[0=>[
                'name'=>'測試組','gender'=>'open','arrow_count'=>36,'use_custom_reg_window'=>1,
                'reg_start'=>now()->toDateTimeString(),
            ]],
        ])->assertSessionHasErrors('groups.0.reg_end');

        $this->assertDatabaseCount('event_groups',0);
    }
}
