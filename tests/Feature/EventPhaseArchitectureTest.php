<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventPhaseArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_group_automatically_receives_a_qualification_phase(): void
    {
        $event = Event::factory()->create();
        $group = EventGroup::factory()->create([
            'event_id'=>$event->id,
            'name'=>'男子反曲弓公開組',
            'arrow_count'=>36,
            'arrows_per_end'=>6,
        ]);

        $phase = $group->qualificationPhase()->firstOrFail();

        $this->assertSame($event->id, $phase->event_id);
        $this->assertSame('qualification', $phase->type);
        $this->assertSame('cumulative', $phase->scoring_mode);
        $this->assertSame('draft', $phase->status);
        $this->assertSame(36, $phase->total_arrows);
        $this->assertSame(6, $phase->arrows_per_end);
        $this->assertNotNull($phase->uuid);
    }

    public function test_group_settings_sync_to_unlocked_phase_but_not_after_locking(): void
    {
        $group = EventGroup::factory()->create([
            'name'=>'公開組',
            'arrow_count'=>36,
            'arrows_per_end'=>6,
        ]);

        $group->update(['name'=>'公開組 A', 'arrow_count'=>72]);
        $phase = $group->qualificationPhase()->firstOrFail();
        $this->assertSame('公開組 A 排名賽', $phase->name);
        $this->assertSame(72, $phase->total_arrows);

        $phase->update(['locked_at'=>now()]);
        $group->update(['name'=>'不應覆寫', 'arrow_count'=>36]);
        $phase->refresh();

        $this->assertSame('公開組 A 排名賽', $phase->name);
        $this->assertSame(72, $phase->total_arrows);
    }
}
