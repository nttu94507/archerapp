@extends('layouts.app')

@php
    $liveLabel = ($isEventFinished ?? false) ? '排名賽結果' : '排名賽戰況';
    $eventStart = $event->start_date?->copy()->startOfDay();
    $eventEnd = ($event->end_date ?? $event->start_date)?->copy()->endOfDay();
    $eventStatus = match (true) {
        (bool) $event->cancelled_at => ['已取消', 'bg-red-100 text-red-700'],
        (bool) ($isEventFinished ?? false) => ['已結束', 'bg-emerald-100 text-emerald-700'],
        $eventStart && $eventEnd && now()->between($eventStart, $eventEnd) => ['比賽中', 'bg-emerald-100 text-emerald-700'],
        $regStatus === '報名中' => ['報名中', 'bg-indigo-100 text-indigo-700'],
        $regStatus === '已截止' => ['報名截止', 'bg-gray-200 text-gray-700'],
        default => ['即將開放', 'bg-amber-100 text-amber-800'],
    };
    $dateRange = !$event->start_date
        ? null
        : (($event->end_date && $event->start_date->equalTo($event->end_date))
            ? $event->start_date->format('Y-m-d')
            : ($event->end_date ? $event->start_date->format('Y-m-d').'～'.$event->end_date->format('Y-m-d') : $event->start_date->format('Y-m-d')));
@endphp

@section('title', $event->name)

@section('content')
<div class="mx-auto max-w-5xl px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
    <a href="{{ route('events.index') }}" onclick="if (document.referrer && new URL(document.referrer).origin === window.location.origin) { event.preventDefault(); history.back(); }" class="mb-3 inline-flex min-h-11 items-center gap-2 text-sm font-medium text-indigo-600 hover:text-indigo-500">
        <span aria-hidden="true">←</span><span>返回上一頁</span>
    </a>

    <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $eventStatus[1] }}">{{ $eventStatus[0] }}</span>
                <h1 class="mt-3 break-words text-2xl font-bold text-gray-900">{{ $event->name }}</h1>
            </div>
            @if($canManage)<a href="{{ route('organizer.events.show', $event) }}" class="inline-flex min-h-11 items-center rounded-xl bg-emerald-600 px-4 text-sm font-semibold text-white hover:bg-emerald-500">管理賽事</a>@endif
        </div>
        <div class="mt-4 grid gap-2 text-sm text-gray-600 sm:grid-cols-2">
            <p>📅 {{ $dateRange ?: '日期待公布' }}</p>
            <p>📍 {{ $event->venue ?: '場地待公布' }}</p>
            <p class="sm:col-span-2">主辦單位：{{ $event->organizer }}</p>
        </div>
        @if($hasPublicQualificationLive || ($event->public_elimination_brackets_count ?? 0) > 0)
            <div class="mt-5 flex flex-wrap gap-2 border-t border-gray-100 pt-4">
                @if($hasPublicQualificationLive)<a href="{{ route('events.live', $event) }}" class="inline-flex min-h-11 items-center rounded-xl bg-indigo-600 px-4 text-sm font-semibold text-white hover:bg-indigo-500">{{ $liveLabel }}</a>@endif
                @if(($event->public_elimination_brackets_count ?? 0) > 0)<a href="{{ route('events.elimination', $event) }}" class="inline-flex min-h-11 items-center rounded-xl bg-gray-900 px-4 text-sm font-semibold text-white hover:bg-gray-800">個人對抗賽</a>@endif
            </div>
        @endif
    </section>

    @if(session('success'))<div class="mt-4 rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-700">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mt-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ session('error') }}</div>@endif

    @auth
        @if($myRegistrations->isNotEmpty())
            <section class="mt-5 rounded-2xl border border-indigo-200 bg-indigo-50 p-4 sm:p-5">
                <h2 class="font-semibold text-indigo-950">我的報名</h2>
                <div class="mt-3 grid gap-2 sm:grid-cols-2">
                    @foreach($myRegistrations as $registration)
                        @php
                            $statusLabel = match($registration->status) {
                                'pending'=>'待處理', 'registered'=>'已報名', 'checked_in'=>'已報到',
                                'no_show'=>'DNS', 'withdrawn'=>'已退出', default=>$registration->status,
                            };
                        @endphp
                        <div class="rounded-xl border border-indigo-100 bg-white p-3">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <p class="font-semibold text-gray-900">{{ optional($registration->event_group)->name ?? '未指定組別' }}</p>
                                <div class="flex flex-wrap gap-1">
                                    <span class="rounded-full bg-blue-100 px-2 py-0.5 text-[11px] font-medium text-blue-700">{{ $statusLabel }}</span>
                                    <span class="rounded-full px-2 py-0.5 text-[11px] font-medium {{ $registration->paid ? 'bg-emerald-100 text-emerald-700' : 'bg-orange-100 text-orange-700' }}">{{ $registration->paid ? '已繳費' : '待繳費' }}</span>
                                </div>
                            </div>
                            @if($registration->status === 'registered' && !$registrationLocked)
                                <form method="POST" action="{{ route('event-registrations.withdraw',$registration) }}" class="mt-3" onsubmit="return confirm('確定取消報名？')">@csrf @method('PATCH')<button class="min-h-10 text-xs font-semibold text-red-600">取消報名</button></form>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    @endauth

    <section id="groups" class="mt-5">
        <div class="mb-3 flex items-center justify-between gap-3"><h2 class="text-lg font-semibold text-gray-900">賽事組別</h2><span class="text-xs text-gray-400">{{ $groups->count() }} 組</span></div>
        @if($groups->isEmpty())
            <div class="rounded-2xl border border-dashed border-gray-300 bg-white p-8 text-center text-sm text-gray-500">此賽事尚無組別。</div>
        @else
            <div class="grid gap-3">
                @foreach($groups as $g)
                    @php
                        $cap = $event->isFreePlan() ? 16 : ($g->quota ?? null);
                        $registered = $g->registered_count ?? 0;
                        $full = $cap !== null && $registered >= $cap;
                        $already = auth()->check() && in_array($g->id, $myGroupIds ?? [], true);
                        $requiresGender = $g->gender !== 'open' || $g->hasTeamFormat('mixed');
                        $genderMissing = auth()->check() && $requiresGender && empty($memberGender);
                        $genderMismatch = auth()->check() && $g->gender !== 'open' && !empty($memberGender) && $g->gender !== $memberGender;
                        $groupRegStart = $g->reg_start ?: $regStartAt;
                        $groupRegEnd = $g->reg_end ?: $regEndAt;
                        $groupIsBetween = !$registrationLocked && $groupRegStart && $groupRegEnd && now()->between($groupRegStart, $groupRegEnd);
                        $registrationUnavailableLabel = match (true) {
                            $registrationLocked => '報名截止', !$groupRegStart || !$groupRegEnd => '尚未開放',
                            now()->lt($groupRegStart) => '尚未開放', now()->gt($groupRegEnd) => '報名截止', default => '目前不可報名',
                        };
                        $singleRoundArrows = $event->mode === 'indoor' ? 30 : 36;
                        $roundLabel = $g->arrow_count > $singleRoundArrows ? '雙局' : '單局';
                    @endphp
                    <article class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm transition hover:border-indigo-300 sm:p-5">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <h3 class="break-words font-semibold text-gray-900">{{ $g->name }}</h3>
                                <div class="mt-2 flex flex-wrap gap-1.5 text-xs font-medium">
                                    <span class="rounded-full bg-gray-100 px-2.5 py-1 text-gray-700">{{ $roundLabel }}・{{ $g->arrow_count }} 箭</span>
                                    @if($g->standard_team_enabled)<span class="rounded-full bg-violet-100 px-2.5 py-1 text-violet-700">團體</span>@endif
                                    @if($g->mixed_team_enabled)<span class="rounded-full bg-amber-100 px-2.5 py-1 text-amber-700">混雙</span>@endif
                                </div>
                                <p class="mt-2 text-sm text-gray-600">{{ (int) $g->fee > 0 ? 'NT$ '.number_format($g->fee) : '免費' }}・{{ $cap ? $registered.' / '.$cap.' 人' : '已報名 '.$registered.' 人' }}</p>
                                @if($g->is_team)<a href="{{ route('events.teams.index',[$event,$g]) }}" class="mt-2 inline-flex min-h-10 items-center text-xs font-semibold text-violet-700">團體組隊・{{ $g->active_teams_count }} 隊 →</a>@endif
                            </div>
                            <div class="shrink-0">
                                @if($already)
                                    <span class="inline-flex min-h-10 items-center rounded-xl bg-gray-100 px-3 text-xs font-semibold text-gray-600">已報名</span>
                                @elseif($genderMismatch)
                                    <span class="inline-flex min-h-10 items-center rounded-xl border border-red-200 bg-red-50 px-3 text-xs font-semibold text-red-700">性別不符合</span>
                                @elseif($genderMissing)
                                    <a href="{{ route('member-profile.edit') }}" class="inline-flex min-h-10 items-center rounded-xl border border-amber-200 bg-amber-50 px-3 text-xs font-semibold text-amber-700">請先設定性別</a>
                                @elseif(!$groupIsBetween)
                                    <span class="inline-flex min-h-10 items-center rounded-xl bg-gray-100 px-3 text-xs font-semibold text-gray-500">{{ $registrationUnavailableLabel }}</span>
                                @elseif($full)
                                    <span class="inline-flex min-h-10 items-center rounded-xl bg-gray-100 px-3 text-xs font-semibold text-gray-500">名額已滿</span>
                                @else
                                    @auth<a href="{{ route('events.registration.confirm', [$event, $g]) }}" class="inline-flex min-h-11 items-center rounded-xl bg-indigo-600 px-4 text-sm font-semibold text-white hover:bg-indigo-500">立即報名</a>
                                    @else<a href="{{ route('login.options') }}" class="inline-flex min-h-11 items-center rounded-xl border border-gray-300 px-4 text-sm font-semibold text-gray-700 hover:bg-gray-50">登入後報名</a>@endauth
                                @endif
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>
</div>
@endsection
