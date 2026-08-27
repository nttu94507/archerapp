<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventGroup;
use App\Models\EventEliminationMatch;
use App\Models\EventRankingSnapshot;
use App\Services\IndividualEliminationBracketService;
use App\Services\EliminationShootOffService;
use App\Services\EliminationMatchProgressionService;
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
            'bracket_size'=>['required', 'integer', 'in:4,8,16,32,64,128'],
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

    public function reconcileBronzeWalkover(
        Event $event,
        \App\Models\EventEliminationBracket $bracket,
        EliminationMatchProgressionService $progression,
    ): RedirectResponse {
        $this->authorize('manageScoreCorrections', $event);
        abort_unless($bracket->event_id === $event->id, 404);
        $bronze = $bracket->matches()->where('match_type', 'bronze')->first();

        if (! $bronze) {
            return back()->withErrors(['bronze'=>'此對抗表沒有設定季軍賽。']);
        }
        if (! $progression->reconcileBronzeWalkover($bronze)) {
            return back()->withErrors(['bronze'=>'目前不符合自動輪空條件；請確認兩場準決賽都已結束，且季軍賽只有一位有效選手。']);
        }

        return back()->with('success', '季軍賽已重新檢查，唯一有效選手已輪空取得季軍。');
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
