@extends('layouts.app')

@section('title', '我的賽事')

@section('content')
<div class="mx-auto max-w-5xl px-4 py-6 sm:px-6 sm:py-8">
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div><h1 class="text-2xl font-bold">我的賽事</h1><p class="mt-1 text-sm text-gray-500">查看參賽資訊與正式成績。</p></div>
        <a href="{{ route('events.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-indigo-600 px-5 text-sm font-medium text-white">探索賽事</a>
    </div>

    @if($events->isEmpty())
        <div class="rounded-2xl border border-dashed bg-white p-8 text-center">
            <p class="font-medium text-gray-800">目前沒有賽事報名紀錄</p>
            <p class="mt-1 text-sm text-gray-500">找到適合的組別後，最快兩步即可完成報名。</p>
            <a href="{{ route('events.index') }}" class="mt-5 inline-flex min-h-11 items-center rounded-xl bg-indigo-600 px-5 text-sm font-medium text-white">查看開放報名賽事</a>
        </div>
    @else
        <div class="space-y-4">
            @foreach($events as $row)
                @php
                    $event = $row['event']; $registration = $row['registration'];
                @endphp
                <article class="rounded-2xl border bg-white p-4 shadow-sm sm:p-5">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="break-words text-lg font-semibold">{{ $event->name }}</h2>
                            </div>
                            <p class="mt-1 text-sm text-gray-500">{{ $event->start_date->format('Y-m-d') }}～{{ $event->end_date->format('Y-m-d') }} · {{ $event->venue ?: '場地待公布' }}</p>
                            <p class="mt-2 text-sm font-medium">{{ $registration->event_group?->name ?: '未指定組別' }}</p>
                        </div>
                        <div class="flex shrink-0 flex-wrap gap-2">
                            <a href="{{ route('events.show', $event) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border px-4 text-sm font-medium">賽事資訊</a>
                            @if($registration->result_published_at)
                                <a href="{{ route('my-events.results.show', $registration) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-emerald-600 px-4 text-sm font-medium text-white">查看正式成績</a>
                            @endif
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</div>
@endsection
