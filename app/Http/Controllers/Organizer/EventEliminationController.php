<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventGroup;
use App\Models\EventEliminationMatch;
use App\Models\EventRankingSnapshot;
use App\Services\IndividualEliminationBracketService;
use App\Services\RecurveSetMatchService;
use App\Services\CompoundCumulativeMatchService;
use App\Services\EliminationShootOffService;
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
    public function index(Event $event): View
    {
        $this->authorize('viewResults', $event);

        $event->load([
            'groups'=>fn ($query) => $query->with(['eliminationBrackets'=>fn ($brackets) => $brackets
                ->with(['rankingSnapshot', 'matches.participantOneEntry', 'matches.participantTwoEntry', 'matches.sets', 'matches.ends', 'matches.shootOffs'])]),
        ]);
        $snapshots = EventRankingSnapshot::query()
            ->where('event_id', $event->id)
            ->where('status', 'locked')
            ->whereNull('superseded_at')
            ->with('entries')
            ->get()
            ->keyBy('event_group_id');

        return view('organizer.elimination.index', [
            'event'=>$event,
            'snapshots'=>$snapshots,
            'sizes'=>IndividualEliminationBracketService::SIZES,
        ]);
    }

    public function store(
        Request $request,
        Event $event,
        IndividualEliminationBracketService $service,
    ): RedirectResponse {
        $this->authorize('manageScoreCorrections', $event);
        $data = $request->validate([
            'event_group_id'=>['required', 'integer'],
            'bracket_size'=>['required', 'integer', 'in:4,8,16,32,64'],
            'bronze_match_enabled'=>['nullable', 'boolean'],
        ]);
        $group = EventGroup::query()
            ->where('event_id', $event->id)
            ->findOrFail($data['event_group_id']);

        $service->create(
            $event,
            $group,
            (int) $data['bracket_size'],
            $request->boolean('bronze_match_enabled'),
            $request->user()->id,
        );

        return redirect()->route('organizer.events.elimination.index', $event)
            ->with('success', $group->name.' 個人對抗表已依正式排名種子建立。');
    }

    public function showMatch(Event $event, EventEliminationMatch $match): View
    {
        $this->authorize('viewResults', $event);
        abort_unless($match->bracket()->where('event_id', $event->id)->exists(), 404);
        $match->load(['bracket.group', 'sets', 'ends', 'shootOffs.recorder', 'shootOffs.judge', 'participantOneEntry', 'participantTwoEntry']);

        return view($match->bracket->scoring_mode === 'cumulative'
            ? 'organizer.elimination.compound-match'
            : 'organizer.elimination.match', compact('event', 'match'));
    }

    public function storeSet(
        Request $request,
        Event $event,
        EventEliminationMatch $match,
        RecurveSetMatchService $service,
    ): RedirectResponse {
        $this->authorize('manageScores', $event);
        abort_unless($match->bracket()->where('event_id', $event->id)->exists(), 404);
        $data = $request->validate([
            'participant_one_arrows'=>['required', 'array', 'size:3'],
            'participant_one_arrows.*'=>['required', 'string', 'max:2'],
            'participant_two_arrows'=>['required', 'array', 'size:3'],
            'participant_two_arrows.*'=>['required', 'string', 'max:2'],
        ]);
        $updated = $service->recordSet(
            $match,
            $data['participant_one_arrows'],
            $data['participant_two_arrows'],
            $request->user()->id,
        );

        $message = match ($updated->status) {
            'completed'=>'本場比賽完成，勝者已自動晉級。',
            'awaiting_shoot_off'=>'五局結束仍為 5：5，請進行加射與主裁判判定。',
            default=>'第 '.$updated->sets->count().' 局已保存。',
        };

        return redirect()->route('organizer.events.elimination.matches.show', [$event, $match])->with('success', $message);
    }

    public function storeEnd(Request $request, Event $event, EventEliminationMatch $match, CompoundCumulativeMatchService $service): RedirectResponse
    {
        $this->authorize('manageScores', $event);
        abort_unless($match->bracket()->where('event_id', $event->id)->exists(), 404);
        $data = $request->validate([
            'participant_one_arrows'=>['required', 'array', 'size:3'],
            'participant_one_arrows.*'=>['required', 'string', 'max:2'],
            'participant_two_arrows'=>['required', 'array', 'size:3'],
            'participant_two_arrows.*'=>['required', 'string', 'max:2'],
        ]);
        $updated = $service->recordEnd($match, $data['participant_one_arrows'], $data['participant_two_arrows'], $request->user()->id);
        $message = match ($updated->status) {
            'completed'=>'五趟比賽完成，勝者已自動晉級。',
            'awaiting_shoot_off'=>'十五箭累計同分，請進行加射與主裁判判定。',
            default=>'第 '.$updated->ends->count().' 趟已保存。',
        };

        return redirect()->route('organizer.events.elimination.matches.show', [$event, $match])->with('success', $message);
    }

    public function storeShootOff(Request $request, Event $event, EventEliminationMatch $match, EliminationShootOffService $service): RedirectResponse
    {
        $this->authorize('manageShootOff', $event);
        abort_unless($match->bracket()->where('event_id', $event->id)->exists(), 404);
        $data = $request->validate([
            'participant_one_arrow'=>['required', 'string', 'max:2'],
            'participant_two_arrow'=>['required', 'string', 'max:2'],
        ]);
        $updated = $service->record($match, $data['participant_one_arrow'], $data['participant_two_arrow'], $request->user()->id);
        $message = $updated->status === 'completed' ? '加射分值不同，勝者已自動晉級。' : '加射同分，已送交主裁判判定距離。';

        return redirect()->route('organizer.events.elimination.matches.show', [$event, $match])->with('success', $message);
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

    public function adjudicateShootOff(Request $request, Event $event, EventEliminationMatch $match, EliminationShootOffService $service): RedirectResponse
    {
        $this->authorize('adjudicateShootOff', $event);
        abort_unless($match->bracket()->where('event_id', $event->id)->exists(), 404);
        $data = $request->validate([
            'decision'=>['required', 'in:participant_one,participant_two,re_shoot'],
            'decision_note'=>['required', 'string', 'max:1000'],
        ]);
        $updated = $service->adjudicate($match, $data['decision'], $data['decision_note'], $request->user()->id);
        $message = $updated->status === 'completed' ? '主裁判判定完成，勝者已自動晉級。' : '已判定同距離，請進行下一次加射。';

        return redirect()->route('organizer.events.elimination.matches.show', [$event, $match])->with('success', $message);
    }
}
