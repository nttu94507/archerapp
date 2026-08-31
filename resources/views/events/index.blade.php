@extends('layouts.app')
@section('title', '賽事情報')
@section('content')
@php
    $dateRange = function ($event) {
        $start = $event->start_date; $end = $event->end_date;
        if ($start && $end) return $start->equalTo($end) ? $start->format('Y-m-d') : $start->format('Y-m-d').'～'.$end->format('Y-m-d');
        return $start?->format('Y-m-d') ?? '日期待公布';
    };
    $statusMeta = [
        'ongoing'=>['label'=>'現正進行', 'class'=>'bg-emerald-100 text-emerald-700'],
        'registration_open'=>['label'=>'報名中', 'class'=>'bg-indigo-100 text-indigo-700'],
        'upcoming'=>['label'=>'即將開始', 'class'=>'bg-amber-100 text-amber-800'],
    ];
@endphp
<main class="mx-auto max-w-6xl space-y-8 px-4 py-6 sm:px-6 sm:py-8">
    <header class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div><p class="text-sm font-semibold text-indigo-600">探索、報名與追蹤比賽</p><h1 class="mt-1 text-2xl font-bold text-gray-900">賽事情報</h1></div>
        @auth @if(auth()->user()->isAdmin())<a href="{{ route('events.create') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-indigo-600 px-4 text-sm font-medium text-white">新增賽事</a>@endif @endauth
    </header>

    <form method="GET" action="{{ route('events.index') }}" class="grid gap-3 rounded-2xl border bg-white p-4 shadow-sm sm:grid-cols-[minmax(0,1fr)_10rem_auto]">
        <label><span class="sr-only">搜尋賽事</span><input name="q" value="{{ request('q') }}" class="min-h-12 w-full rounded-xl border-gray-300 text-base" placeholder="搜尋賽事、主辦方或地點"></label>
        <label><span class="sr-only">場地類型</span><select name="mode" class="min-h-12 w-full rounded-xl border-gray-300 text-base"><option value="">全部類型</option><option value="outdoor" @selected(request('mode')==='outdoor')>室外賽</option><option value="indoor" @selected(request('mode')==='indoor')>室內賽</option></select></label>
        <button class="min-h-12 rounded-xl bg-gray-900 px-5 text-sm font-semibold text-white">搜尋</button>
    </form>

    <section class="space-y-4">
        <div class="flex items-end justify-between gap-3"><div><h2 class="text-lg font-semibold text-gray-900">現在值得關注</h2><p class="mt-1 text-sm text-gray-500">依現正進行、開放報名及開賽時間排列。</p></div><span class="text-xs text-gray-400">{{ $featuredEvents->count() }} 場</span></div>
        @if($featuredEvents->isEmpty())
            <div class="rounded-2xl border border-dashed bg-white p-8 text-center text-sm text-gray-500">目前沒有符合條件的近期賽事。</div>
        @else
            <div class="grid gap-4 md:grid-cols-2">
                @foreach($featuredEvents as $event)
                    @php
                        $meta = $statusMeta[$event->listing_status];
                        $hasLive = $event->has_live_qualification || $event->has_live_elimination;
                        $actionUrl = $event->listing_status === 'ongoing' && $hasLive ? route('events.live', $event) : route('events.show', $event).($event->listing_status === 'registration_open' ? '#groups' : '');
                        $actionLabel = $event->listing_status === 'ongoing' && $hasLive ? '查看戰況' : ($event->listing_status === 'registration_open' ? '立即報名' : '查看詳情');
                    @endphp
                    <article class="flex min-h-48 flex-col rounded-2xl border bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-indigo-200 hover:shadow-md">
                        <div class="flex items-start justify-between gap-3"><span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $meta['class'] }}">{{ $meta['label'] }}</span><span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs text-gray-600">{{ $event->mode === 'indoor' ? '室內' : '室外' }}</span></div>
                        <div class="mt-3 min-w-0 flex-1"><h3 class="break-words text-lg font-bold text-gray-900">{{ $event->name }}</h3><p class="mt-1 text-sm text-gray-600">{{ $dateRange($event) }}</p><p class="mt-1 truncate text-sm text-gray-500">{{ $event->venue ?: '場地待公布' }}・{{ $event->organizer }}</p>
                            @if($event->listing_status === 'registration_open')<p class="mt-2 text-xs font-medium text-indigo-700">報名截止：{{ $event->registrationClosesAt()?->format('Y-m-d H:i') ?? '依組別公告' }}</p>
                            @elseif($event->listing_status === 'upcoming')<p class="mt-2 text-xs text-gray-500">{{ ['upcoming'=>'尚未開放報名','closed'=>'報名已截止','unset'=>'報名時間待公布'][$event->registrationStatus()] ?? '查看賽事資訊' }}</p>@endif
                        </div>
                        <a href="{{ $actionUrl }}" class="mt-4 inline-flex min-h-11 items-center justify-center rounded-xl {{ $event->listing_status === 'registration_open' ? 'bg-indigo-600 text-white' : 'border border-gray-200 text-gray-700' }} px-4 text-sm font-semibold">{{ $actionLabel }}</a>
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    <section class="space-y-4">
        <div class="flex items-end justify-between gap-3"><div><h2 class="text-lg font-semibold text-gray-900">歷史賽事</h2><p class="mt-1 text-sm text-gray-500">最近結束的賽事優先顯示。</p></div><span class="text-xs text-gray-400">{{ $pastEvents->count() }} 場</span></div>
        @if($pastEvents->isEmpty())
            <div class="rounded-2xl border border-dashed bg-white p-8 text-center text-sm text-gray-500">還沒有歷史賽事紀錄。</div>
        @else
            <div class="grid gap-3 md:grid-cols-2">
                @foreach($historyPreview as $event)
                    <a href="{{ route('events.show', $event) }}" class="flex min-h-28 items-center justify-between gap-4 rounded-2xl border bg-white p-4 shadow-sm hover:border-indigo-200"><div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><span class="rounded-full {{ $event->has_published_results ? 'bg-violet-100 text-violet-700' : 'bg-gray-100 text-gray-600' }} px-2 py-1 text-xs font-medium">{{ $event->has_published_results ? '正式成績' : '成績整理中' }}</span><span class="text-xs text-gray-400">{{ $event->mode === 'indoor' ? '室內' : '室外' }}</span></div><h3 class="mt-2 truncate font-semibold text-gray-900">{{ $event->name }}</h3><p class="mt-1 text-xs text-gray-500">{{ $dateRange($event) }}・{{ $event->venue ?: '場地待公布' }}</p></div><span class="shrink-0 text-sm font-medium text-indigo-600">{{ $event->has_published_results ? '查看成績' : '查看賽事' }} ›</span></a>
                @endforeach
            </div>
            @if($historyRemaining->isNotEmpty())
                <details class="group rounded-2xl border bg-white p-4"><summary class="flex min-h-11 cursor-pointer list-none items-center justify-between font-medium"><span>查看其餘 {{ $historyRemaining->count() }} 場歷史賽事</span><span class="text-gray-400 transition group-open:rotate-180">⌄</span></summary><div class="mt-3 grid gap-3 border-t pt-4 md:grid-cols-2">@foreach($historyRemaining as $event)<a href="{{ route('events.show', $event) }}" class="rounded-xl bg-gray-50 p-4"><p class="font-medium text-gray-800">{{ $event->name }}</p><p class="mt-1 text-xs text-gray-500">{{ $dateRange($event) }}</p></a>@endforeach</div></details>
            @endif
        @endif
    </section>
</main>
@endsection
