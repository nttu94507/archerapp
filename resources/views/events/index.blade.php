{{-- resources/views/events/index.blade.php --}}
@extends('layouts.app')

@section('title', '賽事列表')

@section('content')
    @php
        $regStatus = function ($event) {
            if (!$event->reg_start || !$event->reg_end) {
                return null;
            }

            $now = now();
            $start = \Carbon\Carbon::parse($event->reg_start);
            $end = \Carbon\Carbon::parse($event->reg_end);

            if ($now->lt($start)) {
                return ['label' => '尚未開始', 'class' => 'bg-gray-100 text-gray-700'];
            }

            if ($now->between($start, $end)) {
                return ['label' => '報名中', 'class' => 'bg-indigo-50 text-indigo-700'];
            }

            return ['label' => '已截止', 'class' => 'bg-gray-100 text-gray-500'];
        };

        $dateRange = function ($event) {
            $start = $event->start_date ? \Carbon\Carbon::parse($event->start_date) : null;
            $end = $event->end_date ? \Carbon\Carbon::parse($event->end_date) : null;

            if ($start && $end) {
                return $start->equalTo($end)
                    ? $start->format('Y-m-d')
                    : $start->format('Y-m-d') . ' ~ ' . $end->format('Y-m-d');
            }

            return $start ? $start->format('Y-m-d') : '—';
        };
    @endphp

    <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 py-8 space-y-8">
        {{-- Header --}}
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-sm font-semibold text-indigo-600">賽事總覽</p>
                <h1 class="text-2xl font-bold text-gray-900">報名、即將舉辦與歷史賽事</h1>
                <p class="mt-1 text-sm text-gray-600">查看目前可報名的比賽，並回顧或預覽所有賽事資訊。</p>
            </div>
            @auth
                @if(auth()->user()->isAdmin())
                    <a href="{{ route('events.create') }}"
                       class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow hover:bg-indigo-500">
                        新增賽事
                    </a>
                @endif
            @endauth
        </div>

        {{-- Open for registration --}}
        <section class="space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">可報名的比賽</h2>
                    <p class="text-sm text-gray-500">現在開放報名的場次，點擊可直接前往報名組別。</p>
                </div>
            </div>

            @if($openEvents->isEmpty())
                <div class="rounded-2xl border border-dashed border-gray-200 bg-white p-6 text-center text-sm text-gray-500">
                    目前沒有開放報名的賽事。
                </div>
            @else
                <div class="grid gap-4 md:grid-cols-2">
                    @foreach($openEvents as $event)
                        @php $status = $regStatus($event); @endphp
                        <article class="rounded-2xl border border-indigo-100 bg-white p-5 shadow-sm ring-1 ring-indigo-50">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-xs font-semibold text-indigo-600">報名截止：{{ $event->reg_end ? \Carbon\Carbon::parse($event->reg_end)->format('Y-m-d H:i') : '—' }}</p>
                                    <a href="{{ route('events.show', $event) }}"
                                       class="mt-1 block text-lg font-semibold text-gray-900 hover:text-indigo-700">
                                        {{ $event->name }}
                                    </a>
                                    <p class="text-sm text-gray-600">{{ $dateRange($event) }} ・ {{ $event->venue ?? '地點未填寫' }}</p>
                                    <div class="mt-2 flex flex-wrap gap-2 text-xs text-gray-500">
                                        <span class="inline-flex rounded-full bg-gray-100 px-2 py-1 font-medium">{{ $event->mode === 'indoor' ? '室內' : '室外' }}</span>
                                        @if($event->level)
                                            <span class="inline-flex rounded-full bg-gray-100 px-2 py-1 font-medium">{{ $event->level }}</span>
                                        @endif
                                        @if($status)
                                            <span class="inline-flex rounded-full px-2 py-1 font-medium {{ $status['class'] }}">{{ $status['label'] }}</span>
                                        @endif
                                    </div>
                                </div>
                                <a href="{{ route('events.show', $event) }}#groups"
                                   class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                                    立即報名
                                </a>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- Upcoming --}}
        <section class="space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">即將舉辦</h2>
                    <p class="text-sm text-gray-500">最近的比賽行程，方便提前安排與準備。</p>
                </div>
            </div>

            @if($upcomingEvents->isEmpty())
                <div class="rounded-2xl border border-dashed border-gray-200 bg-white p-6 text-center text-sm text-gray-500">
                    尚未有即將舉辦的賽事。
                </div>
            @else
                <div class="grid gap-4 md:grid-cols-2">
                    @foreach($upcomingEvents as $event)
                        @php $status = $regStatus($event); @endphp
                        <article class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-xs text-gray-500">開始日期：{{ $event->start_date ? \Carbon\Carbon::parse($event->start_date)->format('Y-m-d') : '—' }}</p>
                                    <a href="{{ route('events.show', $event) }}"
                                       class="mt-1 block text-lg font-semibold text-gray-900 hover:text-indigo-700">
                                        {{ $event->name }}
                                    </a>
                                    <p class="text-sm text-gray-600">{{ $dateRange($event) }} ・ {{ $event->venue ?? '地點未填寫' }}</p>
                                    <div class="mt-2 flex flex-wrap gap-2 text-xs text-gray-500">
                                        <span class="inline-flex rounded-full bg-gray-100 px-2 py-1 font-medium">{{ $event->mode === 'indoor' ? '室內' : '室外' }}</span>
                                        @if($event->level)
                                            <span class="inline-flex rounded-full bg-gray-100 px-2 py-1 font-medium">{{ $event->level }}</span>
                                        @endif
                                        @if($status)
                                            <span class="inline-flex rounded-full px-2 py-1 font-medium {{ $status['class'] }}">{{ $status['label'] }}</span>
                                        @endif
                                    </div>
                                </div>
                                <a href="{{ route('events.show', $event) }}#groups"
                                   class="inline-flex items-center justify-center rounded-xl border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 hover:border-indigo-300 hover:text-indigo-700">
                                    查看組別
                                </a>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- Past --}}
        <section class="space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">歷史賽事</h2>
                    <p class="text-sm text-gray-500">回顧已完成的賽事與紀錄。</p>
                </div>
            </div>

            @if($pastEvents->isEmpty())
                <div class="rounded-2xl border border-dashed border-gray-200 bg-white p-6 text-center text-sm text-gray-500">
                    還沒有歷史賽事紀錄。
                </div>
            @else
                <div class="grid gap-4 md:grid-cols-2">
                    @foreach($pastEvents as $event)
                        <article class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-xs text-gray-500">結束日期：{{ $event->end_date ? \Carbon\Carbon::parse($event->end_date)->format('Y-m-d') : ($event->start_date ? \Carbon\Carbon::parse($event->start_date)->format('Y-m-d') : '—') }}</p>
                                    <a href="{{ route('events.show', $event) }}"
                                       class="mt-1 block text-lg font-semibold text-gray-900 hover:text-indigo-700">
                                        {{ $event->name }}
                                    </a>
                                    <p class="text-sm text-gray-600">{{ $dateRange($event) }} ・ {{ $event->venue ?? '地點未填寫' }}</p>
                                    <div class="mt-2 flex flex-wrap gap-2 text-xs text-gray-500">
                                        <span class="inline-flex rounded-full bg-gray-100 px-2 py-1 font-medium">{{ $event->mode === 'indoor' ? '室內' : '室外' }}</span>
                                        @if($event->level)
                                            <span class="inline-flex rounded-full bg-gray-100 px-2 py-1 font-medium">{{ $event->level }}</span>
                                        @endif
                                    </div>
                                </div>
                                <a href="{{ route('events.show', $event) }}"
                                   class="inline-flex items-center justify-center rounded-xl border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 hover:border-indigo-300 hover:text-indigo-700">
                                    查看詳情
                                </a>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
@endsection
