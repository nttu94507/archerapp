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
