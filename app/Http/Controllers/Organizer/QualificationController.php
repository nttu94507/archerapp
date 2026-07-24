<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\OrganizerApplication;
use App\Models\OrganizerProfile;
use App\Models\OrganizerReviewLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class QualificationController extends Controller
{
    public function show(Request $request): View
    {
        $profile = $request->user()->organizerProfile()->with(['applications'=>fn($q)=>$q->latest('version'),'reviewLogs.actor'])->first();
        return view('organizer.qualification.show', compact('profile'));
    }

    public function update(Request $request): RedirectResponse
    {
        $profile = $request->user()->organizerProfile()->first();
        abort_if($profile && ! $profile->canEditApplication(), 422, '目前狀態不能修改申請資料。');
        $validated = $this->validateProfile($request);
        if ($request->hasFile('verification_document')) {
            $validated['verification_document_path'] = $request->file('verification_document')->store('organizer-verification', 'local');
        }
        $profile = OrganizerProfile::updateOrCreate(['user_id'=>$request->user()->id], $validated + ['status'=>$profile?->status ?? 'draft']);
        OrganizerReviewLog::create(['organizer_profile_id'=>$profile->id,'actor_id'=>$request->user()->id,'action'=>'profile.saved']);
        return back()->with('success','主辦方申請草稿已儲存。');
    }

    public function submit(Request $request): RedirectResponse
    {
        $profile = $request->user()->organizerProfile()->first();
        abort_unless($profile && $profile->canEditApplication(), 422, '請先完成申請資料，或目前狀態不能送審。');
        $version = ((int)$profile->applications()->max('version')) + 1;
        DB::transaction(function () use ($profile,$version,$request) {
            $application = OrganizerApplication::create([
                'organizer_profile_id'=>$profile->id,'version'=>$version,'status'=>'pending',
                'snapshot'=>$profile->only(['organization_name','organization_type','contact_name','contact_email','contact_phone','website','social_link','registration_number','experience','planned_events','application_reason','verification_document_path']),
                'submitted_at'=>now(),
            ]);
            $profile->update(['status'=>'pending','public_review_note'=>null]);
            OrganizerReviewLog::create(['organizer_profile_id'=>$profile->id,'organizer_application_id'=>$application->id,'actor_id'=>$request->user()->id,'action'=>'submitted']);
        });
        return back()->with('success','主辦方資格申請已送出。');
    }

    public function withdraw(Request $request): RedirectResponse
    {
        $profile = $request->user()->organizerProfile()->first();
        abort_unless($profile?->status === 'pending', 422);
        DB::transaction(function () use ($profile,$request) {
            $application=$profile->applications()->where('status','pending')->latest('version')->first();
            $application?->update(['status'=>'withdrawn']);
            $profile->update(['status'=>'draft']);
            OrganizerReviewLog::create(['organizer_profile_id'=>$profile->id,'organizer_application_id'=>$application?->id,'actor_id'=>$request->user()->id,'action'=>'withdrawn']);
        });
        return back()->with('success','申請已撤回，可以修改後重新送審。');
    }

    private function validateProfile(Request $request): array
    {
        return $request->validate([
            'organization_name'=>['required','string','max:160'],'organization_type'=>['required','in:individual,club,association,school,company,other'],
            'contact_name'=>['required','string','max:120'],'contact_email'=>['required','email','max:255'],'contact_phone'=>['required','string','max:40'],
            'website'=>['nullable','url','max:255'],'social_link'=>['nullable','url','max:255'],'registration_number'=>['nullable','string','max:80'],
            'experience'=>['nullable','string','max:3000'],'planned_events'=>['nullable','string','max:3000'],'application_reason'=>['required','string','max:3000'],
            'verification_document'=>['nullable','file','mimes:pdf,jpg,jpeg,png','max:5120'],
        ]);
    }
}
