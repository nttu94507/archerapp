<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApprovedOrganizer
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->canCreateEvents(), 403, '主辦方資格尚未核准，無法建立新賽事。');
        return $next($request);
    }
}
