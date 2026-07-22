<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EventBadge;
use App\Models\UserEventBadge;
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
