<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventGroup;
use App\Models\EventRegistration;
use App\Models\EventScoreEntry;
use App\Models\User;
use App\Services\QualificationRankingSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventRankingSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_qualification_creates_versioned_immutable_seed_snapshot(): void
    {
        $event = Event::factory()->create();
        $group = EventGroup::factory()->create(['event_id'=>$event->id, 'name'=>'反曲弓公開組']);
        $users = User::factory()->count(4)->create();
        $registrations = collect();

        foreach ([50, 50, 40] as $index => $score) {
            $registration = EventRegistration::create([
                'event_id'=>$event->id,
                'event_group_id'=>$group->id,
                'user_id'=>$users[$index]->id,
                'name'=>$users[$index]->name,
                'email'=>$users[$index]->email,
                'status'=>'checked_in',
                'score_verified_at'=>now(),
                'result_published_at'=>now(),
                'result_status'=>'completed',
            ]);
            EventScoreEntry::create([
                'event_id'=>$event->id,
                'event_registration_id'=>$registration->id,
                'user_id'=>$users[$index]->id,
                'end_number'=>1,
                'scores'=>[$score],
                'end_total'=>$score,
            ]);
            $registrations->push($registration);
        }

        $dns = EventRegistration::create([
            'event_id'=>$event->id,
            'event_group_id'=>$group->id,
            'user_id'=>$users[3]->id,
            'name'=>$users[3]->name,
            'email'=>$users[3]->email,
            'status'=>'no_show',
            'score_verified_at'=>now(),
            'result_published_at'=>now(),
            'result_status'=>'dns',
        ]);

        $phase = $group->qualificationPhase()->firstOrFail();
        $phase->update(['status'=>'published', 'locked_at'=>now(), 'published_at'=>now()]);
        $service = app(QualificationRankingSnapshotService::class);

        $first = $service->capture($event, $group);
        $entries = $first->entries->keyBy('event_registration_id');
        $this->assertSame(1, $first->version);
        $this->assertSame([1, 1, 3], $registrations->map(fn ($registration) => $entries[$registration->id]->rank_position)->all());
        $this->assertSame([1, 2, 3], $registrations->map(fn ($registration) => $entries[$registration->id]->seed_position)->all());
        $this->assertTrue($entries[$registrations[0]->id]->requires_tiebreak);
        $this->assertTrue($entries[$registrations[1]->id]->requires_tiebreak);
        $this->assertNull($entries[$dns->id]->rank_position);
        $this->assertNull($entries[$dns->id]->seed_position);
        $this->assertFalse($entries[$dns->id]->is_eligible);

        $same = $service->capture($event, $group);
        $this->assertTrue($first->is($same));
        $this->assertSame(1, $phase->rankingSnapshots()->count());

        EventScoreEntry::where('event_registration_id', $registrations[2]->id)
            ->update(['scores'=>[60], 'end_total'=>60]);
        $users[2]->forceFill(['profile_completed_at'=>now()])->save();
        $this->actingAs($users[2])
            ->get(route('my-events.results.show', $registrations[2]))
            ->assertOk()
            ->assertSee('第 3 名');
        $second = $service->capture($event, $group);

        $this->assertSame(2, $second->version);
        $this->assertNotNull($first->fresh()->superseded_at);
        $this->assertSame('superseded', $first->fresh()->status);
        $this->assertSame($registrations[2]->id, $second->entries->first()->event_registration_id);
    }
}
