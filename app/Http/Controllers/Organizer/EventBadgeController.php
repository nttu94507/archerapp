<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventBadge;
use App\Models\EventBadgeClaim;
use App\Models\UserEventBadge;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EventBadgeController extends Controller
{
    public function index(Request $request, Event $event): View
    {
        $this->authorizeOrganizer($request, $event);

        $badges = $event->badges()
            ->withCount([
                'claims',
                'claims as pending_claims_count' => fn ($query) => $query->whereIn('status', ['pending', 'needs_review']),
                'awards as active_awards_count' => fn ($query) => $query->whereNull('revoked_at'),
            ])
            ->latest()->get();

        return view('organizer.badges.index', compact('event', 'badges'));
    }

    public function store(Request $request, Event $event): RedirectResponse
    {
        $this->authorizeOrganizer($request, $event);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['required', 'in:participant,finisher,staff,volunteer,special'],
            'eligibility' => ['required', 'in:any,registered,checked_in,scored'],
            'claim_starts_at' => ['nullable', 'date'],
            'claim_ends_at' => ['nullable', 'date', 'after:claim_starts_at'],
        ]);

        $badge = $event->badges()->create($validated + [
            'created_by' => $request->user()->id,
            'claim_enabled' => $request->boolean('claim_enabled'),
        ]);

        return redirect()->route('organizer.events.badges.show', [$event, $badge])
            ->with('success', 'Badge 已建立。');
    }

    public function show(Request $request, Event $event, EventBadge $badge): View
    {
        $this->authorizeBadge($request, $event, $badge);

        $claims = $badge->claims()->with('user')->latest()->get();
        $awards = $badge->awards()->with('user')->latest('awarded_at')->get();

        return view('organizer.badges.show', compact('event', 'badge', 'claims', 'awards'));
    }

    public function update(Request $request, Event $event, EventBadge $badge): RedirectResponse
    {
        $this->authorizeBadge($request, $event, $badge);

        $validated = $request->validate([
            'claim_starts_at' => ['nullable', 'date'],
            'claim_ends_at' => ['nullable', 'date', 'after:claim_starts_at'],
        ]);

        $badge->update($validated + ['claim_enabled' => $request->boolean('claim_enabled')]);

        return back()->with('success', 'QR Code 申請設定已更新。');
    }

    public function regenerateToken(Request $request, Event $event, EventBadge $badge): RedirectResponse
    {
        $this->authorizeBadge($request, $event, $badge);
        $badge->update(['claim_token' => (string) \Illuminate\Support\Str::uuid()]);

        return back()->with('success', '已重新產生 QR Code，舊 QR Code 已失效。');
    }

    public function qrCode(Request $request, Event $event, EventBadge $badge): Response
    {
        $this->authorizeBadge($request, $event, $badge);
        $renderer = new ImageRenderer(new RendererStyle(480, 2), new SvgImageBackEnd());
        $svg = (new Writer($renderer))->writeString(route('badge-claims.show', $badge->claim_token));

        return response($svg)->header('Content-Type', 'image/svg+xml')->header('Cache-Control', 'private, no-store');
    }

    public function bulkReview(Request $request, Event $event, EventBadge $badge): RedirectResponse
    {
        $this->authorizeBadge($request, $event, $badge);
        $validated = $request->validate([
            'action' => ['required', 'in:approve,reject'],
            'claim_ids' => ['required', 'array', 'min:1'],
            'claim_ids.*' => ['integer'],
            'review_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $claims = $badge->claims()
            ->whereIn('id', $validated['claim_ids'])
            ->whereIn('status', ['pending', 'needs_review'])
            ->get();
        abort_if($claims->count() !== count(array_unique($validated['claim_ids'])), 422, '包含無效的申請。');

        DB::transaction(function () use ($claims, $validated, $request, $badge): void {
            foreach ($claims as $claim) {
                $approved = $validated['action'] === 'approve';
                $claim->update([
                    'status' => $approved ? 'approved' : 'rejected',
                    'reviewed_by' => $request->user()->id,
                    'reviewed_at' => now(),
                    'review_note' => $validated['review_note'] ?? null,
                ]);

                if ($approved) {
                    UserEventBadge::updateOrCreate(
                        ['event_badge_id' => $badge->id, 'user_id' => $claim->user_id],
                        [
                            'event_badge_claim_id' => $claim->id,
                            'awarded_by' => $request->user()->id,
                            'awarded_at' => now(),
                            'revoked_by' => null,
                            'revoked_at' => null,
                            'revoked_reason' => null,
                        ]
                    );
                }
            }
        });

        return back()->with('success', '已批次處理 '.$claims->count().' 筆申請。');
    }

    private function authorizeBadge(Request $request, Event $event, EventBadge $badge): void
    {
        abort_unless($badge->event_id === $event->id, 404);
        $this->authorizeOrganizer($request, $event);
    }

    private function authorizeOrganizer(Request $request, Event $event): void
    {
        $user = $request->user();
        $allowed = $user->isAdmin() || ($user->organizerProfile()->where('status', 'suspended')->doesntExist() && $event->staff()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->whereIn('role', ['owner', 'manager'])
            ->exists());

        abort_unless($allowed, 403);
    }
}
