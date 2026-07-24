@extends('layouts.app')
@section('title','Badge 管理')
@section('content')
<div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6" x-data="{qr:null}">
    <div class="flex items-center justify-between gap-4"><div><p class="text-xs font-semibold uppercase tracking-widest text-indigo-600">Platform Admin</p><h1 class="mt-1 text-2xl font-bold">Badge 管理</h1></div><a href="{{route('admin.badges.create')}}" class="inline-flex min-h-11 items-center rounded-xl bg-indigo-600 px-5 text-sm font-medium text-white">新增官方 Badge</a></div>
    @if(session('success'))<div class="rounded-xl bg-green-50 p-4 text-sm text-green-700">{{session('success')}}</div>@endif
    @if(session('error'))<div class="rounded-xl bg-red-50 p-4 text-sm text-red-700">{{session('error')}}</div>@endif

    <nav class="grid grid-cols-3 gap-2 rounded-2xl bg-gray-100 p-1">
        @foreach(['all'=>'全部','official'=>'官方發放','organization'=>'單位發放'] as $value=>$label)
            <a href="{{route('admin.badges.index',$value==='all'?[]:['source'=>$value])}}" class="rounded-xl px-3 py-3 text-center text-sm font-medium {{$source===$value?'bg-white text-indigo-700 shadow-sm':'text-gray-600'}}">{{$label}} <span class="ml-1 text-xs">{{$sourceCounts[$value]}}</span></a>
        @endforeach
    </nav>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse($badges as $badge)
            @php($official=$badge->issuer_type==='platform')
            <article class="flex flex-col rounded-2xl border bg-white p-5 shadow-sm">
                <div class="flex items-start gap-3"><img src="{{$badge->icon_url}}" class="h-16 w-16 shrink-0 rounded-2xl object-cover"><div class="min-w-0 flex-1"><div class="flex items-start justify-between gap-2"><h2 class="break-words font-semibold">{{$badge->name}}</h2><span class="shrink-0 rounded-full px-2 py-1 text-xs {{$official?'bg-purple-100 text-purple-700':'bg-blue-100 text-blue-700'}}">{{$official?'官方發放':'單位發放'}}</span></div><p class="mt-1 text-xs text-gray-500">{{$badge->issuer_name ?: '未設定單位'}}{{ $badge->display_activity_name ? '・'.$badge->display_activity_name : '' }}</p><span class="mt-2 inline-flex rounded-full px-2 py-1 text-xs {{$badge->is_active?'bg-green-100 text-green-700':'bg-red-100 text-red-700'}}">{{$badge->is_active?'正常':'平台停用'}}</span></div></div>
                @if($badge->description)<p class="mt-4 line-clamp-2 text-sm text-gray-600">{{$badge->description}}</p>@endif
                <div class="mt-4 grid grid-cols-3 gap-2 text-center"><div class="rounded-xl bg-gray-50 p-2"><p class="font-semibold">{{$badge->claims_count}}</p><p class="text-xs text-gray-500">申請</p></div><div class="rounded-xl bg-gray-50 p-2"><p class="font-semibold">{{$badge->active_awards_count}}</p><p class="text-xs text-gray-500">已發放</p></div><div class="rounded-xl bg-gray-50 p-2"><p class="font-semibold">{{$badge->max_supply??'∞'}}</p><p class="text-xs text-gray-500">上限</p></div></div>

                @if($official && $badge->is_active)<form method="POST" action="{{route('admin.badges.award-all',$badge)}}" class="mt-4 border-t pt-4" onsubmit="return confirm('確定將「{{$badge->name}}」派發給目前全站 {{$memberCount}} 個帳號？此操作不會重複派發。')">@csrf<button class="min-h-11 w-full rounded-xl bg-purple-600 px-4 text-sm font-medium text-white hover:bg-purple-500">全站派發</button><p class="mt-2 text-center text-xs text-gray-500">已取得的會員會自動跳過</p></form>@endif

                <div class="mt-auto grid grid-cols-2 gap-2 pt-4">
                    <button type="button" @if($badge->location_claim_enabled || $badge->claim_enabled) @click="qr={{Illuminate\Support\Js::from(['name'=>$badge->name,'url'=>route('badge-drops.qrcode',$badge->claim_token),'enabled'=>$badge->is_active&&($badge->location_claim_enabled||$badge->claim_enabled)])}}" @else disabled @endif class="min-h-11 rounded-xl border text-sm {{($badge->location_claim_enabled||$badge->claim_enabled)?'border-indigo-200 text-indigo-700':'cursor-not-allowed border-gray-200 text-gray-400'}}">顯示 QR Code</button>
                    <form method="POST" action="{{route('admin.badges.toggle',$badge)}}">@csrf @method('PATCH')<button class="min-h-11 w-full rounded-xl border text-sm {{$badge->is_active?'border-red-200 text-red-600':'border-green-200 text-green-700'}}">{{$badge->is_active?'平台停用':'重新啟用'}}</button></form>
                </div>
                @if($badge->event)<a href="{{route('organizer.events.badges.show',[$badge->event,$badge])}}" class="mt-2 inline-flex min-h-11 items-center justify-center rounded-xl border text-sm">查看發放紀錄</a>@endif
            </article>
        @empty
            <p class="rounded-2xl border border-dashed bg-white p-10 text-center text-gray-500 md:col-span-2 xl:col-span-3">目前沒有符合篩選條件的 Badge。</p>
        @endforelse
    </div>
    {{$badges->links()}}

    <div x-show="qr" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" @click.self="qr=null" @keydown.escape.window="qr=null"><div class="w-full max-w-sm rounded-3xl bg-white p-6 text-center"><div class="flex justify-between gap-3"><h2 class="font-semibold" x-text="qr?.name"></h2><button @click="qr=null">✕</button></div><img :src="qr?.url" alt="定位 QR Code" class="mx-auto mt-4 h-64 w-64 rounded-2xl border p-3"><p class="mt-3 rounded-xl p-3 text-sm" :class="qr?.enabled?'bg-green-50 text-green-700':'bg-yellow-50 text-yellow-800'" x-text="qr?.enabled?'目前可掃描領取':'目前無法領取'"></p></div></div>
</div>
@endsection
