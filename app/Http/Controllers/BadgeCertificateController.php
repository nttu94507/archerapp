<?php

namespace App\Http\Controllers;

use App\Models\UserEventBadge;
use Illuminate\View\View;

class BadgeCertificateController extends Controller
{
    public function show(string $publicId): View
    {
        $award=UserEventBadge::with(['badge','user'])->where('public_id',$publicId)->firstOrFail();
        return view('badges.certificate',compact('award'));
    }
}
