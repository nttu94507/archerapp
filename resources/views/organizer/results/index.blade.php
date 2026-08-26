@extends('layouts.app')

@section('title', $event->name.' 成績管理')

@section('content')
<div class="mx-auto max-w-6xl space-y-6 px-4 py-8 sm:px-6">
    <div>
        <a href="{{ route('organizer.events.show', $event) }}" class="text-sm text-indigo-600">← 返回賽事工作台</a>
        <h1 class="mt-2 text-2xl font-bold">成績確認與分組發布</h1>
        <p class="mt-1 text-sm text-gray-500">已報到選手即使中途停止，也會依現有分數結算並參與排名；只有未報到選手會標記為棄賽（DNF）。</p>
    </div>

    @if(session('success'))<div class="rounded-xl bg-green-50 p-4 text-sm text-green-700">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="rounded-xl bg-red-50 p-4 text-sm text-red-700">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="rounded-xl bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>@endif

    <form id="verify-form" method="POST" action="{{ route('organizer.events.results.verify', $event) }}" class="hidden">@csrf</form>

    <div class="space-y-5">
        @foreach($event->groups as $group)
            @php
                $state = $groupStates->get($group->id);
                $items = $state['registrations'];
                $canPublish = $items->isNotEmpty()
                    && $state['has_session']
                    && $state['has_targets']
                    && $state['unconfirmed_targets'] === 0
                    && $state['unverified'] === 0
                    && !$state['published'];
                $canVerifyGroup = $items->isNotEmpty()
                    && $state['has_session']
                    && $state['has_targets']
                    && $state['unverified'] > 0
                    && !$state['published'];
                $checkClass = 'result-check-group-'.$group->id;
            @endphp
            <section class="overflow-hidden rounded-2xl border bg-white shadow-sm">
                <div class="flex flex-col gap-4 border-b bg-gray-50 p-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-lg font-semibold">{{ $group->name }}</h2>
                            @if($state['published'])
                                <span class="rounded-full bg-green-100 px-2.5 py-1 text-xs font-medium text-green-700">已發布</span>
                            @elseif($canPublish)
                                <span class="rounded-full bg-indigo-100 px-2.5 py-1 text-xs font-medium text-indigo-700">可發布</span>
                            @else
                                <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-700">尚未完成</span>
                            @endif
                        </div>
                        <div class="mt-2 flex flex-wrap gap-2 text-xs">
                            <span class="rounded-lg bg-white px-2.5 py-1.5 text-gray-600">選手 {{ $items->count() }} 人</span>
                            <span class="rounded-lg bg-white px-2.5 py-1.5 {{ $state['unfinished_targets'] ? 'text-red-600' : 'text-green-700' }}">未完成靶位 {{ $state['unfinished_targets'] }}</span>
                            @if($state['requires_judge_review'])<span class="rounded-lg bg-white px-2.5 py-1.5 {{ $state['unconfirmed_targets'] ? 'text-red-600' : 'text-green-700' }}">待主裁判簽核 {{ $state['unconfirmed_targets'] }}</span>@endif
                            <span class="rounded-lg bg-white px-2.5 py-1.5 {{ $state['incomplete_scores'] ? 'text-amber-700' : 'text-green-700' }}">待審核的部分成績 {{ $state['incomplete_scores'] }}</span>
                            <span class="rounded-lg bg-white px-2.5 py-1.5 {{ $state['unverified'] ? 'text-red-600' : 'text-green-700' }}">尚未確認 {{ $state['unverified'] }}</span>
                        </div>
                        @if(!$state['has_session'] || !$state['has_targets'])
                            <p class="mt-2 text-xs text-red-600">此組別尚未建立排靶與計分場次。</p>
                        @endif
                    </div>

                    @if(!$state['published'])
                        <div class="flex flex-wrap gap-2 sm:justify-end">
                            <form method="POST" action="{{ route('organizer.events.results.groups.verify', [$event, $group]) }}" onsubmit="return confirm('確定一次確認「{{ $group->name }}」所有完整成績？')">
                                @csrf
                                <button @disabled(!$canVerifyGroup)
                                        class="min-h-11 rounded-xl px-4 text-sm font-medium {{ $canVerifyGroup ? 'bg-indigo-600 text-white hover:bg-indigo-500' : 'cursor-not-allowed bg-gray-200 text-gray-400' }}">
                                    確認本組全部成績
                                </button>
                            </form>
                            <form method="POST" action="{{ route('organizer.events.results.publish', [$event, $group]) }}" onsubmit="return confirm('確定發布「{{ $group->name }}」的正式成績？發布後選手即可從我的賽事查看。')">
                                @csrf
                                <button @disabled(!$canPublish)
                                        class="min-h-11 rounded-xl px-4 text-sm font-medium {{ $canPublish ? 'bg-green-600 text-white hover:bg-green-500' : 'cursor-not-allowed bg-gray-200 text-gray-400' }}">
                                    發布本組正式成績
                                </button>
                            </form>
                        </div>
                    @endif
                </div>

                @if($items->isEmpty())
                    <p class="p-6 text-center text-sm text-gray-500">此組別沒有有效選手。</p>
                @else
                    <div class="border-b bg-white px-4 py-3">
                        <label class="inline-flex min-h-11 cursor-pointer items-center gap-2 text-sm font-medium text-gray-600">
                            <input type="checkbox" class="h-5 w-5 rounded" aria-label="選取本組待確認成績" onclick="document.querySelectorAll('.{{ $checkClass }}').forEach(el=>el.checked=this.checked)">
                            選取本組所有待確認選手
                        </label>
                    </div>
                    <div class="grid gap-3 bg-gray-50 p-3 sm:p-4 lg:grid-cols-2">
                        @foreach($items->sortByDesc('calculated_total') as $registration)
                            @php
                                $complete = $registration->score_submitted_at && $registration->scoreEntries->count() >= $state['required_ends'];
                                $entriesByEnd = $registration->scoreEntries->keyBy('end_number');
                                $assignment = $registration->scoringAssignment;
                                $targetPosition = $assignment?->target ? $assignment->target->target_number.$assignment->position : '未排靶';
                                $twoRounds = $state['required_ends'] === 12;
                            @endphp
                            <article class="rounded-2xl border bg-white p-4 shadow-sm">
                                <div class="flex items-start gap-3">
                                    <div class="pt-1">
                                        @if(!$registration->score_verified_at)
                                            <input form="verify-form" class="{{ $checkClass }} h-5 w-5 rounded" type="checkbox" name="registration_ids[]" value="{{ $registration->id }}" aria-label="選取 {{ $registration->name }}">
                                        @else
                                            <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-green-100 text-xs text-green-700">✓</span>
                                        @endif
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-start justify-between gap-2">
                                            <div><p class="text-xs font-semibold text-indigo-600">{{ $targetPosition }}</p><h3 class="truncate text-lg font-bold text-gray-900">{{ $registration->name }}</h3></div>
                                            <div class="text-right"><p class="text-3xl font-bold tabular-nums text-gray-900">{{ $registration->calculated_total }}</p><p class="text-xs text-gray-400">總分</p></div>
                                        </div>

                                        <div class="mt-4 space-y-3">
                                            @foreach($twoRounds ? [['上半局', 1, 6], ['下半局', 7, 12]] : [['各趟成績', 1, $state['required_ends']]] as [$roundLabel, $fromEnd, $toEnd])
                                                <div>
                                                    <p class="mb-1 text-[11px] font-medium text-gray-500">{{ $roundLabel }}</p>
                                                    <div class="grid grid-cols-6 gap-1.5">
                                                        @foreach(range($fromEnd, $toEnd) as $end)
                                                            @php($entry = $entriesByEnd->get($end))
                                                            <div class="rounded-lg border {{ $entry ? 'border-gray-200 bg-gray-50 text-gray-900' : 'border-dashed border-gray-200 text-gray-300' }} px-1 py-2 text-center">
                                                                <p class="text-[10px] font-medium">{{ $twoRounds ? ($end > 6 ? $end - 6 : $end) : $end }}</p>
                                                                <p class="mt-0.5 font-mono text-sm font-bold">{{ $entry?->end_total ?? '__' }}</p>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>

                                        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t pt-3">
                                            <div class="flex flex-wrap gap-2 text-xs">
                                                <span class="rounded-full bg-gray-100 px-2.5 py-1 font-medium">10：{{ $registration->calculated_ten_count }}</span>
                                                <span class="rounded-full bg-gray-100 px-2.5 py-1 font-medium">X：{{ $registration->calculated_x_count }}</span>
                                                <span class="rounded-full px-2.5 py-1 font-medium {{ $registration->result_status === 'dnf' ? 'bg-amber-100 text-amber-700' : ($complete ? 'bg-green-100 text-green-700' : 'bg-indigo-100 text-indigo-700') }}">{{ $registration->result_status === 'dnf' ? '未報到 DNF' : ($complete ? '成績完整' : ($registration->score_verified_at ? '現有分數結算' : '部分成績待審核')) }}</span>
                                                <span class="rounded-full px-2.5 py-1 font-medium {{ $registration->score_verified_at ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">{{ $registration->score_verified_at ? '主辦已確認' : '待確認' }}</span>
                                            </div>
                                            <a href="{{ route('organizer.events.results.registrations.edit', [$event, $registration]) }}" class="inline-flex min-h-10 items-center rounded-xl border border-indigo-200 px-3 text-sm font-medium text-indigo-700">{{ $registration->result_published_at ? '查看完整成績' : '查看／修正' }}</a>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                    @if($items->contains(fn ($item) => !$item->score_verified_at))
                        <div class="flex justify-end border-t p-4">
                            <button form="verify-form" onclick="return confirm('確定審核選取成績？已報到的未完賽選手將以現有分數結算；未報到選手才會標記為 DNF。')" class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white">確認選取成績</button>
                        </div>
                    @endif
                @endif
            </section>
        @endforeach
    </div>
</div>
@endsection
