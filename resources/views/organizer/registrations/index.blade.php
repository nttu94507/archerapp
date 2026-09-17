@extends('layouts.app')

@section('title', $event->name.' 選手名單')

@section('content')
@php
    $statusLabels = ['registered'=>'已報名','checked_in'=>'已報到','withdrawn'=>'已退出','refunded'=>'已取消','no_show'=>'未出賽（DNS）'];
    $statusColors = ['registered'=>'bg-indigo-50 text-indigo-700','checked_in'=>'bg-emerald-50 text-emerald-700','withdrawn'=>'bg-gray-100 text-gray-600','refunded'=>'bg-gray-100 text-gray-600','no_show'=>'bg-amber-50 text-amber-700'];
@endphp

<div class="mx-auto max-w-6xl space-y-5 px-4 py-6 sm:px-6 sm:py-8">
    <header>
        <a href="{{ $selectedGroup ? route('organizer.events.registrations.index', $event) : route('organizer.events.show', $event) }}" class="inline-flex min-h-11 items-center text-sm font-medium text-indigo-600">← {{ $selectedGroup ? '全部組別' : '返回賽事工作台' }}</a>
        <h1 class="mt-1 text-2xl font-bold">{{ $selectedGroup?->name ?? '選手名單' }}</h1>
    </header>

    @if(session('success'))<div class="rounded-xl bg-green-50 p-4 text-sm text-green-700">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="rounded-xl bg-red-50 p-4 text-sm text-red-700">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="rounded-xl bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>@endif

    @if(!$selectedGroup)
        <section class="grid grid-cols-2 gap-3">
            <div class="rounded-2xl border bg-white p-4 text-center shadow-sm"><p class="text-2xl font-bold">{{ $totals['groups'] }}</p><p class="mt-1 text-xs text-gray-500">組別</p></div>
            <div class="rounded-2xl border bg-white p-4 text-center shadow-sm"><p class="text-2xl font-bold">{{ $totals['registrations'] }}</p><p class="mt-1 text-xs text-gray-500">有效報名</p></div>
        </section>

        <section>
            <h2 class="mb-3 text-lg font-semibold">選擇組別</h2>
            <div class="grid gap-4 md:grid-cols-2">
                @forelse($groups as $group)
                    @php($active = (int) $group->active_registrations_count)
                    <a href="{{ route('organizer.events.registrations.index', [$event, 'event_group_id' => $group->id]) }}" class="group rounded-2xl border bg-white p-5 shadow-sm transition hover:border-indigo-300 hover:shadow-md">
                        <div class="flex items-center justify-between gap-3">
                            <div class="min-w-0"><h3 class="break-words text-lg font-semibold group-hover:text-indigo-700">{{ $group->name }}</h3><p class="mt-1 text-sm text-gray-500">{{ $group->distance ?: '距離未定' }} · {{ $group->arrow_count }} 箭</p></div>
                            <span class="shrink-0 rounded-full bg-indigo-50 px-3 py-1 text-sm font-semibold text-indigo-700">{{ $active }} 人</span>
                        </div>
                        @if($group->quota)<p class="mt-4 border-t pt-3 text-sm text-gray-500">名額 {{ $active }} / {{ $group->quota }}</p>@endif
                    </a>
                @empty
                    <div class="rounded-2xl border border-dashed bg-white p-8 text-center text-sm text-gray-500 md:col-span-2">此賽事尚未建立組別。</div>
                @endforelse
            </div>
        </section>
    @else
        <form method="GET" class="rounded-2xl border border-indigo-100 bg-white p-4 shadow-sm">
            <input type="hidden" name="event_group_id" value="{{ $selectedGroup->id }}">
            <label for="registration-search" class="text-sm font-semibold">搜尋選手</label>
            <div class="mt-2 flex flex-col gap-2 sm:flex-row">
                <input id="registration-search" name="q" value="{{ request('q') }}" class="min-h-12 min-w-0 flex-1 rounded-xl border-gray-300 text-base sm:text-sm" placeholder="姓名、暱稱、Email、會員編號或隊伍">
                <button class="min-h-12 rounded-xl bg-gray-900 px-5 text-sm font-medium text-white">搜尋</button>
                @if(request()->filled('q'))<a href="{{ route('organizer.events.registrations.index', [$event, 'event_group_id' => $selectedGroup->id]) }}" class="inline-flex min-h-12 items-center justify-center rounded-xl border px-4 text-sm">清除</a>@endif
            </div>
        </form>

        <section class="flex items-center justify-between rounded-2xl border bg-white p-4 shadow-sm sm:p-5">
            <div><p class="text-xs text-gray-500">有效報名</p><p class="mt-1 text-xl font-semibold">{{ $selectedGroup->active_registrations_count }} 人</p></div>
            @if($selectedGroup->quota)<p class="rounded-full bg-gray-100 px-3 py-1 text-sm text-gray-600">名額 {{ $selectedGroup->active_registrations_count }} / {{ $selectedGroup->quota }}</p>@endif
        </section>

        <div class="grid gap-3 md:grid-cols-2">
            @forelse($registrations as $registration)
                <article class="rounded-2xl border bg-white p-4 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="min-w-0"><h3 class="text-lg font-semibold">{{ $registration->name }}</h3><p class="break-all text-xs text-gray-500">{{ $registration->email }}</p></div>
                        <span class="rounded-full px-3 py-1 text-xs font-medium {{ $statusColors[$registration->status] ?? 'bg-gray-100 text-gray-600' }}">{{ $statusLabels[$registration->status] ?? $registration->status }}</span>
                    </div>
                    @if($registration->team_name)<p class="mt-3 text-sm text-gray-600">隊伍：{{ $registration->team_name }}</p>@endif
                    <p class="mt-3 text-xs text-gray-400">報名時間 {{ $registration->created_at?->format('Y/m/d H:i') }}</p>
                </article>
            @empty
                <div class="rounded-2xl border border-dashed bg-white p-8 text-center text-sm text-gray-500 md:col-span-2">此組目前沒有符合條件的選手。</div>
            @endforelse
        </div>
        @if($registrations->hasPages()){{ $registrations->links() }}@endif
    @endif
</div>
@endsection
