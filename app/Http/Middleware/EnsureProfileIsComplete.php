<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProfileIsComplete
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {

        $user = $request->user();
        if ($user && is_null($user->profile_completed_at)) {
            // 允許通過的白名單路由：查看/提交個資、登出等
            if (! $request->routeIs(['profile.complete', 'profile.store', 'logout'])) {
                return redirect()->route('user.profile.completion');
            }
        }
        return $next($request);
    }
}
