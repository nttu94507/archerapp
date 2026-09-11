<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use App\Support\EventPlanCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventTrialTest extends TestCase
{
    use RefreshDatabase;

    public function test_mvp_mode_gives_every_user_free_personal_elimination_without_consuming_trial(): void
    {
        config()->set('product.mvp_mode', true);
        $user = User::factory()->create();
        $payload = $this->payload('免費 MVP 賽事');
        unset($payload['creation_plan']);

        $this->actingAs($user)->post(route('organizer.events.store'), $payload)->assertRedirect();

        $event = Event::where('name', '免費 MVP 賽事')->firstOrFail();
        $this->assertSame(EventPlanCatalog::MVP, $event->plan_code);
        $this->assertTrue($event->hasPlanFeature('individual_elimination'));
        $this->assertFalse($event->hasPlanFeature('team_competition'));
        $this->assertSame(6, $event->planLimit('groups'));
        $this->assertDatabaseCount('event_trial_usages', 0);
        $this->assertSame(2, $user->remainingEventTrials());
    }

    public function test_user_can_create_two_trial_events_and_third_is_rejected(): void
    {
        $user = User::factory()->create();

        foreach ([1, 2] as $number) {
            $this->actingAs($user)->post(route('organizer.events.store'), $this->payload('試用賽事 '.$number))
                ->assertRedirect();
        }

        $this->assertSame(0, $user->remainingEventTrials());
        $this->assertDatabaseCount('event_trial_usages', 2);
        $this->assertSame(2, Event::where('plan_code', EventPlanCatalog::TRIAL)->count());

        $this->actingAs($user)->post(route('organizer.events.store'), $this->payload('第三場試用'))
            ->assertSessionHasErrors('creation_plan');
        $this->assertDatabaseMissing('events', ['name'=>'第三場試用']);
    }

    public function test_trial_limits_groups_and_includes_advanced_features(): void
    {
        $user = User::factory()->create();
        $payload = $this->payload('超過組別');
        $payload['groups'] = array_fill(0, 3, $payload['groups'][0]);

        $this->actingAs($user)->post(route('organizer.events.store'), $payload)
            ->assertSessionHasErrors('groups');

        $this->assertTrue(EventPlanCatalog::features(EventPlanCatalog::TRIAL)['team_competition']);
        $this->assertTrue(EventPlanCatalog::features(EventPlanCatalog::TRIAL)['individual_elimination']);
        $this->assertSame(32, EventPlanCatalog::limits(EventPlanCatalog::TRIAL)['athletes']);
    }

    private function payload(string $name): array
    {
        $date = now()->addDays(5)->format('Y-m-d');

        return [
            'creation_plan'=>'trial', 'name'=>$name, 'start_date'=>$date, 'end_date'=>$date,
            'mode'=>'outdoor', 'organizer'=>'測試主辦', 'visibility'=>'unlisted',
            'reg_start'=>now()->format('Y-m-d H:i:s'), 'reg_end'=>$date.' 23:59:00',
            'submit_mode'=>'publish', 'check_in_enabled'=>1,
            'groups'=>[[
                'name'=>'反曲弓 70 公尺公開組', 'bow_type'=>'recurve', 'gender'=>'open',
                'distance'=>'70m', 'arrow_count'=>72, 'arrows_per_end'=>6, 'quota'=>32, 'fee'=>0,
            ]],
        ];
    }
}
