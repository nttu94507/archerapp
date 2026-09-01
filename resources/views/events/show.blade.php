@extends('layouts.app')

@php
    $liveLabel = ($isEventFinished ?? false) ? '排名賽結果' : '排名賽戰況';
@endphp

@section('title', $event->name)

@section('content')
    <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 py-8">
        <div class="mb-6">
            <a href="{{ route('events.index') }}"
               onclick="if (document.referrer && new URL(document.referrer).origin === window.location.origin) { event.preventDefault(); history.back(); }"
               class="mb-3 inline-flex min-h-11 items-center gap-2 text-sm font-medium text-indigo-600 hover:text-indigo-500">
                <span aria-hidden="true">←</span><span>返回上一頁</span>
            </a>
            <h1 class="text-2xl font-bold text-gray-900">{{ $event->name }}</h1>
            <p class="text-sm text-gray-500">
                {{ $event->start_date }} ~ {{ $event->end_date }} · {{ $event->organizer }}
            </p>
            @if($event->venue)<p class="mt-1 text-sm text-gray-600">📍 {{ $event->venue }}</p>@endif

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

            <div class="mt-3 flex flex-wrap gap-2">
                @if($hasPublicQualificationLive)<a href="{{ route('events.live', $event) }}"
                   class="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-indigo-600 to-purple-600 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-indigo-200 hover:from-indigo-500 hover:to-purple-500 focus:outline-none focus:ring-2 focus:ring-indigo-300">
                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-white/20 text-[11px]">{{ ($isEventFinished ?? false) ? 'Result' : 'LIVE' }}</span>
                    <span>{{ $liveLabel }}</span>
                </a>@endif
                @if(($event->public_elimination_brackets_count ?? 0) > 0)<a href="{{ route('events.elimination', $event) }}" class="inline-flex items-center rounded-2xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white">個人對抗賽</a>@endif
                {{-- 管理按鈕 --}}
                @if($canManage)
                    <a href="{{ route('organizer.events.show', $event) }}"
                       class="inline-flex items-center rounded-xl bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-emerald-500">
                        管理
                    </a>
                @endif
            </div>
        </div>

        @if(session('success'))
            <div class="mb-4 rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-700">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ session('error') }}</div>
        @endif

        {{-- 組別清單（公開） --}}
        <section id="groups" class="rounded-2xl border bg-white p-4 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-900 mb-3">賽事組別</h2>

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
                                        'no_show' => '未報到（DNS）',
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
                                            'bg-amber-100 text-amber-700' => $registration->status === 'no_show',
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
                                @if($registration->status === 'registered' && !$registrationLocked)<form method="POST" action="{{ route('event-registrations.withdraw',$registration) }}" onsubmit="return confirm('確定取消報名？')">@csrf @method('PATCH')<button class="text-xs text-red-600">取消報名</button></form>@endif
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
                            $cap = $g->quota ?? null;
                            $registered = $g->registered_count ?? 0; // 來自 withCount
                            $full = $cap !== null && $registered >= $cap;

                            $already = auth()->check() && in_array($g->id, $myGroupIds ?? [], true);
                            $requiresGender = $g->gender !== 'open' || $g->hasTeamFormat('mixed');
                            $genderMissing = auth()->check() && $requiresGender && empty($memberGender);
                            $genderMismatch = auth()->check() && $g->gender !== 'open' && !empty($memberGender) && $g->gender !== $memberGender;
                            $groupRegStart = $g->reg_start ?: $regStartAt;
                            $groupRegEnd = $g->reg_end ?: $regEndAt;
                            $groupIsBetween = !$registrationLocked && $groupRegStart && $groupRegEnd && now()->between($groupRegStart, $groupRegEnd);
                            $registrationUnavailableLabel = match (true) {
                                $registrationLocked => '排靶完成，報名截止',
                                !$groupRegStart || !$groupRegEnd => '尚未設定報名時間',
                                now()->lt($groupRegStart) => '報名尚未開始',
                                now()->gt($groupRegEnd) => '報名已截止',
                                default => '目前不可報名',
                            };
                        @endphp

                        <li class="py-3 flex items-center justify-between">
                            <div class="min-w-0">
                                <div class="font-medium text-gray-900 truncate">{{ $g->name }}</div>
                                <div class="text-sm text-gray-700">{{ ['recurve'=>'反曲弓','compound'=>'複合弓','barebow'=>'光弓'][$g->bow_type] ?? '弓種不限' }} · {{ ['male'=>'男子組','female'=>'女子組','open'=>'性別不限'][$g->gender] ?? '性別不限' }} · {{ $g->distance ?: '距離未定' }} · {{ $g->arrow_count }} 箭</div>
                                <div class="mt-1 text-xs text-gray-500">
                                    {{ (int) $g->fee > 0 ? 'NT$ '.number_format($g->fee) : '免費' }} ·
                                    @if($cap)剩餘 {{ max(0, $cap - $registered) }} / {{ $cap }} 名@else已報名 {{ $registered }} 人@endif
                                </div>
                                @if($g->is_team)<a href="{{ route('events.teams.index',[$event,$g]) }}" class="mt-2 inline-flex min-h-10 items-center rounded-xl bg-violet-50 px-3 text-xs font-semibold text-violet-700">團體組隊・{{ $g->active_teams_count }} 隊 →</a>@endif
                            </div>

                            {{-- 右側按鈕區 --}}
                            <div class="flex items-center gap-2">
                                @if($already)
                                    <span class="inline-flex items-center rounded-xl bg-gray-100 px-3 py-1.5 text-xs font-medium text-gray-600">
                                已報名
                            </span>
                                @elseif($genderMismatch)
                                    <span class="inline-flex min-h-10 items-center rounded-xl bg-red-50 px-3 text-xs font-semibold text-red-700">性別不符合</span>
                                @elseif($genderMissing)
                                    <a href="{{ route('member-profile.edit') }}" class="inline-flex min-h-10 items-center rounded-xl bg-amber-50 px-3 text-xs font-semibold text-amber-700">請先設定性別</a>
                                @elseif(!$groupIsBetween)
                                    <span class="text-xs text-gray-400">{{ $registrationUnavailableLabel }}</span>
                                @elseif($full)
                                    <span class="inline-flex items-center rounded-xl bg-gray-100 px-3 py-1.5 text-xs font-medium text-gray-500">
                                名額已滿
                            </span>
                                @else
                                    @auth
                                        <a href="{{ route('events.registration.confirm', [$event, $g]) }}"
                                           class="inline-flex min-h-11 items-center rounded-xl bg-indigo-600 px-4 text-sm font-medium text-white hover:bg-indigo-500">立即報名</a>
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
