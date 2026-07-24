<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompleteProfileRequest;
use App\Models\User;
use App\Models\UserProfile;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ProfileCompletionController extends Controller
{
    public function index(Request $request)
    {
        return view('profile.index', [
            'user' => $request->user()->load(['profile', 'eventBadges' => fn ($query) => $query->whereNull('revoked_at')->with('badge.event')]),
        ]);
    }

    public function edit(Request $request)
    {
        $profile = $request->user()->profile;
        return view('profile.complete', [
            'profile' => $profile,
        ]);
    }

    public function update(CompleteProfileRequest $request)
    {
        $user = $request->user();

        $user->forceFill([
            'nickname' => $request->safe()->string('nickname')->toString() ?: null,
        ])->save();

        $profile = UserProfile::updateOrCreate(
            ['user_id' => $user->id],
            $request->safe()->except(['agree_terms', 'nickname']) + [
                'consent_signed_at' => now(),
                'consent_version'   => config('legal.consent_version', 'v1'),
            ]
        );

        if (is_null($user->profile_completed_at)) {
            $user->forceFill(['profile_completed_at' => now()])->save();
        }

        return redirect()->route('member-profile.index')->with('status', '會員資料已更新！');
    }

    public function qrCode(Request $request): Response
    {
        $renderer = new ImageRenderer(new RendererStyle(320, 2), new SvgImageBackEnd());
        $svg = (new Writer($renderer))->writeString(route('members.show', $request->user()->uuid));

        return response($svg)->header('Content-Type', 'image/svg+xml')->header('Cache-Control', 'private, max-age=3600');
    }

    public function scan()
    {
        return view('profile.scan');
    }

    public function show(User $user)
    {
        return view('profile.show', [
            'member' => $user->load(['profile', 'eventBadges' => fn ($query) => $query->whereNull('revoked_at')->with('badge.event')]),
        ]);
    }
}
