<?php

namespace App\Http\Controllers;

use App\Models\EventBadge;
use App\Models\EventBadgeClaim;
use App\Models\EventRegistration;
use App\Models\EventScoreEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EventBadgeClaimController extends Controller
{
    public function show(Request $request, string $token): View
    {
        $badge = EventBadge::with('event')->where('claim_token', $token)->firstOrFail();
        $claim = $badge->claims()->where('user_id', $request->user()->id)->first();
        [$eligible, $note] = $this->eligibility($badge, $request->user()->id);

        return view('badge-claims.show', compact('badge', 'claim', 'eligible', 'note'));
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $badge = EventBadge::with('event')->where('claim_token', $token)->firstOrFail();

        if (! $badge->isClaimOpen()) {
            return back()->with('error', '此 Badge 目前未開放申請。');
        }

        if ($badge->awards()->where('user_id', $request->user()->id)->whereNull('revoked_at')->exists()) {
            return back()->with('error', '你已經取得這枚 Badge。');
        }

        [$eligible, $note] = $this->eligibility($badge, $request->user()->id);
        EventBadgeClaim::firstOrCreate(
            ['event_badge_id' => $badge->id, 'user_id' => $request->user()->id],
            [
                'status' => $eligible ? 'pending' : 'needs_review',
                'is_eligible' => $eligible,
                'eligibility_note' => $note,
            ]
        );

        return back()->with('success', '申請已送出，等待主辦方確認。');
    }

    /** @return array{bool,string} */
    private function eligibility(EventBadge $badge, int $userId): array
    {
        if ($badge->eligibility === 'any') {
            return [true, '不限資格'];
        }

        $registration = EventRegistration::where('event_id', $badge->event_id)
            ->where('user_id', $userId)->first();

        if (! $registration || in_array($registration->status, ['withdrawn', 'refunded', 'no_show'], true)) {
            return [false, '找不到有效報名資料'];
        }

        if ($badge->eligibility === 'registered') {
            return [true, '已有有效報名'];
        }

        if ($badge->eligibility === 'checked_in') {
            return [$registration->status === 'checked_in', $registration->status === 'checked_in' ? '已完成報到' : '尚未完成報到'];
        }

        $hasScore = EventRegistration::where('event_id', $badge->event_id)
            ->where('user_id', $userId)->whereNotNull('score_verified_at')->exists();
        return [$hasScore, $hasScore ? '已有主辦方確認成績' : '尚無主辦方確認成績'];
    }
}
