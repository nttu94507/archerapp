<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventAuditLog;
use App\Models\EventRegistration;
use App\Services\EventBadgeAwardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EventResultController extends Controller
{
    public function index(Event $event): View
    {
        $this->authorize('manageScores', $event);
        $registrations = $event->registrations()->with(['event_group', 'scoreEntries'])->whereIn('status', ['registered','checked_in'])->get()->map(function ($registration) {
            $registration->calculated_total = $registration->scoreEntries->sum('end_total');
            return $registration;
        })->sortByDesc('calculated_total');
        return view('organizer.results.index', compact('event', 'registrations'));
    }

    public function verify(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('manageScores', $event);
        $validated = $request->validate(['registration_ids'=>['required','array','min:1'],'registration_ids.*'=>['integer']]);
        $items = $event->registrations()->whereIn('id',$validated['registration_ids'])->whereNotNull('score_submitted_at')->get();
        foreach ($items as $registration) $registration->update(['score_verified_at'=>now(),'score_verified_by'=>$request->user()->id]);
        EventAuditLog::create(['event_id'=>$event->id,'user_id'=>$request->user()->id,'action'=>'results.verified','metadata'=>['count'=>$items->count()]]);
        return back()->with('success','已確認 '.$items->count().' 筆成績。');
    }

    public function publish(Request $request, Event $event, EventBadgeAwardService $badges): RedirectResponse
    {
        $this->authorize('manageScores', $event);
        $verified = $event->registrations()->whereNotNull('score_verified_at')->get();
        abort_if($verified->isEmpty(),422,'至少確認一筆成績才能發布。');
        foreach ($verified as $registration) $registration->update(['result_published_at'=>now()]);
        $event->update(['completed_at'=>now()]);
        $awarded = $badges->awardPlacementsFor($event);
        EventAuditLog::create(['event_id'=>$event->id,'user_id'=>$request->user()->id,'action'=>'results.published','metadata'=>['count'=>$verified->count()]]);
        return back()->with('success','正式成績已發布，已發放 '.$awarded.' 個名次 Badge。');
    }
}
