<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class LoginController extends Controller
{
    //
    public function options()
    {
        return view('layouts.login');
    }

    public function logout(Request $request)
    {
        auth()->logout();                      // 登出使用者
        $request->session()->invalidate();     // 讓舊 session 作廢
        $request->session()->regenerateToken();// 更新 CSRF token

        return redirect('/')->with('status', '已成功登出');
    }
}
