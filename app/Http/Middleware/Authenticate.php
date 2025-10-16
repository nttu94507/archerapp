<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Auth\Middleware\Authenticate as BaseAuthenticate;

class Authenticate extends BaseAuthenticate
{
//    /**
//     * Handle an incoming request.
//     *
//     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
//     */
//    public function handle(Request $request, Closure $next): Response
//    {
//        return $next($request);
//    }

    protected function redirectTo($request): ?string
    {
        if (! $request->expectsJson()) {
            // 直接丟去 Google OAuth（請先建立對應路由）
            return route('login.options');
            // 若你想先到自家 /login 再點 Google，可以改成：
            // return route('login');
        }
        return null; // API/JSON 請求不做 302
    }
}
