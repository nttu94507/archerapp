@extends('layouts.app')

@section('title', '申請 '.$badge->name)

@section('content')
<div class="mx-auto max-w-lg px-4 py-10 sm:px-6">
    <div class="rounded-2xl border bg-white p-6 shadow-sm">
        <p class="text-sm font-medium text-indigo-600">賽事 Badge 申請</p>
        <div class="mt-2 flex items-center gap-3"><img src="{{ $badge->icon_url }}" alt="" class="h-16 w-16 rounded-2xl object-cover"><h1 class="text-2xl font-bold">{{ $badge->name }}</h1></div>
        <p class="mt-2 text-gray-600">{{ $badge->event->name }}</p>
        @if($badge->description)<p class="mt-4 rounded-xl bg-gray-50 p-4 text-sm text-gray-600">{{ $badge->description }}</p>@endif
        @if($badge->claim_starts_at || $badge->claim_ends_at)<p class="mt-3 text-sm text-gray-500">申請期間：{{ $badge->claim_starts_at?->format('Y/m/d H:i') ?? '現在' }}－{{ $badge->claim_ends_at?->format('Y/m/d H:i') ?? '不限' }}</p>@endif

        <div class="mt-5 rounded-xl border p-4 text-sm"><p class="font-medium {{ $eligible ? 'text-green-700' : 'text-orange-700' }}">{{ $eligible ? '系統初步判定符合資格' : '需要主辦方人工確認' }}</p><p class="mt-1 text-gray-500">{{ $note }}</p></div>

        @if(session('success'))<div class="mt-4 rounded-xl bg-green-50 p-4 text-sm text-green-700">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="mt-4 rounded-xl bg-red-50 p-4 text-sm text-red-700">{{ session('error') }}</div>@endif

        @if($claim)
            <div class="mt-5 rounded-xl bg-indigo-50 p-4 text-sm text-indigo-700">申請狀態：{{ ['pending'=>'等待主辦方確認','needs_review'=>'等待主辦方人工確認','approved'=>'已通過並授予','rejected'=>'申請未通過'][$claim->status] ?? $claim->status }}</div>
        @elseif($badge->isClaimOpen())
            <form method="POST" action="{{ route('badge-claims.store', $badge->claim_token) }}" class="mt-6">@csrf<button class="w-full rounded-xl bg-indigo-600 px-5 py-3 font-medium text-white hover:bg-indigo-500">確認送出申請</button></form>
        @else
            <p class="mt-5 rounded-xl bg-gray-100 p-4 text-sm text-gray-600">此 Badge 目前未開放申請。</p>
        @endif
        <p class="mt-4 text-center text-xs text-gray-500">掃描不會立即取得 Badge，須經主辦方確認。</p>
    </div>
</div>
@endsection
