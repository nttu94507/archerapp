<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\EventBadge;
use App\Models\User;
use App\Services\EventBadgeAwardService;
use Carbon\Carbon;
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

    public function create(Request $request): View
    {
        abort_unless($request->user()->canCreateEvents(),403);
        return view('organizer.badge-hub.create');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canCreateEvents(),403);
        $data=$this->validated($request);
        unset($data['icon']); if($request->hasFile('icon')) $data['icon_path']=$request->file('icon')->store('badge-icons','public');
        $data=$this->claimSettings($request,$data);
        EventBadge::create($data+['created_by'=>$request->user()->id,'issuer_type'=>'organizer','issuer_name'=>$request->user()->organizerProfile?->organization_name ?: $request->user()->display_name,'type'=>'special','eligibility'=>'any','award_rule'=>'manual','claim_enabled'=>false]);
        return redirect()->route('organizer.badges.index')->with('success','Badge 已建立。');
    }

    public function edit(Request $request, EventBadge $badge): View
    {
        $this->authorizeBadge($request,$badge);
        return view('organizer.badge-hub.edit',compact('badge'));
    }

    public function update(Request $request, EventBadge $badge): RedirectResponse
    {
        $this->authorizeBadge($request,$badge);
        $data=$this->validated($request);
        unset($data['icon']);
        if($request->hasFile('icon')) {
            $newPath=$request->file('icon')->store('badge-icons','public');
            if($badge->icon_path) Storage::disk('public')->delete($badge->icon_path);
            $data['icon_path']=$newPath;
        }
        $badge->update($this->claimSettings($request,$data));
        return redirect()->route('organizer.badges.index')->with('success','Badge 已更新。');
    }

    public function award(Request $request, EventBadge $badge, EventBadgeAwardService $service): RedirectResponse
    {
        abort_unless($badge->event_id===null && ($badge->created_by===$request->user()->id || $request->user()->isAdmin()),403);
        if(!$badge->is_active) return back()->with('error','此 Badge 已由平台停用。');
        $data=$request->validate(['member'=>['required','string','max:255'],'note'=>['nullable','string','max:1000']]);
        if($badge->isAtCapacity()) return back()->with('error','徽章數量已達到最大值。');
        $user=User::where('uuid',$data['member'])->orWhere('email',$data['member'])->first();
        if(!$user) return back()->with('error','找不到此會員編號或 Email。');
        $issued=$service->award($badge,$user->id,$badge->issuer_type==='platform'?'platform':'manual',$request->user()->id,$data['note']??null);
        return back()->with($issued?'success':'error',$issued?'Badge 已發放。':($badge->fresh()->isAtCapacity()?'徽章數量已達到最大值。':'會員已取得這枚 Badge。'));
    }

    public function toggleClaim(Request $request, EventBadge $badge): RedirectResponse
    {
        $this->authorizeBadge($request,$badge);
        abort_if($badge->claim_lat===null || $badge->claim_lng===null,422,'此 Badge 未設定定位領取。');
        if(!$badge->is_active) return back()->with('error','此 Badge 已由平台停用，無法變更自行領取狀態。');

        $enabled=!$badge->location_claim_enabled;
        $badge->update(['location_claim_enabled'=>$enabled]);

        return back()->with('success',$enabled?'自行領取已重新開放。':'自行領取已停用，既有 QR Code 暫時無法領取。');
    }

    private function authorizeBadge(Request $request,EventBadge $badge): void
    {
        abort_unless($request->user()->canCreateEvents(),403);
        abort_unless($badge->event_id===null && $badge->issuer_type==='organizer' && $badge->created_by===$request->user()->id,403);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name'=>['required','string','max:120'],'description'=>['nullable','string','max:1000'],
            'external_activity_name'=>['required','string','max:160'],'external_activity_date'=>['nullable','date'],
            'external_activity_location'=>['nullable','string','max:255'],'max_supply'=>['nullable','integer','min:1'],
            'icon'=>['nullable','image','mimes:jpg,jpeg,png,webp','max:10240'],'location_claim_enabled'=>['nullable','boolean'],
            'claim_lat'=>['nullable','required_if:location_claim_enabled,1','numeric','between:-90,90'],
            'claim_lng'=>['nullable','required_if:location_claim_enabled,1','numeric','between:-180,180'],
            'claim_radius_km'=>['nullable','numeric','between:1,50'],'claim_period'=>['nullable','in:unlimited,range'],
            'claim_start_date'=>['nullable','required_if:claim_period,range','date'],
            'claim_end_date'=>['nullable','required_if:claim_period,range','date','after_or_equal:claim_start_date'],
        ]);
    }

    private function claimSettings(Request $request,array $data): array
    {
        $data['location_claim_enabled']=$request->boolean('location_claim_enabled');
        $data['claim_radius_km']??=10;
        $claimPeriod=$data['claim_period']??'unlimited';
        $claimStartDate=$data['claim_start_date']??null;
        $claimEndDate=$data['claim_end_date']??null;
        unset($data['claim_period'],$data['claim_start_date'],$data['claim_end_date']);
        if(! $data['location_claim_enabled']) {
            $data['claim_starts_at']=null; $data['claim_ends_at']=null;
        } elseif($claimPeriod==='range') {
            $data['claim_starts_at']=Carbon::parse($claimStartDate)->startOfDay();
            $data['claim_ends_at']=Carbon::parse($claimEndDate)->endOfDay();
        } else {
            $data['claim_starts_at']=null; $data['claim_ends_at']=null;
        }
        return $data;
    }
}
