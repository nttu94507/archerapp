<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApprovedOrganizer
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->canCreateEvents(), 403, '帳號目前已被停止建立新賽事，請聯絡平台管理員。');
        return $next($request);
    }
}
