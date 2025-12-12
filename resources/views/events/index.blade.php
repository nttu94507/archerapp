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
                <p class="text-sm font-semibold text-indigo-600">賽事情報站</p>
                <h1 class="text-2xl font-bold text-gray-900">進行中、開放報名、預告與歷史賽事</h1>
                <p class="mt-1 text-sm text-gray-600">依時間與報名狀態快速瀏覽所有賽事，一鍵進入報名組別。</p>
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

        {{-- Ongoing --}} 
        @if($ongoingEvents->isNotEmpty())
            <section class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">現正開賽</h2>
                        <p class="text-sm text-gray-500">賽事進行中，可快速查看資訊或前往組別。</p>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    @foreach($ongoingEvents as $event)
                        @php $status = $regStatus($event); @endphp
                        <a href="{{ route('events.show', $event) }}#groups" class="group block rounded-2xl border border-indigo-100 bg-white p-5 shadow-sm ring-1 ring-indigo-50 transition hover:-translate-y-0.5 hover:shadow md:focus:outline-none md:focus:ring-2 md:focus:ring-indigo-200">
                            <article class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-xs font-semibold text-green-600">進行中</p>
                                    <div class="mt-1 flex items-center gap-2">
                                        <h3 class="text-lg font-semibold text-gray-900 group-hover:text-indigo-700">{{ $event->name }}</h3>
                                        @if($status)
                                            <span class="inline-flex rounded-full px-2 py-1 text-[11px] font-semibold {{ $status['class'] }}">{{ $status['label'] }}</span>
                                        @endif
                                    </div>
                                    <p class="text-sm text-gray-600">{{ $dateRange($event) }} ・ {{ $event->venue ?? '點未填寫'}}</p>
                                    <div class="mt-2 flex flex-wrap gap-2 text-xs text-gray-500">
                                        <span class="inline-flex rounded-full bg-gray-100 px-2 py-1 font-medium">{{ $event->mode === 'indoor' ? '室內' : '室外' }}</span>
                                        @if($event->level)
                                            <span class="inline-flex rounded-full bg-gray-100 px-2 py-1 font-medium">{{ $event->level }}</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="flex flex-col items-end gap-2 text-right">
                                    <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">立即前往組別</span>
                                    <span class="text-xs text-gray-500">點擊整個卡片前往</span>
                                </div>
                            </article>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Open for registration --}} 
        @if($openEvents->isNotEmpty())
            <section class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">開放報名</h2>
                        <p class="text-sm text-gray-500">現在開放報名的場次，點擊可直接前往報名組別。</p>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    @foreach($openEvents as $event)
                        @php $status = $regStatus($event); @endphp
                        <a href="{{ route('events.show', $event) }}#groups" class="group block rounded-2xl border border-indigo-100 bg-white p-5 shadow-sm ring-1 ring-indigo-50 transition hover:-translate-y-0.5 hover:shadow md:focus:outline-none md:focus:ring-2 md:focus:ring-indigo-200">
                            <article class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-xs font-semibold text-indigo-600">報名截止：{{ $event->reg_end ? \Carbon\Carbon::parse($event->reg_end)->format('Y-m-d H:i') : '—' }}</p>
                                    <h3 class="mt-1 text-lg font-semibold text-gray-900 group-hover:text-indigo-700">{{ $event->name }}</h3>
                                    <p class="text-sm text-gray-600">{{ $dateRange($event) }} ・ {{ $event->venue ?? '點未填寫'}} </p>
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
                                <div class="flex items-center gap-2 text-sm text-gray-500 group-hover:text-indigo-600">
                                    <span>點擊整個卡片前往報名組別</span>
                                    <span aria-hidden="true">→</span>
                                </div>
                            </article>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Upcoming --}} 
        @if($upcomingEvents->isNotEmpty())
            <section class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">賽事預告</h2>
                        <p class="text-sm text-gray-500">最近的比賽行程，方便提前安排與準備。</p>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    @foreach($upcomingEvents as $event)
                        @php $status = $regStatus($event); @endphp
                        <a href="{{ route('events.show', $event) }}#groups" class="group block rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow md:focus:outline-none md:focus:ring-2 md:focus:ring-indigo-200">
                            <article class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-xs text-gray-500">開始日期：{{ $event->start_date ? \Carbon\Carbon::parse($event->start_date)->format('Y-m-d') : '—' }}</p>
                                    <h3 class="mt-1 text-lg font-semibold text-gray-900 group-hover:text-indigo-700">{{ $event->name }}</h3>
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
                                <div class="flex flex-col items-end gap-2 text-right">
                                    <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700">查看組別與資訊</span>
                                    <span class="text-xs text-gray-500">點擊卡片</span>
                                </div>
                            </article>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

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
                        <a href="{{ route('events.show', $event) }}" class="group block rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow md:focus:outline-none md:focus:ring-2 md:focus:ring-indigo-200">
                            <article class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-xs text-gray-500">結束日期：{{ $event->end_date ? \Carbon\Carbon::parse($event->end_date)->format('Y-m-d') : ($event->start_date ? \Carbon\Carbon::parse($event->start_date)->format('Y-m-d') : '—') }}</p>
                                    <h3 class="mt-1 text-lg font-semibold text-gray-900 group-hover:text-indigo-700">
                                        {{ $event->name }}
                                    </h3>
                                    <p class="text-sm text-gray-600">{{ $dateRange($event) }} ・ {{ $event->venue ?? '地點未填寫' }}</p>
                                    <div class="mt-2 flex flex-wrap gap-2 text-xs text-gray-500">
                                        <span class="inline-flex rounded-full bg-gray-100 px-2 py-1 font-medium">{{ $event->mode === 'indoor' ? '室內' : '室外' }}</span>
                                        @if($event->level)
                                            <span class="inline-flex rounded-full bg-gray-100 px-2 py-1 font-medium">{{ $event->level }}</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="flex flex-col items-end gap-2 text-right">
                                    <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700">查看詳情</span>
                                    <span class="text-xs text-gray-500">點擊卡片</span>
                                </div>
                            </article>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
@endsection
