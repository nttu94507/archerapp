@extends('layouts.app')
@section('title','Badge 列表')
@section('content')
<div class="mx-auto max-w-6xl space-y-6 px-4 py-6 sm:px-6" x-data="{ qr: null }">
    <div class="flex items-center justify-between gap-4">
        <div><h1 class="text-2xl font-bold">Badge 列表</h1><p class="mt-1 text-sm text-gray-500">管理主辦單位建立的 Badge。</p></div>
        <a href="{{ route('organizer.badges.create') }}" class="inline-flex min-h-11 shrink-0 items-center rounded-xl bg-indigo-600 px-5 text-sm font-medium text-white">新增 Badge</a>
    </div>
    @if(session('success'))<div class="rounded-xl bg-green-50 p-4 text-sm text-green-700">{{session('success')}}</div>@endif
    @if(session('error'))<div class="rounded-xl bg-red-50 p-4 text-sm text-red-700">{{session('error')}}</div>@endif

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse($badges as $badge)
            <article role="link" tabindex="0" onclick="window.location='{{ route('organizer.badges.edit',$badge) }}'" onkeydown="if(event.key==='Enter')window.location='{{ route('organizer.badges.edit',$badge) }}'" class="cursor-pointer rounded-2xl border bg-white p-5 shadow-sm transition hover:border-indigo-300 hover:shadow">
                <div class="flex items-start gap-3">
                    <img src="{{ $badge->icon_url }}" alt="" class="h-16 w-16 shrink-0 rounded-2xl object-cover">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-start justify-between gap-2"><h2 class="break-words font-semibold">{{ $badge->name }}</h2><span class="shrink-0 rounded-full px-2 py-1 text-xs {{ !$badge->is_active ? 'bg-red-100 text-red-700' : ($badge->location_claim_enabled ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-800') }}">{{ !$badge->is_active ? '平台停用' : ($badge->location_claim_enabled ? '開放領取' : '未開放') }}</span></div>
                        <p class="mt-1 truncate text-sm text-gray-500">{{ $badge->external_activity_name }}</p>
                        <p class="mt-1 text-xs text-gray-500">已發放 {{ $badge->awards_count }}{{ $badge->max_supply ? '／'.$badge->max_supply : '' }}</p>
                    </div>
                </div>
                @if($badge->description)<p class="mt-4 line-clamp-2 text-sm text-gray-600">{{ $badge->description }}</p>@endif
                <div class="mt-5 grid grid-cols-2 gap-2 border-t pt-4" onclick="event.stopPropagation()">
                    <button type="button" @if($badge->claim_lat !== null && $badge->claim_lng !== null) @click.stop="qr={{ Illuminate\Support\Js::from(['name'=>$badge->name,'url'=>route('badge-drops.qrcode',$badge->claim_token),'enabled'=>$badge->is_active && $badge->location_claim_enabled]) }}" @else disabled @endif class="min-h-11 rounded-xl border text-sm font-medium {{ $badge->claim_lat !== null ? 'border-indigo-200 text-indigo-700' : 'cursor-not-allowed border-gray-200 text-gray-400' }}">顯示 QR Code</button>
                    @if($badge->claim_lat !== null && $badge->claim_lng !== null && $badge->is_active)
                        <form method="POST" action="{{route('organizer.badges.claim-toggle',$badge)}}">@csrf @method('PATCH')<button class="min-h-11 w-full rounded-xl border text-sm font-medium {{ $badge->location_claim_enabled ? 'border-red-200 text-red-600' : 'border-green-200 text-green-700' }}">{{ $badge->location_claim_enabled ? '停用' : '啟用' }}</button></form>
                    @else
                        <button disabled class="min-h-11 cursor-not-allowed rounded-xl border border-gray-200 text-sm text-gray-400">停用</button>
                    @endif
                </div>
            </article>
        @empty
            <div class="rounded-2xl border border-dashed bg-white p-10 text-center sm:col-span-2 lg:col-span-3"><p class="text-gray-500">尚未建立 Badge。</p><a href="{{route('organizer.badges.create')}}" class="mt-4 inline-flex min-h-11 items-center rounded-xl bg-indigo-600 px-5 text-sm text-white">新增第一個 Badge</a></div>
        @endforelse
    </div>

    <div x-show="qr" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" @click.self="qr=null" @keydown.escape.window="qr=null">
        <div class="w-full max-w-sm rounded-3xl bg-white p-6 text-center shadow-xl">
            <div class="flex items-start justify-between gap-3"><h2 class="text-lg font-semibold" x-text="qr?.name"></h2><button type="button" @click="qr=null" class="rounded-lg p-2 text-gray-500">✕</button></div>
            <img :src="qr?.url" alt="Badge QR Code" class="mx-auto mt-4 h-64 w-64 rounded-2xl border bg-white p-3">
            <p class="mt-3 rounded-xl p-3 text-sm" :class="qr?.enabled ? 'bg-green-50 text-green-700' : 'bg-yellow-50 text-yellow-800'" x-text="qr?.enabled ? '目前可掃描領取' : '目前已停用，QR Code 暫時無法領取'"></p>
        </div>
    </div>
</div>
@endsection
