<?php

namespace App\Http\Controllers;

use App\Models\EventBadge;
use App\Services\EventBadgeAwardService;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class BadgeDropController extends Controller
{
    public function show(string $token): View
    {
        $badge=$this->badge($token);
        return view('badges.location-claim',compact('badge'));
    }

    public function claim(Request $request, string $token, EventBadgeAwardService $service): RedirectResponse
    {
        $badge=$this->badge($token);
        if(!$badge->is_active || (!$badge->location_claim_enabled && !$badge->claim_enabled)) return back()->with('error','此 Badge 目前未開放領取。');
        if($badge->claim_starts_at && $badge->claim_starts_at->isFuture()) return back()->with('error','Badge 尚未開放領取。');
        if($badge->claim_ends_at && $badge->claim_ends_at->isPast()) return back()->with('error','Badge 領取活動已結束。');
        if($badge->awards()->where('user_id',$request->user()->id)->whereNull('revoked_at')->exists()) return back()->with('error','你已經取得這枚 Badge。');
        if($badge->isAtCapacity()) return back()->with('error','徽章數量已達到最大值。');
        if(!$badge->location_claim_enabled) {
            $issued=$service->award($badge,$request->user()->id,'public_qr',null,null,['criteria_snapshot'=>'登入會員帳號並掃描官方 QR Code']);
            return back()->with($issued?'success':'error',$issued?'Badge 已取得。':($badge->fresh()->isAtCapacity()?'徽章數量已達到最大值。':'你已經取得這枚 Badge。'));
        }
        $data=$request->validate(['lat'=>['required','numeric','between:-90,90'],'lng'=>['required','numeric','between:-180,180'],'accuracy'=>['required','numeric','min:0','max:5000']]);
        $distance=$this->distanceKm($badge->claim_lat,$badge->claim_lng,(float)$data['lat'],(float)$data['lng']);
        if($distance > $badge->claim_radius_km) return back()->with('error','目前不在 Badge 發放區域內。');
        $issued=$service->award($badge,$request->user()->id,'location_qr',null,null,['criteria_snapshot'=>'掃描 QR Code 並完成位置驗證']);
        return back()->with($issued?'success':'error',$issued?'位置驗證成功，Badge 已取得。':($badge->fresh()->isAtCapacity()?'徽章數量已達到最大值。':'你已經取得這枚 Badge。'));
    }

    public function qrCode(string $token): Response
    {
        $badge=$this->badge($token);
        $svg=(new Writer(new ImageRenderer(new RendererStyle(480,2),new SvgImageBackEnd)))->writeString(route('badge-drops.show',$badge->claim_token));
        return response($svg)->header('Content-Type','image/svg+xml')->header('Cache-Control','private, no-store');
    }

    private function badge(string $token): EventBadge
    {
        return EventBadge::whereNull('event_id')->whereIn('issuer_type',['platform','organizer'])->where('claim_token',$token)->firstOrFail();
    }

    private function distanceKm(float $lat1,float $lng1,float $lat2,float $lng2): float
    {
        $earth=6371; $latDelta=deg2rad($lat2-$lat1); $lngDelta=deg2rad($lng2-$lng1);
        $a=sin($latDelta/2)**2+cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin($lngDelta/2)**2;
        return $earth*2*atan2(sqrt($a),sqrt(1-$a));
    }
}
