<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventGroup;
use App\Models\EventRegistration;
use App\Models\EventScoreEntry;
use App\Models\EventStaff;
use App\Models\User;
use App\Services\IndividualEliminationBracketService;
use App\Services\QualificationRankingSnapshotService;
use App\Services\RecurveSetMatchService;
use App\Services\CompoundCumulativeMatchService;
use App\Services\EliminationShootOffService;
use App\Support\EventPlanCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EventEliminationBracketTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_event_builds_standard_seeded_individual_bracket_and_bronze_match(): void
    {
        [$event, $group] = $this->publishedRanking(range(80, 73), 'recurve', true);

        $bracket = app(IndividualEliminationBracketService::class)->create($event, $group, 8, true);
        $firstRound = $bracket->matches()->where('match_type', 'main')->where('round_number', 1)->orderBy('position')->get();

        $this->assertSame([[1, 8], [5, 4], [3, 6], [7, 2]], $firstRound
            ->map(fn ($match) => [$match->participant_one_seed, $match->participant_two_seed])->all());
        $this->assertSame(7, $bracket->matches()->where('match_type', 'main')->count());
        $this->assertSame(1, $bracket->matches()->where('match_type', 'bronze')->count());
        $this->assertSame('set', $bracket->scoring_mode);
        $this->assertSame('elimination', $bracket->phase->type);
        $this->assertSame('ready', $bracket->phase->status);
        $this->assertNotNull($bracket->phase->locked_at);
    }

    public function test_compound_bracket_uses_cumulative_mode_and_advances_first_round_byes(): void
    {
        [$event, $group] = $this->publishedRanking(range(60, 55), 'compound', true);

        $bracket = app(IndividualEliminationBracketService::class)->create($event, $group, 8, false);
        $byes = $bracket->matches()->where('match_type', 'main')->where('round_number', 1)->where('status', 'walkover')->get();
        $semifinals = $bracket->matches()->where('match_type', 'main')->where('round_number', 2)->get();

        $this->assertSame('cumulative', $bracket->scoring_mode);
        $this->assertCount(2, $byes);
        $this->assertEqualsCanonicalizing([1, 2], $byes->pluck('winner_registration_id')->map(function ($registrationId) use ($bracket) {
            return $bracket->rankingSnapshot->entries->firstWhere('event_registration_id', $registrationId)->seed_position;
        })->all());
        $this->assertSame(2, $semifinals->filter(fn ($match) => $match->participant_one_registration_id || $match->participant_two_registration_id)->count());
        $this->assertSame(0, $bracket->matches()->where('match_type', 'bronze')->count());
    }

    public function test_bracket_is_blocked_when_selected_seeds_still_require_tiebreak(): void
    {
        [$event, $group] = $this->publishedRanking([50, 50, 40, 30], 'recurve', true);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('種子範圍內仍有同分選手');
        app(IndividualEliminationBracketService::class)->create($event, $group, 4);
    }

    public function test_free_event_cannot_create_individual_elimination_bracket(): void
    {
        [$event, $group] = $this->publishedRanking([40, 30, 20, 10], 'recurve', false);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('免費方案僅提供排名賽');
        app(IndividualEliminationBracketService::class)->create($event, $group, 4);
    }

    public function test_recurve_match_awards_two_one_zero_set_points_and_stops_at_six(): void
    {
        [$event, $group] = $this->publishedRanking([80, 70, 60, 50], 'recurve', true);
        $bracket = app(IndividualEliminationBracketService::class)->create($event, $group, 4, true);
        $match = $bracket->matches()->where('match_type', 'main')->where('round_number', 1)->where('position', 1)->firstOrFail();
        $service = app(RecurveSetMatchService::class);

        $service->recordSet($match, ['X', '10', '9'], ['9', '9', '9'], null);
        $service->recordSet($match, ['10', '10', '8'], ['9', '9', '9'], null);
        $completed = $service->recordSet($match, ['10', '10', '10'], ['8', '8', '8'], null);

        $this->assertSame('completed', $completed->status);
        $this->assertSame(6, $completed->participant_one_set_points);
        $this->assertSame(0, $completed->participant_two_set_points);
        $this->assertSame($completed->participant_one_registration_id, $completed->winner_registration_id);
        $this->assertSame($completed->participant_two_registration_id, $completed->loser_registration_id);
        $this->assertSame($completed->winner_registration_id, $completed->nextMatch->participant_one_registration_id);
        $this->assertSame(2, $completed->sets->first()->participant_one_set_points);
    }

    public function test_five_tied_sets_stop_match_for_shoot_off_without_declaring_winner(): void
    {
        [$event, $group] = $this->publishedRanking([40, 30, 20, 10], 'recurve', true);
        $bracket = app(IndividualEliminationBracketService::class)->create($event, $group, 4, false);
        $match = $bracket->matches()->where('round_number', 1)->where('position', 1)->firstOrFail();
        $service = app(RecurveSetMatchService::class);

        foreach (range(1, 5) as $_) {
            $match = $service->recordSet($match, ['10', '9', '8'], ['9', '9', '9'], null);
        }

        $this->assertSame('awaiting_shoot_off', $match->status);
        $this->assertSame(5, $match->participant_one_set_points);
        $this->assertSame(5, $match->participant_two_set_points);
        $this->assertNull($match->winner_registration_id);
        $this->assertNull($match->completed_at);
        $this->assertCount(5, $match->sets);
    }

    public function test_semifinal_loser_is_placed_into_bronze_match(): void
    {
        [$event, $group] = $this->publishedRanking([40, 30, 20, 10], 'recurve', true);
        $bracket = app(IndividualEliminationBracketService::class)->create($event, $group, 4, true);
        $match = $bracket->matches()->where('round_number', 1)->where('position', 1)->firstOrFail();
        $loserId = $match->participant_two_registration_id;
        $service = app(RecurveSetMatchService::class);
        foreach (range(1, 3) as $_) {
            $match = $service->recordSet($match, ['10', '10', '10'], ['8', '8', '8'], null);
        }

        $bronze = $bracket->matches()->where('match_type', 'bronze')->firstOrFail()->fresh();
        $this->assertSame($loserId, $bronze->participant_one_registration_id);
        $this->assertSame('pending', $bronze->status);
    }

    public function test_compound_match_counts_all_five_ends_before_declaring_winner(): void
    {
        [$event, $group] = $this->publishedRanking([40, 30, 20, 10], 'compound', true);
        $bracket = app(IndividualEliminationBracketService::class)->create($event, $group, 4, true);
        $match = $bracket->matches()->where('match_type', 'main')->where('round_number', 1)->where('position', 1)->firstOrFail();
        $service = app(CompoundCumulativeMatchService::class);

        foreach (range(1, 4) as $_) {
            $match = $service->recordEnd($match, ['10', '10', '10'], ['8', '8', '8'], null);
            $this->assertSame('in_progress', $match->status);
            $this->assertNull($match->winner_registration_id);
        }
        $completed = $service->recordEnd($match, ['10', '9', '8'], ['8', '8', '8'], null);

        $this->assertSame('completed', $completed->status);
        $this->assertSame(147, $completed->participant_one_total);
        $this->assertSame(120, $completed->participant_two_total);
        $this->assertSame($completed->participant_one_registration_id, $completed->winner_registration_id);
        $this->assertSame($completed->winner_registration_id, $completed->nextMatch->participant_one_registration_id);
        $this->assertCount(5, $completed->ends);
        $this->assertSame(147, $completed->ends->last()->participant_one_running_total);
    }

    public function test_compound_tie_after_fifteen_arrows_waits_for_shoot_off(): void
    {
        [$event, $group] = $this->publishedRanking([40, 30, 20, 10], 'compound', true);
        $bracket = app(IndividualEliminationBracketService::class)->create($event, $group, 4, false);
        $match = $bracket->matches()->where('round_number', 1)->where('position', 1)->firstOrFail();
        $service = app(CompoundCumulativeMatchService::class);

        foreach (range(1, 5) as $_) {
            $match = $service->recordEnd($match, ['10', '9', '8'], ['9', '9', '9'], null);
        }

        $this->assertSame('awaiting_shoot_off', $match->status);
        $this->assertSame(135, $match->participant_one_total);
        $this->assertSame(135, $match->participant_two_total);
        $this->assertNull($match->winner_registration_id);
        $this->assertNull($match->completed_at);
    }

    public function test_shoot_off_with_different_values_automatically_advances_winner(): void
    {
        $match = $this->compoundMatchAwaitingShootOff();
        $winnerId = $match->participant_one_registration_id;

        $completed = app(EliminationShootOffService::class)->record($match, '10', '9', null);

        $this->assertSame('completed', $completed->status);
        $this->assertSame($winnerId, $completed->winner_registration_id);
        $this->assertSame('score', $completed->shootOffs->first()->decision_type);
        $this->assertSame('resolved', $completed->shootOffs->first()->status);
        $this->assertSame($winnerId, $completed->nextMatch->participant_one_registration_id);
    }

    public function test_equal_shoot_off_requires_judge_and_can_order_another_shoot_off(): void
    {
        $match = $this->compoundMatchAwaitingShootOff();
        $service = app(EliminationShootOffService::class);

        $waiting = $service->record($match, 'X', '10', null);
        $this->assertSame('awaiting_judge', $waiting->status);
        $this->assertSame('pending_judge', $waiting->shootOffs->first()->status);
        $this->assertNull($waiting->winner_registration_id);

        $repeat = $service->adjudicate($waiting, 're_shoot', '雙方箭孔至靶心距離無法區分。', User::factory()->create()->id);
        $this->assertSame('awaiting_shoot_off', $repeat->status);
        $this->assertSame('equal_distance', $repeat->shootOffs->first()->decision_type);

        $completed = $service->record($repeat, '8', '9', null);
        $this->assertSame('completed', $completed->status);
        $this->assertCount(2, $completed->shootOffs);
        $this->assertSame($completed->participant_two_registration_id, $completed->winner_registration_id);
    }

    public function test_chief_judge_closest_to_center_decision_completes_match(): void
    {
        $match = $this->compoundMatchAwaitingShootOff();
        $service = app(EliminationShootOffService::class);
        $waiting = $service->record($match, '10', '10', null);
        $judge = User::factory()->create();

        $completed = $service->adjudicate($waiting, 'participant_two', '第二位選手箭孔較接近靶心。', $judge->id);

        $shootOff = $completed->shootOffs->first();
        $this->assertSame('completed', $completed->status);
        $this->assertSame($completed->participant_two_registration_id, $completed->winner_registration_id);
        $this->assertSame('closest_to_center', $shootOff->decision_type);
        $this->assertSame($judge->id, $shootOff->judged_by);
        $this->assertNotNull($shootOff->judged_at);
    }

    public function test_public_elimination_page_is_hidden_until_bracket_is_explicitly_published(): void
    {
        [$event, $group] = $this->publishedRanking([40, 30, 20, 10], 'recurve', true);
        $event->update(['status'=>'approved', 'published_at'=>now()]);
        $bracket = app(IndividualEliminationBracketService::class)->create($event->fresh(), $group, 4, true);

        $this->get(route('events.elimination', $event))->assertNotFound();

        $bracket->update(['visibility'=>'public', 'published_at'=>now()]);
        $this->get(route('events.elimination', $event))
            ->assertOk()
            ->assertSee('個人對抗賽即時戰況')
            ->assertSee($bracket->matches->first()->participantOneEntry->athlete_name);
    }

    public function test_public_bracket_remains_hidden_when_event_itself_is_unpublished(): void
    {
        [$event, $group] = $this->publishedRanking([40, 30, 20, 10], 'compound', true);
        $bracket = app(IndividualEliminationBracketService::class)->create($event, $group, 4, false);
        $bracket->update(['visibility'=>'public', 'published_at'=>now()]);
        $event->update(['status'=>'draft', 'published_at'=>null]);

        $this->get(route('events.elimination', $event))->assertNotFound();
    }

    public function test_event_owner_can_render_individual_elimination_management_page(): void
    {
        [$event, $group] = $this->publishedRanking([40, 30, 20, 10], 'recurve', true);
        $owner = User::factory()->create();
        EventStaff::create([
            'event_id'=>$event->id,
            'user_id'=>$owner->id,
            'role'=>'owner',
            'status'=>'active',
            'invited_by'=>$owner->id,
        ]);

        $this->actingAs($owner)
            ->get(route('organizer.events.elimination.index', $event))
            ->assertOk()
            ->assertSee('個人對抗表')
            ->assertSee($group->name);
    }

    public function test_legacy_published_group_can_backfill_verification_and_create_ranking_snapshot(): void
    {
        $event = Event::factory()->create([
            'plan_code'=>EventPlanCatalog::LEGACY,
            'plan_features_snapshot'=>EventPlanCatalog::features(EventPlanCatalog::LEGACY),
            'plan_limits_snapshot'=>EventPlanCatalog::limits(EventPlanCatalog::LEGACY),
        ]);
        $group = EventGroup::factory()->create(['event_id'=>$event->id, 'name'=>'舊賽事公開組', 'bow_type'=>'recurve']);
        foreach ([30, 20, 10] as $score) {
            $user = User::factory()->create();
            $registration = EventRegistration::create([
                'event_id'=>$event->id, 'event_group_id'=>$group->id, 'user_id'=>$user->id,
                'name'=>$user->name, 'email'=>$user->email, 'status'=>'checked_in',
                'result_published_at'=>now()->subDay(),
            ]);
            EventScoreEntry::create([
                'event_id'=>$event->id, 'event_registration_id'=>$registration->id,
                'user_id'=>$user->id, 'end_number'=>1, 'scores'=>[$score], 'end_total'=>$score,
            ]);
        }
        $owner = User::factory()->create();
        EventStaff::create([
            'event_id'=>$event->id, 'user_id'=>$owner->id, 'role'=>'owner',
            'status'=>'active', 'invited_by'=>$owner->id,
        ]);

        $this->actingAs($owner)
            ->post(route('organizer.events.results.ranking-snapshot', [$event, $group]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('event_ranking_snapshots', ['event_group_id'=>$group->id, 'version'=>1, 'status'=>'locked']);
        $this->assertSame('published', $group->qualificationPhase()->firstOrFail()->status);
        $this->assertSame(0, $group->registrations()->whereNull('score_verified_at')->count());
        $this->assertDatabaseHas('event_audit_logs', ['event_id'=>$event->id, 'action'=>'results.legacy_verification_backfilled']);
    }

    public function test_elimination_match_allows_only_its_bound_device_to_read_and_score(): void
    {
        [$event, $group] = $this->publishedRanking([40, 30, 20, 10], 'recurve', true);
        $bracket = app(IndividualEliminationBracketService::class)->create($event, $group, 4, false);
        $match = $bracket->matches()->where('round_number', 1)->where('position', 1)->firstOrFail();

        $this->get(route('elimination-stations.show', $match->access_token))
            ->assertOk()->assertSee('驗證並綁定設備')
            ->assertDontSee($match->participantOneEntry->athlete_name);

        $deviceToken = 'test-elimination-device-token';
        $match->update(['device_token_hash'=>hash('sha256', $deviceToken), 'device_bound_at'=>now()]);
        $this->get(route('elimination-stations.show', $match->access_token))
            ->assertStatus(423)->assertSee('第二台設備不能讀取或輸入成績');

        $cookie = 'elimination_device_'.$match->id;
        $this->withCookie($cookie, $deviceToken)
            ->get(route('elimination-stations.show', $match->access_token))
            ->assertOk()->assertSee($match->participantOneEntry->athlete_name)->assertSee('送出本局');
        $this->withCookie($cookie, $deviceToken)
            ->post(route('elimination-stations.sets.store', $match->access_token), [
                'participant_one_arrows'=>['10','10','10'],
                'participant_two_arrows'=>['9','9','9'],
            ])->assertSessionHas('success');
        $this->assertDatabaseHas('event_elimination_match_sets', [
            'event_elimination_match_id'=>$match->id, 'set_number'=>1,
            'participant_one_total'=>30, 'participant_two_total'=>27,
        ]);
    }

    private function compoundMatchAwaitingShootOff()
    {
        [$event, $group] = $this->publishedRanking([40, 30, 20, 10], 'compound', true);
        $bracket = app(IndividualEliminationBracketService::class)->create($event, $group, 4, false);
        $match = $bracket->matches()->where('round_number', 1)->where('position', 1)->firstOrFail();
        foreach (range(1, 5) as $_) {
            $match = app(CompoundCumulativeMatchService::class)->recordEnd($match, ['10', '9', '8'], ['9', '9', '9'], null);
        }
        return $match;
    }

    /** @return array{Event, EventGroup} */
    private function publishedRanking(array $scores, string $bowType, bool $paid): array
    {
        $attributes = $paid ? [
            'plan_code'=>EventPlanCatalog::EVENT_PASS,
            'plan_features_snapshot'=>EventPlanCatalog::features(EventPlanCatalog::EVENT_PASS),
            'plan_limits_snapshot'=>EventPlanCatalog::limits(EventPlanCatalog::EVENT_PASS),
        ] : [];
        $event = Event::factory()->create($attributes);
        $group = EventGroup::factory()->create(['event_id'=>$event->id, 'name'=>'公開組', 'bow_type'=>$bowType]);

        foreach ($scores as $score) {
            $user = User::factory()->create();
            $registration = EventRegistration::create([
                'event_id'=>$event->id,
                'event_group_id'=>$group->id,
                'user_id'=>$user->id,
                'name'=>$user->name,
                'email'=>$user->email,
                'status'=>'checked_in',
                'score_verified_at'=>now(),
                'result_published_at'=>now(),
                'result_status'=>'completed',
            ]);
            EventScoreEntry::create([
                'event_id'=>$event->id,
                'event_registration_id'=>$registration->id,
                'user_id'=>$user->id,
                'end_number'=>1,
                'scores'=>[$score],
                'end_total'=>$score,
            ]);
        }

        $group->qualificationPhase()->firstOrFail()->update([
            'status'=>'published',
            'locked_at'=>now(),
            'published_at'=>now(),
        ]);
        app(QualificationRankingSnapshotService::class)->capture($event, $group);

        return [$event->fresh(), $group->fresh()];
    }
}
