<?php

namespace App\Http\Controllers;

use App\Models\EventAuditLog;
use App\Models\EventEliminationMatch;
use App\Services\CompoundCumulativeMatchService;
use App\Services\EliminationShootOffService;
use App\Services\RecurveSetMatchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EliminationScoringStationController extends Controller
{
    public function show(Request $request, string $token)
    {
        $match = $this->match($token);
        if (! $this->isAuthorizedDevice($request, $match)) {
            return $match->device_token_hash
                ? response()->view('elimination-stations.device-locked', [], 423)
                : view('elimination-stations.claim', compact('match'));
        }
        $match->load(['bracket.event', 'bracket.group', 'sets', 'ends', 'shootOffs.recorder', 'shootOffs.judge', 'participantOneEntry', 'participantTwoEntry', 'participantOneTeam.memberships.registration', 'participantTwoTeam.memberships.registration']);
        $event = $match->bracket->event;
        $deviceMode = true;
        $stationToken = $token;
        $view = $match->bracket->scoring_mode === 'cumulative' ? 'organizer.elimination.compound-match' : 'organizer.elimination.match';

        return view($view, compact('event', 'match', 'deviceMode', 'stationToken'));
    }

    public function claim(Request $request, string $token): RedirectResponse
    {
        $match = $this->match($token);
        $validated = $request->validate(['pin'=>['required', 'digits:6']]);
        $deviceToken = null;
        $result = DB::transaction(function () use ($match, $validated, $request, &$deviceToken): string {
            $locked = EventEliminationMatch::whereKey($match->id)->lockForUpdate()->firstOrFail();
            if ($locked->device_token_hash) return 'locked';
            if (! hash_equals((string) $locked->device_pin, (string) $validated['pin'])) return 'invalid';
            $deviceToken = Str::random(64);
            $locked->update([
                'device_token_hash'=>hash('sha256', $deviceToken), 'device_bound_at'=>now(),
                'device_last_seen_at'=>now(), 'device_user_agent'=>Str::limit((string) $request->userAgent(), 500, ''),
            ]);
            EventAuditLog::create([
                'event_id'=>$match->bracket->event_id, 'action'=>'elimination.match_device_bound',
                'subject_type'=>EventEliminationMatch::class, 'subject_id'=>$match->id,
                'metadata'=>['round'=>$match->round_number, 'position'=>$match->position],
            ]);
            return 'claimed';
        });
        if ($result === 'invalid') return back()->withInput()->withErrors(['pin'=>'PIN 碼錯誤。']);
        if ($result === 'locked') return redirect()->route('elimination-stations.show', $token);
        Cookie::queue(cookie($this->cookieName($match), $deviceToken, 60 * 24 * 30, '/', null, $request->isSecure(), true, false, 'strict'));
        return redirect()->route('elimination-stations.show', $token);
    }

    public function storeSet(Request $request, string $token, RecurveSetMatchService $service): RedirectResponse
    {
        $match = $this->authorizedMatch($request, $token);
        $data = $this->scores($request);
        $service->recordSet($match, $data['participant_one_arrows'], $data['participant_two_arrows'], null);
        return back()->with('success', '本局已保存。');
    }

    public function storeEnd(Request $request, string $token, CompoundCumulativeMatchService $service): RedirectResponse
    {
        $match = $this->authorizedMatch($request, $token);
        $data = $this->scores($request);
        $service->recordEnd($match, $data['participant_one_arrows'], $data['participant_two_arrows'], null);
        return back()->with('success', '本趟已保存。');
    }

    public function storeShootOff(Request $request, string $token, EliminationShootOffService $service): RedirectResponse
    {
        $match = $this->authorizedMatch($request, $token);
        if(in_array($match->bracket->category,['team','mixed_team'],true)){
            $size=$match->bracket->category==='mixed_team'?2:3;
            $data=$request->validate(['participant_one_arrows'=>['required','array','size:'.$size],'participant_one_arrows.*'=>['required','string','max:2'],'participant_two_arrows'=>['required','array','size:'.$size],'participant_two_arrows.*'=>['required','string','max:2']]);
            $service->recordTeam($match,$data['participant_one_arrows'],$data['participant_two_arrows'],null);
        }else{
            $data = $request->validate(['participant_one_arrow'=>['required','string','max:2'], 'participant_two_arrow'=>['required','string','max:2']]);
            $service->record($match, $data['participant_one_arrow'], $data['participant_two_arrow'], null);
        }
        return back()->with('success', '加射箭值已保存。');
    }

    private function scores(Request $request): array
    {
        $match=$request->route('token')?EventEliminationMatch::where('access_token',$request->route('token'))->with('bracket')->first():null;
        $size=$match&&in_array($match->bracket->category,['team','mixed_team'],true)?($match->bracket->category==='mixed_team'?4:6):3;
        return $request->validate([
            'participant_one_arrows'=>['required','array','size:'.$size], 'participant_one_arrows.*'=>['required','string','max:2'],
            'participant_two_arrows'=>['required','array','size:'.$size], 'participant_two_arrows.*'=>['required','string','max:2'],
        ]);
    }

    private function match(string $token): EventEliminationMatch
    {
        $match = EventEliminationMatch::where('access_token', $token)->with('bracket.event', 'bracket.group')->firstOrFail();
        abort_if(
            $match->bracket->event->auditLogs()->where('action', 'event.completed')->exists(),
            410,
            '此賽事已正式完成，對抗賽計分設備已停用。'
        );

        return $match;
    }

    private function authorizedMatch(Request $request, string $token): EventEliminationMatch
    {
        $match = $this->match($token);
        abort_unless($this->isAuthorizedDevice($request, $match), 423, '此設備未取得本場計分權限。');
        return $match;
    }

    private function isAuthorizedDevice(Request $request, EventEliminationMatch $match): bool
    {
        $token = $request->cookie($this->cookieName($match));
        if (! is_string($token) || ! $match->device_token_hash || ! hash_equals($match->device_token_hash, hash('sha256', $token))) return false;
        return DB::transaction(function () use ($match, $token): bool {
            $locked = EventEliminationMatch::whereKey($match->id)->lockForUpdate()->firstOrFail();
            if (! $locked->device_token_hash || ! hash_equals($locked->device_token_hash, hash('sha256', $token))) return false;
            $locked->update(['device_last_seen_at'=>now()]);
            return true;
        });
    }

    private function cookieName(EventEliminationMatch $match): string { return 'elimination_device_'.$match->id; }
}
