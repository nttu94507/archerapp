<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventGroup;
use App\Models\EventStaff;
use App\Models\User;
use App\Support\EventPlanCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterDemoEventAthletesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_registers_each_group_and_creates_standard_and_mixed_teams(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->create([
            'mode'=>'outdoor', 'check_in_enabled'=>false,
            'plan_code'=>EventPlanCatalog::EVENT_PASS,
            'plan_features_snapshot'=>EventPlanCatalog::features(EventPlanCatalog::EVENT_PASS),
            'plan_limits_snapshot'=>EventPlanCatalog::limits(EventPlanCatalog::EVENT_PASS),
        ]);
        EventStaff::create([
            'event_id'=>$event->id, 'user_id'=>$owner->id, 'role'=>'owner',
            'status'=>'active', 'invited_by'=>$owner->id,
        ]);
        $group = EventGroup::factory()->create([
            'event_id'=>$event->id, 'gender'=>'open', 'fee'=>500,
            'is_team'=>true, 'standard_team_enabled'=>true, 'mixed_team_enabled'=>true,
        ]);

        $this->artisan('demo:register-event', [
            'event'=>$event->uuid, '--athletes'=>8, '--with-teams'=>true,
        ])->assertSuccessful();

        $this->assertSame(24, $group->registrations()->count());
        $this->assertSame(24, $group->registrations()->where('payment_status', 'paid')->count());
        $this->assertDatabaseHas('event_teams', ['event_group_id'=>$group->id, 'team_format'=>'standard', 'status'=>'full']);
        $this->assertDatabaseHas('event_teams', ['event_group_id'=>$group->id, 'team_format'=>'mixed', 'status'=>'full']);
        $this->assertSame(4, $group->eventTeams()->where('team_format', 'standard')->count());
        $this->assertSame(4, $group->eventTeams()->where('team_format', 'mixed')->count());
        $this->assertSame(24, \DB::table('event_team_members')->where('event_group_id', $group->id)->count());
    }
}
