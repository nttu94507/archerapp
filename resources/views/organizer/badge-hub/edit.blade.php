@extends('layouts.app')
@section('title','編輯 '.$badge->name)
@section('content')
<div class="mx-auto max-w-3xl space-y-6 px-4 py-6 sm:px-6">
    <div><a href="{{route('organizer.badges.index')}}" class="text-sm text-indigo-600">← 返回 Badge 列表</a><div class="mt-2 flex items-center gap-3"><img src="{{$badge->icon_url}}" class="h-14 w-14 rounded-2xl object-cover"><h1 class="text-2xl font-bold">編輯 {{$badge->name}}</h1></div></div>
    @if(session('success'))<div class="rounded-xl bg-green-50 p-4 text-sm text-green-700">{{session('success')}}</div>@endif
    @if(session('error'))<div class="rounded-xl bg-red-50 p-4 text-sm text-red-700">{{session('error')}}</div>@endif
    @if($errors->any())<div class="rounded-xl bg-red-50 p-4 text-sm text-red-700">{{$errors->first()}}</div>@endif
    <section class="rounded-2xl border bg-white p-5 shadow-sm sm:p-6">@include('organizer.badge-hub._form',['badge'=>$badge])</section>
    @if($badge->is_active && !$badge->isAtCapacity())
    <section class="rounded-2xl border bg-white p-5 shadow-sm"><h2 class="font-semibold">人工發放</h2><form method="POST" action="{{route('organizer.badges.award',$badge)}}" class="mt-4 grid gap-3 sm:grid-cols-[1fr_1fr_auto]">@csrf<input name="member" required class="min-h-11 rounded-xl border-gray-300 text-sm" placeholder="會員 UUID 或 Email"><input name="note" class="min-h-11 rounded-xl border-gray-300 text-sm" placeholder="授予原因（選填）"><button class="min-h-11 rounded-xl bg-gray-900 px-5 text-sm text-white">發放</button></form></section>
    @endif
</div>
@endsection
