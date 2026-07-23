<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\EventBadge;
use App\Models\User;
use App\Services\EventBadgeAwardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class BadgeController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->canCreateEvents(),403);
        $badges=EventBadge::whereNull('event_id')->where('created_by',$request->user()->id)->withCount('awards')->latest()->get();
        return view('organizer.badge-hub.index',compact('badges'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canCreateEvents(),403);
        $data=$request->validate(['name'=>['required','string','max:120'],'description'=>['nullable','string','max:1000'],'external_activity_name'=>['required','string','max:160'],'external_activity_date'=>['nullable','date'],'external_activity_location'=>['nullable','string','max:255'],'max_supply'=>['nullable','integer','min:1'],'icon'=>['nullable','image','mimes:jpg,jpeg,png,webp','max:10240']]);
        unset($data['icon']); if($request->hasFile('icon')) $data['icon_path']=$request->file('icon')->store('badge-icons','public');
        EventBadge::create($data+['created_by'=>$request->user()->id,'issuer_type'=>'organizer','issuer_name'=>$request->user()->organizerProfile?->organization_name ?: $request->user()->display_name,'type'=>'special','eligibility'=>'any','award_rule'=>'manual','claim_enabled'=>false]);
        return back()->with('success','Badge 已建立，可直接發放給會員。');
    }

    public function award(Request $request, EventBadge $badge, EventBadgeAwardService $service): RedirectResponse
    {
        abort_unless($badge->event_id===null && ($badge->created_by===$request->user()->id || $request->user()->isAdmin()),403);
        $data=$request->validate(['member'=>['required','string','max:255'],'note'=>['nullable','string','max:1000']]);
        if($badge->isAtCapacity()) return back()->with('error','徽章數量已達到最大值。');
        $user=User::where('uuid',$data['member'])->orWhere('email',$data['member'])->first();
        if(!$user) return back()->with('error','找不到此會員編號或 Email。');
        $issued=$service->award($badge,$user->id,$badge->issuer_type==='platform'?'platform':'manual',$request->user()->id,$data['note']??null);
        return back()->with($issued?'success':'error',$issued?'Badge 已發放。':($badge->fresh()->isAtCapacity()?'徽章數量已達到最大值。':'會員已取得這枚 Badge。'));
    }
}
