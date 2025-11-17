<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleController extends Controller
{
    //
    public function redirect()
    {
        // 需要切換帳號時可加 ->with(['prompt' => 'select_account'])
        return Socialite::driver('google')->redirect();
    }

    public function callback()
    {
//        dd(123);
        try {
            // 在多節點/反向代理時建議 stateless()
            $googleUser = Socialite::driver('google')->stateless()->user();
        } catch (\Throwable $e) {
            return redirect()->route('login')->withErrors(['google' => 'Google 驗證失敗，請再試一次。']);
        }

        // 1) 先用 google_id 找
        $user = User::where('google_id', $googleUser->getId())->first();

        // 2) 沒找到就用 email 對應舊帳號，避免重複
        if (!$user && $googleUser->getEmail()) {
            $user = User::where('email', $googleUser->getEmail())->first();
        }

        // 3) 若完全沒有，就建立新使用者
        if (!$user) {
            $user = User::create([
                'name'              => $googleUser->getName() ?: 'Google 使用者',
                'email'             => $googleUser->getEmail(),
                'google_id'         => $googleUser->getId(),
                'google_avatar'     => $googleUser->getAvatar(),
                'password'          => bcrypt(Str::random(40)),
                'email_verified_at' => now(), // Google 有回 email 就當作已驗證
            ]);
        } else {
            // 補綁 google_id / avatar（若原本沒有）
            if (!$user->google_id) {
                $user->google_id = $googleUser->getId();
            }
            if (!$user->google_avatar && $googleUser->getAvatar()) {
                $user->google_avatar = $googleUser->getAvatar();
            }
            $user->save();
        }

        Auth::login($user, true); // 記住我 = true
        return redirect()->back(); // 或你的會員首頁
    }
}
