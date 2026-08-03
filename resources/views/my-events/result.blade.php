@extends('layouts.app')

@section('title', $registration->event->name.' 正式成績')

@section('content')
@php
    $target = $registration->scoringAssignment?->target;
    $targetPosition = $target ? $target->target_number.$registration->scoringAssignment->position : null;
@endphp
<div class="mx-auto max-w-4xl space-y-5 px-4 py-6 sm:px-6 sm:py-8">
    <div>
        <a href="{{ route('my-events.index') }}" class="inline-flex min-h-11 items-center text-sm font-medium text-indigo-600">← 返回我的賽事</a>
        <div class="mt-2 flex flex-wrap items-start justify-between gap-3">
            <div>
                <span class="inline-flex rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">主辦方已確認・正式成績</span>
                <h1 class="mt-3 text-2xl font-bold text-gray-900">{{ $registration->event->name }}</h1>
                <p class="mt-1 text-sm text-gray-500">{{ $registration->event_group?->name }}{{ $targetPosition ? ' / 靶位 '.$targetPosition : '' }}</p>
            </div>
            <p class="text-xs text-gray-400">發布於 {{ $registration->result_published_at->format('Y-m-d H:i') }}</p>
        </div>
    </div>

    <section class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="rounded-2xl border bg-white p-4 shadow-sm"><p class="text-xs text-gray-500">組別名次</p><p class="mt-2 text-3xl font-bold {{ $registration->result_status === 'dnf' ? 'text-amber-700' : 'text-indigo-700' }}">{{ $registration->result_status === 'dnf' ? 'DNF' : '第 '.$rank.' 名' }}</p></div>
        <div class="rounded-2xl border bg-white p-4 shadow-sm"><p class="text-xs text-gray-500">總分</p><p class="mt-2 text-3xl font-bold">{{ $stats['total'] }}</p></div>
        <div class="rounded-2xl border bg-white p-4 shadow-sm"><p class="text-xs text-gray-500">10</p><p class="mt-2 text-3xl font-bold">{{ $stats['ten_count'] }}</p></div>
        <div class="rounded-2xl border bg-white p-4 shadow-sm"><p class="text-xs text-gray-500">X</p><p class="mt-2 text-3xl font-bold">{{ $stats['x_count'] }}</p></div>
    </section>

    <section class="overflow-hidden rounded-2xl border bg-white shadow-sm">
        <div class="border-b px-4 py-4 sm:px-5">
            <h2 class="font-semibold text-gray-900">各趟成績</h2>
        </div>
        <div class="divide-y">
            @forelse($registration->scoreEntries as $entry)
                <div class="grid grid-cols-[3.5rem_minmax(0,1fr)_3rem] items-center gap-3 px-4 py-3 sm:px-5">
                    <span class="text-sm font-medium text-gray-500">第 {{ $entry->end_number }} 趟</span>
                    <div class="flex min-w-0 flex-wrap gap-1.5">
                        @foreach($entry->scores ?? [] as $score)
                            <span class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg bg-gray-100 px-2 font-mono text-sm font-bold text-gray-800">{{ $score }}</span>
                        @endforeach
                    </div>
                    <span class="text-right text-lg font-bold">{{ $entry->end_total }}</span>
                </div>
            @empty
                <p class="p-8 text-center text-sm text-gray-500">目前沒有成績明細。</p>
            @endforelse
        </div>
    </section>

    <p class="text-center text-xs text-gray-400">此頁為主辦方正式發布的唯讀成績，選手無法修改。</p>
    <a href="{{ route('events.live', ['event'=>$registration->event, 'group'=>$registration->event_group_id, 'sort'=>'desc']) }}"
       class="inline-flex min-h-12 w-full items-center justify-center rounded-xl border border-indigo-200 bg-white px-4 text-sm font-semibold text-indigo-700">查看此組別完整排名</a>
</div>
@endsection
