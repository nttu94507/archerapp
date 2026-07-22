<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrganizerProfile;
use App\Models\OrganizerReviewLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrganizerQualificationController extends Controller
{
    public function index(Request $request): View
    {
        $profiles=OrganizerProfile::with('user')->withCount('applications')
            ->when($request->filled('status'),fn($q)=>$q->where('status',$request->status))
            ->when($request->filled('q'),fn($q)=>$q->where(fn($x)=>$x->where('organization_name','like','%'.$request->q.'%')->orWhere('contact_email','like','%'.$request->q.'%')))
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 WHEN status = 'legacy_review' THEN 1 ELSE 2 END")->latest()->paginate(20)->withQueryString();
        return view('admin.organizers.index',compact('profiles'));
    }

    public function show(OrganizerProfile $profile): View
    {
        $profile->load(['user','applications.reviewer','reviewLogs.actor']);
        return view('admin.organizers.show',compact('profile'));
    }

    public function review(Request $request, OrganizerProfile $profile): RedirectResponse
    {
        $validated=$request->validate(['decision'=>['required','in:approve,changes_requested,reject'],'public_note'=>['nullable','string','max:2000'],'internal_note'=>['nullable','string','max:2000']]);
        abort_unless(in_array($profile->status,['pending','legacy_review'],true),422,'目前狀態不能審核。');
        $map=['approve'=>'approved','changes_requested'=>'changes_requested','reject'=>'rejected']; $status=$map[$validated['decision']];
        $application=$profile->applications()->where('status','pending')->latest('version')->first();
        $application?->update(['status'=>$status,'reviewed_by'=>$request->user()->id,'reviewed_at'=>now()]);
        $profile->update(['status'=>$status,'approved_at'=>$status==='approved'?now():null,'suspended_at'=>null,'public_review_note'=>$validated['public_note']??null]);
        OrganizerReviewLog::create(['organizer_profile_id'=>$profile->id,'organizer_application_id'=>$application?->id,'actor_id'=>$request->user()->id,'action'=>$status,'public_note'=>$validated['public_note']??null,'internal_note'=>$validated['internal_note']??null]);
        return back()->with('success','主辦方資格已更新為 '.$status.'。');
    }

    public function suspend(Request $request, OrganizerProfile $profile): RedirectResponse
    {
        $validated=$request->validate(['public_note'=>['required','string','max:2000'],'internal_note'=>['nullable','string','max:2000']]);
        abort_unless(in_array($profile->status,['approved','legacy_review'],true),422);
        $profile->update(['status'=>'suspended','suspended_at'=>now(),'public_review_note'=>$validated['public_note']]);
        OrganizerReviewLog::create(['organizer_profile_id'=>$profile->id,'actor_id'=>$request->user()->id,'action'=>'suspended','public_note'=>$validated['public_note'],'internal_note'=>$validated['internal_note']??null]);
        return back()->with('success','主辦方資格已停權。');
    }

    public function restore(Request $request, OrganizerProfile $profile): RedirectResponse
    {
        abort_unless($profile->status==='suspended',422);
        $profile->update(['status'=>'approved','suspended_at'=>null,'public_review_note'=>null,'approved_at'=>$profile->approved_at??now()]);
        OrganizerReviewLog::create(['organizer_profile_id'=>$profile->id,'actor_id'=>$request->user()->id,'action'=>'restored']);
        return back()->with('success','主辦方資格已恢復。');
    }

    public function document(OrganizerProfile $profile): StreamedResponse
    {
        abort_unless($profile->verification_document_path,404);
        return \Illuminate\Support\Facades\Storage::disk('local')->download($profile->verification_document_path);
    }
}
