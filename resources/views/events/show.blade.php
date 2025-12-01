@extends('layouts.app')

@section('title', $event->name)

@section('content')
    <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 py-8">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">{{ $event->name }}</h1>
            <p class="text-sm text-gray-500">
                {{ $event->start_date }} ~ {{ $event->end_date }} · {{ $event->organizer }}
            </p>

            {{-- 報名狀態 --}}
            @if($regStatus)
                @php
                    $badgeClass = match($regStatus) {
                        '報名中'   => 'bg-indigo-50 text-indigo-700',
                        '尚未開始' => 'bg-gray-100 text-gray-700',
                        '已截止'   => 'bg-gray-100 text-gray-500',
                        default    => 'bg-gray-100 text-gray-500'
                    };
                @endphp
                <span class="mt-2 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $badgeClass }}">
                {{ $regStatus }}
            </span>
            @endif

            {{-- 管理按鈕 --}}
            @if($canManage)
                <a href="{{ route('events.groups.index', $event) }}"
                   class="ml-2 inline-flex items-center rounded-xl bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-emerald-500">
                    管理
                </a>
            @endif
        </div>

        {{-- 組別清單（公開） --}}
        <section id="groups" class="rounded-2xl border bg-white p-4 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-900 mb-3">可報名組別</h2>

            @auth
                @if($myRegistrations->isNotEmpty())
                    <div class="mb-4 rounded-xl bg-indigo-50 border border-indigo-100 p-4">
                        <div class="flex items-center justify-between gap-2 mb-2">
                            <p class="text-sm font-semibold text-indigo-900">我的報名狀態</p>
                            <span class="text-xs text-indigo-700">繳費狀態由管理員更新</span>
                        </div>
                        <div class="space-y-2">
                            @foreach($myRegistrations as $registration)
                                @php
                                    $statusLabel = match($registration->status) {
                                        'pending' => '待處理',
                                        'registered' => '已報名',
                                        'checked_in' => '已報到',
                                        'withdrawn' => '已退出',
                                        default => $registration->status,
                                    };
                                @endphp
                                <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-white/60 px-3 py-2">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900">{{ optional($registration->event_group)->name ?? '未指定組別' }}</p>
                                        <p class="text-xs text-gray-500">{{ optional($registration->created_at)->format('Y-m-d H:i') }}</p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium
                                            @class([
                                                'bg-yellow-100 text-yellow-700' => $registration->status === 'pending',
                                                'bg-blue-100 text-blue-700' => $registration->status === 'registered',
                                            'bg-emerald-100 text-emerald-700' => $registration->status === 'checked_in',
                                            'bg-gray-200 text-gray-700' => $registration->status === 'withdrawn',
                                            ])">
                                            {{ __('報名狀態：') }}{{ $statusLabel }}
                                        </span>
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium
                                            {{ $registration->paid ? 'bg-emerald-50 text-emerald-700' : 'bg-orange-50 text-orange-700' }}">
                                            {{ $registration->paid ? '已繳費' : '待繳費' }}
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endauth

            @if($groups->isEmpty())
                <p class="text-sm text-gray-500">此賽事尚無組別。</p>
            @else
                <ul class="divide-y divide-gray-100">
                    @foreach($groups as $g)
                        @php
                            $cap = $g->capacity ?? null; // 名額（若無就顯示「—」）
                            $registered = $g->registered_count ?? 0; // 來自 withCount
                            $full = $cap !== null && $registered >= $cap;

                            $already = auth()->check() && in_array($g->id, $myGroupIds ?? [], true);
                        @endphp

                        <li class="py-3 flex items-center justify-between">
                            <div class="min-w-0">
                                <div class="font-medium text-gray-900 truncate">{{ $g->name }}</div>
                                <div class="text-sm text-gray-700">{{$g->bow_type}} / {{$g->gender}} / {{$g->distance}} / {{$g->age_class}} </div>
                                <div class="mt-1 text-xs text-gray-500">
                                    已報名：{{ $registered }}
                                </div>
                            </div>

                            {{-- 右側按鈕區 --}}
                            <div class="flex items-center gap-2">
                                @if($already)
                                    <span class="inline-flex items-center rounded-xl bg-gray-100 px-3 py-1.5 text-xs font-medium text-gray-600">
                                已報名
                            </span>
                                @elseif(!$isBetween)
                                    <span class="text-xs text-gray-400">目前不可報名</span>
                                @elseif($full)
                                    <span class="inline-flex items-center rounded-xl bg-gray-100 px-3 py-1.5 text-xs font-medium text-gray-500">
                                名額已滿
                            </span>
                                @else
                                    @auth
                                        <form method="POST" action="{{ route('events.quick_register', [$event, $g]) }}">
                                            @csrf
                                            <button type="submit"
                                                    class="inline-flex items-center rounded-xl bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-500">
                                                立即報名
                                            </button>
                                        </form>
                                    @else
                                        <a href="{{ route('login.options') }}"
                                           class="inline-flex items-center rounded-xl border px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">
                                            登入後報名
                                        </a>
                                    @endauth
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
@endsection
