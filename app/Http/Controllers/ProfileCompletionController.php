<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompleteProfileRequest;
use App\Models\UserProfile;
use Illuminate\Http\Request;

class ProfileCompletionController extends Controller
{
    //
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

        $profile = UserProfile::updateOrCreate(
            ['user_id' => $user->id],
            $request->safe()->except(['agree_terms']) + [
                'consent_signed_at' => now(),
                'consent_version'   => config('legal.consent_version', 'v1'),
            ]
        );

        if (is_null($user->profile_completed_at)) {
            $user->forceFill(['profile_completed_at' => now()])->save();
        }

        return redirect()->intended(route('leaderboards.index')) // 或 events.index
        ->with('status', '資料已完成！');
    }
}
