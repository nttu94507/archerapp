<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EventBadge;
use App\Models\UserEventBadge;
use App\Models\User;
use App\Services\EventBadgeAwardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BadgeOversightController extends Controller
{
    public function index(Request $request): View
    {
        $badges = EventBadge::with('event')
            ->withCount(['claims', 'awards as active_awards_count' => fn ($query) => $query->whereNull('revoked_at')])
            ->latest()->paginate(20);

        return view('admin.badges.index', compact('badges'));
    }

    public function toggle(EventBadge $badge): RedirectResponse
    {
        $badge->update(['is_active' => ! $badge->is_active, 'claim_enabled' => false]);

        return back()->with('success', $badge->is_active ? 'Badge 已重新啟用。' : 'Badge 與申請 QR Code 已停用。');
    }

    public function store(Request $request): RedirectResponse
    {
        $data=$request->validate(['name'=>['required','string','max:120'],'description'=>['nullable','string','max:1000'],'max_supply'=>['nullable','integer','min:1'],'icon'=>['nullable','image','mimes:jpg,jpeg,png,webp','max:10240'],'location_claim_enabled'=>['nullable','boolean'],'claim_lat'=>['nullable','required_if:location_claim_enabled,1','numeric','between:-90,90'],'claim_lng'=>['nullable','required_if:location_claim_enabled,1','numeric','between:-180,180'],'claim_radius_km'=>['nullable','numeric','between:1,50']]);
        unset($data['icon']); if($request->hasFile('icon')) $data['icon_path']=$request->file('icon')->store('badge-icons','public');
        $data['location_claim_enabled']=$request->boolean('location_claim_enabled'); $data['claim_radius_km']??=10;
        EventBadge::create($data+['created_by'=>$request->user()->id,'issuer_type'=>'platform','issuer_name'=>'ArrowTrack 官方','type'=>'special','eligibility'=>'any','award_rule'=>'manual','claim_enabled'=>false]);
        return back()->with('success','官方 Badge 已建立。');
    }

    public function award(Request $request, EventBadge $badge, EventBadgeAwardService $service): RedirectResponse
    {
        abort_unless($badge->issuer_type==='platform',422);
        $data=$request->validate(['member'=>['required','string','max:255'],'note'=>['nullable','string','max:1000']]);
        if($badge->isAtCapacity()) return back()->with('error','徽章數量已達到最大值。');
        $user=User::where('uuid',$data['member'])->orWhere('email',$data['member'])->first();
        if(!$user) return back()->with('error','找不到會員。');
        $issued=$service->award($badge,$user->id,'platform',$request->user()->id,$data['note']??null);
        return back()->with($issued?'success':'error',$issued?'官方 Badge 已發放。':($badge->fresh()->isAtCapacity()?'徽章數量已達到最大值。':'會員已取得這枚 Badge。'));
    }

    public function revoke(Request $request, UserEventBadge $award): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $award->update([
            'revoked_by' => $request->user()->id,
            'revoked_at' => now(),
            'revoked_reason' => $validated['reason'],
        ]);

        return back()->with('success', 'Badge 授予已撤銷並保留紀錄。');
    }
}
