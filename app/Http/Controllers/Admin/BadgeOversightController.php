<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EventBadge;
use App\Models\UserEventBadge;
use App\Models\User;
use App\Services\EventBadgeAwardService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BadgeOversightController extends Controller
{
    public function index(Request $request): View
    {
        $source=in_array($request->query('source'),['official','organization'],true)?$request->query('source'):'all';
        $badges = EventBadge::with('event')
            ->when($source==='official',fn($query)=>$query->where('issuer_type','platform'))
            ->when($source==='organization',fn($query)=>$query->where('issuer_type','!=','platform'))
            ->withCount(['claims', 'awards as active_awards_count' => fn ($query) => $query->whereNull('revoked_at')])
            ->latest()->paginate(20)->withQueryString();
        $sourceCounts=[
            'all'=>EventBadge::count(),
            'official'=>EventBadge::where('issuer_type','platform')->count(),
            'organization'=>EventBadge::where('issuer_type','!=','platform')->count(),
        ];
        $memberCount=User::count();

        return view('admin.badges.index', compact('badges','source','sourceCounts','memberCount'));
    }

    public function create(): View
    {
        return view('admin.badges.create');
    }

    public function toggle(EventBadge $badge): RedirectResponse
    {
        $badge->update(['is_active' => ! $badge->is_active]);

        return back()->with('success', $badge->is_active ? 'Badge 已重新啟用，原本的發放設定已恢復。' : 'Badge 已由平台停用，所有自行領取暫停。');
    }

    public function store(Request $request): RedirectResponse
    {
        $data=$request->validate(['name'=>['required','string','max:120'],'description'=>['nullable','string','max:1000'],'max_supply'=>['nullable','integer','min:1'],'icon'=>['nullable','image','mimes:jpg,jpeg,png,webp','max:10240'],'claim_method'=>['nullable','in:public,location'],'claim_lat'=>['nullable','required_if:claim_method,location','numeric','between:-90,90'],'claim_lng'=>['nullable','required_if:claim_method,location','numeric','between:-180,180'],'claim_radius_km'=>['nullable','numeric','between:1,50'],'claim_period'=>['nullable','in:unlimited,range'],'claim_start_date'=>['nullable','required_if:claim_period,range','date'],'claim_end_date'=>['nullable','required_if:claim_period,range','date','after_or_equal:claim_start_date']]);
        unset($data['icon']); if($request->hasFile('icon')) $data['icon_path']=$request->file('icon')->store('badge-icons','public');
        $claimMethod=$data['claim_method']??'public'; unset($data['claim_method']);
        $data['location_claim_enabled']=$claimMethod==='location'; $data['claim_enabled']=$claimMethod==='public'; $data['claim_radius_km']??=10;
        if($claimMethod==='public') { $data['claim_lat']=null; $data['claim_lng']=null; }
        $claimPeriod=$data['claim_period']??'unlimited'; $claimStartDate=$data['claim_start_date']??null; $claimEndDate=$data['claim_end_date']??null;
        unset($data['claim_period'],$data['claim_start_date'],$data['claim_end_date']);
        if($claimPeriod==='range') {
            $data['claim_starts_at']=Carbon::parse($claimStartDate)->startOfDay(); $data['claim_ends_at']=Carbon::parse($claimEndDate)->endOfDay();
        } else {
            $data['claim_starts_at']=null; $data['claim_ends_at']=null;
        }
        EventBadge::create($data+['created_by'=>$request->user()->id,'issuer_type'=>'platform','issuer_name'=>'ArrowTrack 官方','type'=>'special','eligibility'=>'any','award_rule'=>'manual']);
        return redirect()->route('admin.badges.index',['source'=>'official'])->with('success','官方 Badge 已建立。');
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

    public function awardAll(Request $request, EventBadge $badge, EventBadgeAwardService $service): RedirectResponse
    {
        abort_unless($badge->issuer_type==='platform',422);
        if(!$badge->is_active) return back()->with('error','此 Badge 已由平台停用。');

        $issued=0;
        $blocked=false;
        DB::transaction(function () use ($badge,$request,$service,&$issued,&$blocked): void {
            $locked=EventBadge::whereKey($badge->id)->lockForUpdate()->firstOrFail();
            $eligible=User::whereNotIn('id',UserEventBadge::select('user_id')->where('event_badge_id',$locked->id));
            $eligibleCount=(clone $eligible)->count();
            $used=$locked->awards()->count();
            if($locked->max_supply!==null && $used+$eligibleCount>$locked->max_supply) {
                $blocked=true;
                return;
            }
            $eligible->orderBy('id')->chunkById(200,function ($users) use ($locked,$request,$service,&$issued): void {
                foreach($users as $user) {
                    if($service->award($locked,$user->id,'platform_all',$request->user()->id,'官方全站派發')) $issued++;
                }
            });
        });

        if($blocked) return back()->with('error','限量數量不足，未派發任何 Badge。請提高上限或取消限量後再試。');
        return back()->with('success',$issued>0?'已派發給 '.$issued.' 位會員。':'所有會員都已取得這枚 Badge。');
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
