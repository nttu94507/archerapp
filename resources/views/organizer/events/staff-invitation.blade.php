@extends('layouts.app')
@section('title', '加入賽事工作團隊')
@section('content')
@php($roleLabel = ['manager'=>'管理者','staff'=>'工作人員','viewer'=>'只讀人員'][$role])
<div class="mx-auto max-w-lg px-4 py-8 sm:px-6">
    <section class="rounded-2xl border bg-white p-5 text-center shadow-sm sm:p-7">
        <p class="text-xs font-semibold uppercase tracking-widest text-indigo-600">Staff Invitation</p>
        <h1 class="mt-2 break-words text-2xl font-bold">加入 {{ $event->name }}</h1>
        <p class="mt-3 text-sm leading-6 text-gray-600">你將以「{{ $roleLabel }}」身分加入賽事工作團隊。</p>
        <div class="mt-5 rounded-xl bg-gray-50 p-4 text-left text-sm">
            <p class="text-gray-500">主辦單位</p><p class="mt-1 font-medium">{{ $event->organizer }}</p>
            <p class="mt-3 text-gray-500">賽事日期</p><p class="mt-1 font-medium">{{ $event->start_date->format('Y-m-d') }}～{{ $event->end_date->format('Y-m-d') }}</p>
        </div>
        <form method="POST" action="{{ request()->fullUrl() }}" class="mt-5">@csrf
            <button class="min-h-12 w-full rounded-xl bg-indigo-600 px-5 text-sm font-medium text-white">確認加入工作團隊</button>
        </form>
        <a href="{{ route('events.index') }}" class="mt-3 inline-flex min-h-11 items-center text-sm text-gray-500">暫時不要</a>
    </section>
</div>
@endsection
