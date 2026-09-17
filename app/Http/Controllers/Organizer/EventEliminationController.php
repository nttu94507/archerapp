<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventAuditLog;
use App\Models\EventGroup;
use App\Models\EventEliminationMatch;
use App\Models\EventEliminationBracket;
use App\Models\EventRankingSnapshot;
use App\Services\IndividualEliminationBracketService;
use App\Services\TeamEliminationBracketService;
use App\Services\EliminationMatchProgressionService;
use App\Services\EliminationMatchRecoveryService;
use App\Services\DirectEliminationDrawService;
use App\Support\EventPlanCatalog;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class EventEliminationController extends Controller
{
    public function index(Request $request, Event $event, EliminationMatchProgressionService $progression): View
    {
        $this->authorize('viewResults', $event);

        $event->eliminationBrackets()->get()->each(
            fn ($bracket) => $progression->synchronizeBracket($bracket)
        );

        $event->load([
            'groups'=>fn ($query) => $query->withCount([
                'registrations as active_registrations_count'=>fn ($registrations) => $registrations->whereIn('status', ['registered', 'checked_in']),
            ])->with([
                'eliminationBrackets'=>fn ($brackets) => $brackets
                    ->with(['rankingSnapshot', 'matches.participantOneEntry', 'matches.participantTwoEntry', 'matches.participantOneTeam', 'matches.participantTwoTeam', 'matches.sets', 'matches.ends', 'matches.shootOffs']),
                'eventTeams'=>fn ($teams) => $teams->where('status', '!=', 'disbanded')->with('memberships.registration'),
            ]),
        ]);
        $snapshots = EventRankingSnapshot::query()
            ->where('event_id', $event->id)
            ->where('status', 'locked')
            ->whereNull('superseded_at')
            ->with('entries')
            ->get()
            ->keyBy('event_group_id');

        $brackets = $event->groups->flatMap->eliminationBrackets;
        $selectedBracket = $brackets->firstWhere('uuid', $request->string('bracket')->toString())
            ?? $brackets->first();

        $teamCounts = $event->groups->mapWithKeys(function (EventGroup $group): array {
            $summary = [];
            foreach (['standard', 'mixed'] as $format) {
                $teams = $group->eventTeams->where('team_format', $format);
                $eligible = $teams->filter(function ($team): bool {
                    $members = $team->memberships->where('status', 'active');
                    return $members->count() === $team->requiredSize()
                        && $members->every(fn ($member) => $member->registration?->result_published_at !== null);
                });
                $summary[$format] = ['total'=>$teams->count(), 'eligible'=>$eligible->count()];
            }
            return [$group->id=>$summary];
        });

        return view('organizer.elimination.index', [
            'event'=>$event,
            'snapshots'=>$snapshots,
            'sizes'=>IndividualEliminationBracketService::SIZES,
            'selectedBracket'=>$selectedBracket,
            'teamCounts'=>$teamCounts,
        ]);
    }

    public function store(
        Request $request,
        Event $event,
        IndividualEliminationBracketService $service,
        TeamEliminationBracketService $teamService,
        DirectEliminationDrawService $directDraw,
    ): RedirectResponse {
        $this->authorize('manageScoreCorrections', $event);
        if ($event->competition_format === 'qualification' && ! $event->eliminationBrackets()->exists()) {
            throw ValidationException::withMessages(['competition_format'=>'此賽事設定為只有資格賽，不能建立對抗表。']);
        }
        $data = $request->validate([
            'event_group_id'=>['required', 'integer'],
            'bracket_size'=>['required', 'integer', 'in:4,8,16,32,64,128'],
            'bronze_match_enabled'=>['nullable', 'boolean'],
            'category'=>['nullable','in:individual,team,mixed_team'],
        ]);
        if (config('product.mvp_mode', true) && ($data['category'] ?? 'individual') !== 'individual') {
            throw ValidationException::withMessages(['category'=>'MVP 模式目前只開放個人對抗賽。']);
        }
        $group = EventGroup::query()
            ->where('event_id', $event->id)
            ->findOrFail($data['event_group_id']);

        if ($event->plan_code === EventPlanCatalog::TRIAL) {
            if ((int) $data['bracket_size'] > 64) {
                throw ValidationException::withMessages(['bracket_size'=>'完整功能試用最多建立 64 人對抗表。']);
            }
            if ($event->eliminationBrackets()->exists()) {
                throw ValidationException::withMessages(['category'=>'完整功能試用每場限建立一種對抗表。']);
            }
        }

        $category=$data['category']??'individual';
        if ($category === 'individual') {
            if ($event->competition_format === 'elimination_only') {
                $directDraw->draw($event, $group, $request->user()->id);
            }
            $bracket = $service->create($event,$group,(int)$data['bracket_size'],$request->boolean('bronze_match_enabled'),$request->user()->id);
        } else {
            $bracket = $teamService->create($event,$group,(int)$data['bracket_size'],$request->boolean('bronze_match_enabled'),$request->user()->id,$category==='mixed_team'?'mixed':'standard');
        }

        $categoryName = match ($category) {
            'team' => '團體',
            'mixed_team' => '混雙',
            default => '個人',
        };

        return redirect()->route('organizer.events.elimination.index', ['event'=>$event, 'bracket'=>$bracket->uuid])
            ->with('success', $group->name.' '.$categoryName.'對抗表已'.($event->competition_format === 'elimination_only' ? '完成隨機抽籤並建立。' : '依正式排名種子建立。'));
    }

    public function showMatch(Event $event, EventEliminationMatch $match): View
    {
        $this->authorize('viewResults', $event);
        abort_unless($match->bracket()->where('event_id', $event->id)->exists(), 404);
        $match->load(['bracket.group', 'sets', 'ends', 'shootOffs.recorder', 'shootOffs.judge', 'participantOneEntry', 'participantTwoEntry', 'participantOneTeam.memberships.registration', 'participantTwoTeam.memberships.registration']);

        return view($match->bracket->scoring_mode === 'cumulative'
            ? 'organizer.elimination.compound-match'
            : 'organizer.elimination.match', compact('event', 'match'));
    }

    public function updateVisibility(Request $request, Event $event, \App\Models\EventEliminationBracket $bracket): RedirectResponse
    {
        $this->authorize('manageScoreCorrections', $event);
        abort_unless($bracket->event_id === $event->id, 404);
        $data = $request->validate(['visibility'=>['required', 'in:internal,public']]);
        if ($data['visibility'] === 'public' && ! $event->hasPlanFeature('public_visibility')) {
            return back()->withErrors(['visibility'=>'目前方案不支援公開對抗賽戰況。']);
        }
        $bracket->update([
            'visibility'=>$data['visibility'],
            'published_at'=>$data['visibility'] === 'public' ? ($bracket->published_at ?? now()) : null,
        ]);
        \App\Models\EventAuditLog::create([
            'event_id'=>$event->id, 'user_id'=>$request->user()->id,
            'action'=>'elimination.visibility_updated',
            'subject_type'=>\App\Models\EventEliminationBracket::class, 'subject_id'=>$bracket->id,
            'metadata'=>['visibility'=>$data['visibility']],
        ]);

        return back()->with('success', $data['visibility'] === 'public' ? '已公開此組別的對抗賽戰況。' : '已改為僅工作人員可查看。');
    }

    public function qrCode(Event $event, EventEliminationMatch $match)
    {
        $this->authorize('manageScores', $event);
        abort_unless($match->bracket()->where('event_id', $event->id)->exists(), 404);
        $svg = (new Writer(new ImageRenderer(new RendererStyle(280, 2), new SvgImageBackEnd())))
            ->writeString(route('elimination-stations.show', $match->access_token));
        return response($svg, 200, ['Content-Type'=>'image/svg+xml', 'Cache-Control'=>'no-store, private']);
    }

    public function releaseDevice(Request $request, Event $event, EventEliminationMatch $match): RedirectResponse
    {
        $this->authorize('manageScores', $event);
        abort_unless($match->bracket()->where('event_id', $event->id)->exists(), 404);
        DB::transaction(function () use ($request, $event, $match): void {
            $locked = EventEliminationMatch::whereKey($match->id)->lockForUpdate()->firstOrFail();
            $locked->update([
                'access_token'=>(string) Str::uuid(), 'device_pin'=>(string) random_int(100000, 999999),
                'device_token_hash'=>null, 'device_bound_at'=>null, 'device_last_seen_at'=>null, 'device_user_agent'=>null,
            ]);
            \App\Models\EventAuditLog::create([
                'event_id'=>$event->id, 'user_id'=>$request->user()->id,
                'action'=>'elimination.match_device_released', 'subject_type'=>EventEliminationMatch::class,
                'subject_id'=>$match->id, 'metadata'=>['round'=>$match->round_number, 'position'=>$match->position],
            ]);
        });
        return back()->with('success', '舊設備與連結已失效，請使用新的 QR Code 與 PIN。');
    }

    public function updateTargetNumber(Request $request, Event $event, EventEliminationMatch $match): RedirectResponse
    {
        $this->authorize('manageScoreCorrections', $event);
        abort_unless($match->bracket()->where('event_id', $event->id)->exists(), 404);
        $data = $request->validate([
            'participant_one_target_number'=>['required', 'string', 'max:20', 'regex:/^[\pL\pN\-]+$/u'],
            'participant_two_target_number'=>['required', 'string', 'max:20', 'regex:/^[\pL\pN\-]+$/u'],
            'reason'=>['nullable', 'string', 'max:500'],
        ], [
            'participant_one_target_number.regex'=>'選手一靶號只能使用文字、數字與連字號。',
            'participant_two_target_number.regex'=>'選手二靶號只能使用文字、數字與連字號。',
        ]);

        DB::transaction(function () use ($request, $event, $match, $data): void {
            $locked = EventEliminationMatch::whereKey($match->id)->lockForUpdate()->firstOrFail();
            $one = mb_strtoupper(trim($data['participant_one_target_number']));
            $two = mb_strtoupper(trim($data['participant_two_target_number']));
            $before = [
                'participant_one'=>$locked->participant_one_target_number ?? $locked->target_number,
                'participant_two'=>$locked->participant_two_target_number ?? $locked->target_number,
            ];
            if ($one === $before['participant_one'] && $two === $before['participant_two']) return;

            $started = $locked->sets()->exists() || $locked->ends()->exists() || $locked->shootOffs()->exists()
                || in_array($locked->status, ['in_progress', 'awaiting_shoot_off', 'awaiting_judge', 'completed'], true);
            if ($started && mb_strlen(trim((string) ($data['reason'] ?? ''))) < 3) {
                throw ValidationException::withMessages(['reason'=>'計分開始後調整靶號，請填寫至少 3 個字的原因。']);
            }
            $usedNumbers = collect([$one, $two])->unique()->values();
            $duplicate = EventEliminationMatch::query()
                ->whereHas('bracket', fn ($query) => $query->where('event_id', $event->id))
                ->where('round_number', $locked->round_number)
                ->whereKeyNot($locked->id)
                ->whereNotIn('status', ['completed', 'walkover'])
                ->where(function ($query) use ($usedNumbers): void {
                    $query->whereIn('participant_one_target_number', $usedNumbers)
                        ->orWhereIn('participant_two_target_number', $usedNumbers)
                        ->orWhereIn('target_number', $usedNumbers);
                })
                ->exists();
            if ($duplicate) {
                throw ValidationException::withMessages(['participant_one_target_number'=>'同一輪進行中的其他場次已使用其中一個靶號。']);
            }

            $locked->update([
                'target_number'=>$one === $two ? $one : null,
                'participant_one_target_number'=>$one,
                'participant_two_target_number'=>$two,
                'access_token'=>(string) Str::uuid(), 'device_pin'=>(string) random_int(100000, 999999),
                'device_token_hash'=>null, 'device_bound_at'=>null, 'device_last_seen_at'=>null, 'device_user_agent'=>null,
            ]);
            EventAuditLog::create([
                'event_id'=>$event->id, 'user_id'=>$request->user()->id,
                'action'=>'elimination.match_target_number_changed', 'subject_type'=>EventEliminationMatch::class,
                'subject_id'=>$locked->id,
                'metadata'=>['before'=>$before, 'after'=>['participant_one'=>$one, 'participant_two'=>$two], 'reason'=>$data['reason'] ?? null, 'forced'=>$started],
            ]);
        });

        return back()->with('success', '對抗賽靶號已更新，舊設備已解除，請重新掃描 QR Code。');
    }

    public function updateRoundTargetNumbers(Request $request, Event $event, EventEliminationBracket $bracket): RedirectResponse
    {
        $this->authorize('manageScoreCorrections', $event);
        abort_unless($bracket->event_id === $event->id, 404);
        $data = $request->validate([
            'round_number'=>['required', 'integer', 'min:1'],
            'matches'=>['required', 'array', 'min:1'],
            'matches.*.participant_one_target_number'=>['required', 'string', 'max:20', 'regex:/^[\pL\pN\-]+$/u'],
            'matches.*.participant_two_target_number'=>['required', 'string', 'max:20', 'regex:/^[\pL\pN\-]+$/u'],
            'reason'=>['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($request, $event, $bracket, $data): void {
            $matches = EventEliminationMatch::query()->where('event_elimination_bracket_id', $bracket->id)
                ->where('round_number', $data['round_number'])->whereIn('id', array_keys($data['matches']))
                ->lockForUpdate()->get();
            abort_unless($matches->count() === count($data['matches']), 422, '對戰資料已變更，請重新整理後再設定。');
            $numbersByMatch = $matches->mapWithKeys(function ($match) use ($data): array {
                $row = $data['matches'][$match->id];
                return [$match->id=>[
                    'one'=>mb_strtoupper(trim($row['participant_one_target_number'])),
                    'two'=>mb_strtoupper(trim($row['participant_two_target_number'])),
                ]];
            });
            $usedByMatches = $numbersByMatch->map(fn ($row) => collect([$row['one'], $row['two']])->unique()->all())->flatten();
            if ($usedByMatches->duplicates()->isNotEmpty()) {
                throw ValidationException::withMessages(['matches'=>'不同對戰不能使用相同靶號；同一場雙方可以共用。']);
            }
            $started = $matches->contains(fn ($match) => $match->sets()->exists() || $match->ends()->exists() || $match->shootOffs()->exists()
                || in_array($match->status, ['in_progress', 'awaiting_shoot_off', 'awaiting_judge', 'completed'], true));
            if ($started && mb_strlen(trim((string) ($data['reason'] ?? ''))) < 3) {
                throw ValidationException::withMessages(['reason'=>'本輪已有計分紀錄，請填寫至少 3 個字的修改原因。']);
            }
            $before = [];
            foreach ($matches as $match) {
                $before[$match->id] = [$match->participant_one_target_number ?? $match->target_number, $match->participant_two_target_number ?? $match->target_number];
                $numbers = $numbersByMatch[$match->id];
                $match->update([
                    'target_number'=>$numbers['one'] === $numbers['two'] ? $numbers['one'] : null,
                    'participant_one_target_number'=>$numbers['one'], 'participant_two_target_number'=>$numbers['two'],
                    'access_token'=>(string) Str::uuid(), 'device_pin'=>(string) random_int(100000, 999999),
                    'device_token_hash'=>null, 'device_bound_at'=>null, 'device_last_seen_at'=>null, 'device_user_agent'=>null,
                ]);
            }
            EventAuditLog::create([
                'event_id'=>$event->id, 'user_id'=>$request->user()->id,
                'action'=>'elimination.round_targets_changed', 'subject_type'=>EventEliminationBracket::class, 'subject_id'=>$bracket->id,
                'metadata'=>['round'=>$data['round_number'], 'before'=>$before, 'after'=>$numbersByMatch->all(), 'reason'=>$data['reason'] ?? null, 'forced'=>$started],
            ]);
        });

        return back()->with('success', '本輪全部靶號已一次更新，計分設備需重新掃描。');
    }

    public function recoverMatch(Request $request, Event $event, EventEliminationMatch $match, EliminationMatchRecoveryService $service): RedirectResponse
    {
        $this->authorize('manageScoreCorrections', $event);
        abort_unless($match->bracket()->where('event_id', $event->id)->exists(), 404);
        $data = $request->validate([
            'action'=>['required', 'in:force_winner,reopen_shoot_off,resynchronize'],
            'winner'=>['nullable', 'in:participant_one,participant_two'],
            'reason'=>['required', 'string', 'min:3', 'max:1000'],
        ]);
        if ($data['action'] === 'force_winner') {
            $request->validate(['winner'=>['required', 'in:participant_one,participant_two']]);
            $service->forceWinner($match, $data['winner'], $data['reason'], $request->user()->id);
        } elseif ($data['action'] === 'reopen_shoot_off') {
            $service->reopenShootOff($match, $data['reason'], $request->user()->id);
        } else {
            $service->resynchronize($match, $data['reason'], $request->user()->id);
        }
        return back()->with('success', '對抗賽異常處理已完成，操作前後內容已寫入紀錄。');
    }

}
