<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventGroup;
use App\Models\EventRegistration;
use App\Models\EventScoringSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FillDemoEventScoresCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_fills_every_target_and_completes_qualification_session(): void
    {
        $event = Event::factory()->create(['mode'=>'outdoor']);
        $group = EventGroup::factory()->create([
            'event_id'=>$event->id, 'arrow_count'=>72, 'arrows_per_end'=>6,
        ]);
        $phase = $group->qualificationPhase()->firstOrFail();
        $session = EventScoringSession::create([
            'event_id'=>$event->id, 'event_group_id'=>$group->id, 'event_phase_id'=>$phase->id,
            'name'=>'測試排名賽', 'total_arrows'=>72, 'arrows_per_end'=>6,
            'athletes_per_target'=>2, 'status'=>'ready',
        ]);
        $registrations = collect(range(1, 4))->map(function (int $index) use ($event, $group): EventRegistration {
            $user = User::factory()->create();

            return EventRegistration::create([
                'event_id'=>$event->id, 'event_group_id'=>$group->id, 'user_id'=>$user->id,
                'name'=>'測試選手 '.$index, 'email'=>$user->email, 'status'=>'registered',
            ]);
        });
        foreach ($registrations->chunk(2) as $targetIndex => $members) {
            $target = $session->targets()->create([
                'target_number'=>$targetIndex + 1, 'access_token'=>(string) Str::uuid(),
                'device_pin'=>'123456', 'status'=>'ready',
            ]);
            foreach ($members->values() as $position => $registration) {
                $target->assignments()->create([
                    'event_registration_id'=>$registration->id, 'position'=>chr(65 + $position),
                ]);
            }
        }

        $this->artisan('demo:fill-scores', ['event'=>$event->uuid])->assertSuccessful();

        $this->assertSame(48, $event->registrations()->withCount('scoreEntries')->get()->sum('score_entries_count'));
        $this->assertSame(2, $session->targets()->where('status', 'completed')->count());
        $this->assertSame('completed', $session->fresh()->status);
        $this->assertSame('completed', $phase->fresh()->status);
        $totals = $registrations->map(fn ($registration) => $registration->scoreEntries()->sum('end_total'));
        $this->assertCount(4, $totals->unique());
    }
}
