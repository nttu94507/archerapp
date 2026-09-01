<?php

namespace Tests\Feature;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeedDemoEventCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_ready_to_test_event_groups_and_registrations(): void
    {
        $this->artisan('demo:seed-event', [
            '--owner'=>'command.owner@example.test',
            '--groups'=>2,
            '--athletes'=>4,
            '--mode'=>'outdoor',
        ])->assertSuccessful();

        $event = Event::with('groups.registrations', 'staff')->sole();
        $this->assertSame('event_pass', $event->plan_code);
        $this->assertFalse($event->check_in_enabled);
        $this->assertCount(2, $event->groups);
        $this->assertSame(8, $event->registrations()->count());
        $this->assertSame(8, $event->registrations()->where('status', 'registered')->count());
        $this->assertSame(1, $event->staff()->where('role', 'owner')->count());
    }

    public function test_free_command_forces_one_single_round_group(): void
    {
        $this->artisan('demo:seed-event', [
            '--owner'=>'free.command.owner@example.test',
            '--groups'=>4,
            '--athletes'=>3,
            '--mode'=>'indoor',
            '--free'=>true,
        ])->assertSuccessful();

        $event = Event::with('groups')->sole();
        $this->assertSame('free', $event->plan_code);
        $this->assertCount(1, $event->groups);
        $this->assertSame(30, $event->groups->first()->arrow_count);
        $this->assertSame(3, $event->groups->first()->arrows_per_end);
    }
}
