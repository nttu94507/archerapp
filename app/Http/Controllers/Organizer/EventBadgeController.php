<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventBadge;
use App\Models\EventBadgeClaim;
use App\Models\UserEventBadge;
use App\Services\EventBadgeAwardService;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

        $groups = $event->groups()->orderBy('name')->get();
        return view('organizer.badges.index', compact('event', 'badges', 'groups'));
    }

    public function store(Request $request, Event $event, EventBadgeAwardService $service): RedirectResponse
    {
        $this->authorizeOrganizer($request, $event);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'icon' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'type' => ['required', 'in:participant,finisher,staff,volunteer,special'],
            'eligibility' => ['nullable', 'in:any,registered,checked_in,scored'],
            'award_rule' => ['nullable', 'in:manual,attendance,placement,staff,volunteer'],
            'event_group_id' => ['nullable', 'integer', 'exists:event_groups,id'],
            'placement' => ['nullable', 'integer', 'between:1,3'],
            'claim_starts_at' => ['nullable', 'date'],
            'claim_ends_at' => ['nullable', 'date', 'after:claim_starts_at'],
        ]);
        $validated['award_rule'] ??= 'manual';
        $validated['eligibility'] = in_array($validated['award_rule'], ['staff', 'volunteer'], true)
            ? 'any'
            : ($validated['eligibility'] ?? 'registered');
        if ($validated['award_rule'] === 'placement') {
            abort_unless(! empty($validated['event_group_id']) && ! empty($validated['placement']), 422, '名次 Badge 必須選擇組別與名次。');
        }
        if (! empty($validated['event_group_id'])) abort_unless($event->groups()->whereKey($validated['event_group_id'])->exists(), 422, '組別不屬於此賽事。');

        unset($validated['icon']);
        if ($request->hasFile('icon')) {
            $validated['icon_path'] = $request->file('icon')->store('badge-icons', 'public');
        }
        $badge = $event->badges()->create($validated + [
            'created_by' => $request->user()->id,
            'claim_enabled' => $request->boolean('claim_enabled'),
        ]);
        $service->awardExistingTeamFor($badge);

        return redirect()->route('organizer.events.badges.show', [$event, $badge])
            ->with('success', 'Badge 已建立。');
    }

    public function manualAward(Request $request, Event $event, EventBadge $badge, EventBadgeAwardService $service): RedirectResponse
    {
        $this->authorizeBadge($request, $event, $badge);
        abort_unless($badge->award_rule === 'manual', 422, '只有主辦方授予的 Badge 可以手動發放。');
        $validated = $request->validate(['user_ids'=>['required','array','min:1'],'user_ids.*'=>['integer'],'award_note'=>['nullable','string','max:1000']]);
        $eligibleIds = $event->registrations()->whereIn('status',['registered','checked_in'])->whereIn('user_id',$validated['user_ids'])->pluck('user_id')->unique();
        abort_if($eligibleIds->count() !== count(array_unique($validated['user_ids'])), 422, '選取名單包含非本賽事選手。');
        foreach ($eligibleIds as $userId) $service->award($badge, $userId, 'manual', $request->user()->id, $validated['award_note'] ?? null);
        return back()->with('success', '已授予 '.$eligibleIds->count().' 位選手。');
    }

    public function show(Request $request, Event $event, EventBadge $badge): View
    {
        $this->authorizeBadge($request, $event, $badge);

        $claims = $badge->claims()->with('user')->latest()->get();
        $awards = $badge->awards()->with('user')->latest('awarded_at')->get();
        $participants = $event->registrations()->with('event_group')->whereIn('status', ['registered','checked_in'])
            ->when($badge->event_group_id, fn ($query) => $query->where('event_group_id', $badge->event_group_id))
            ->orderBy('event_group_id')->orderBy('name')->get()->unique('user_id');

        return view('organizer.badges.show', compact('event', 'badge', 'claims', 'awards', 'participants'));
    }

    public function update(Request $request, Event $event, EventBadge $badge): RedirectResponse
    {
        $this->authorizeBadge($request, $event, $badge);

        $validated = $request->validate([
            'claim_starts_at' => ['nullable', 'date'],
            'claim_ends_at' => ['nullable', 'date', 'after:claim_starts_at'],
            'icon' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);

        unset($validated['icon']);
        if ($request->hasFile('icon')) {
            $newPath = $request->file('icon')->store('badge-icons', 'public');
            if ($badge->icon_path) Storage::disk('public')->delete($badge->icon_path);
            $validated['icon_path'] = $newPath;
        }
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
