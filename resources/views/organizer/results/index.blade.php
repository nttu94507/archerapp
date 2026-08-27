@extends('layouts.app')

@section('title', $event->name.' 成績管理')

@section('content')
<div class="mx-auto max-w-6xl space-y-6 px-4 py-8 sm:px-6">
    <div>
        <a href="{{ route('organizer.events.show', $event) }}" class="text-sm text-indigo-600">← 返回賽事工作台</a>
        <h1 class="mt-2 text-2xl font-bold">成績確認與分組發布</h1>
        <p class="mt-1 text-sm text-gray-500">已報到選手即使中途停止，也會依現有分數結算並參與排名；只有未報到選手會標記為棄賽（DNF）。</p>
        <p class="mt-1 text-sm text-indigo-700">成績須由成績管理員或主裁判核准，核准完成後才能由主辦方正式發布。</p>
    </div>

    @if(session('success'))<div class="rounded-xl bg-green-50 p-4 text-sm text-green-700">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="rounded-xl bg-red-50 p-4 text-sm text-red-700">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="rounded-xl bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>@endif

    @if($canApproveResults)<form id="verify-form" method="POST" action="{{ route('organizer.events.results.verify', $event) }}" class="hidden">@csrf</form>@endif

    <div class="space-y-5">
        @foreach($event->groups as $group)
            @php
                $state = $groupStates->get($group->id);
                $items = $state['registrations'];
                $rankingSnapshot = $currentSnapshots->get($group->id);
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
                                @if($rankingSnapshot)<span class="rounded-full bg-violet-100 px-2.5 py-1 text-xs font-medium text-violet-700">種子快照 v{{ $rankingSnapshot->version }}</span>@else<span class="rounded-full bg-red-100 px-2.5 py-1 text-xs font-medium text-red-700">缺少種子快照</span>@endif
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
                            <span class="rounded-lg bg-white px-2.5 py-1.5 {{ $state['unverified'] ? 'text-red-600' : 'text-green-700' }}">尚未核准 {{ $state['unverified'] }}</span>
                        </div>
                        @if(!$state['has_session'] || !$state['has_targets'])
                            <p class="mt-2 text-xs text-red-600">此組別尚未建立排靶與計分場次。</p>
                        @endif
                    </div>

                    @if(!$state['published'] && ($canApproveResults || $canManageResults))
                        <div class="flex flex-wrap gap-2 sm:justify-end">
                            @if($canApproveResults)
                            <form method="POST" action="{{ route('organizer.events.results.groups.verify', [$event, $group]) }}" onsubmit="return confirm('確定一次核准「{{ $group->name }}」所有成績？')">
                                @csrf
                                <button @disabled(!$canVerifyGroup)
                                        class="min-h-11 rounded-xl px-4 text-sm font-medium {{ $canVerifyGroup ? 'bg-indigo-600 text-white hover:bg-indigo-500' : 'cursor-not-allowed bg-gray-200 text-gray-400' }}">
                                    核准本組全部成績
                                </button>
                            </form>
                            @endif
                            @if($canManageResults)
                            <form method="POST" action="{{ route('organizer.events.results.publish', [$event, $group]) }}" onsubmit="return confirm('確定發布「{{ $group->name }}」的正式成績？發布後選手即可從我的賽事查看。')">
                                @csrf
                                <button @disabled(!$canPublish)
                                        class="min-h-11 rounded-xl px-4 text-sm font-medium {{ $canPublish ? 'bg-green-600 text-white hover:bg-green-500' : 'cursor-not-allowed bg-gray-200 text-gray-400' }}">
                                    發布本組正式成績
                                </button>
                            </form>
                            @endif
                        </div>
                    @elseif($state['published'] && $canManageResults && !$rankingSnapshot)
                        <form method="POST" action="{{ route('organizer.events.results.ranking-snapshot', [$event, $group]) }}" onsubmit="return confirm('將依目前已發布的正式成績補建排名種子快照，不會更改成績或重發 Badge。確定繼續？')">
                            @csrf
                            <button class="min-h-11 rounded-xl bg-violet-600 px-4 text-sm font-medium text-white hover:bg-violet-500">補建排名種子快照</button>
                        </form>
                    @endif
                </div>

                @if($items->isEmpty())
                    <p class="p-6 text-center text-sm text-gray-500">此組別沒有有效選手。</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-white text-left text-xs text-gray-500"><tr>
                                <th class="p-3">@if($canApproveResults)<input type="checkbox" aria-label="選取本組待核准成績" onclick="document.querySelectorAll('.{{ $checkClass }}').forEach(el=>el.checked=this.checked)">@endif</th>
                                <th class="p-3">選手</th><th class="p-3">總分</th><th class="p-3">10</th><th class="p-3">X</th><th class="p-3">成績完整</th><th class="p-3">成績核准</th><th class="p-3">發布狀態</th><th class="p-3">操作</th>
                            </tr></thead>
                            <tbody class="divide-y">
                            @foreach($items->sortByDesc('calculated_total') as $registration)
                                @php($complete = $registration->score_submitted_at && $registration->scoreEntries->count() >= $state['required_ends'])
                                <tr>
                                    <td class="p-3">@if($canApproveResults && !$registration->score_verified_at)<input form="verify-form" class="{{ $checkClass }} rounded" type="checkbox" name="registration_ids[]" value="{{ $registration->id }}">@endif</td>
                                    <td class="p-3 font-medium">{{ $registration->name }}</td>
                                    <td class="p-3 font-semibold">{{ $registration->calculated_total }}</td>
                                    <td class="p-3 font-semibold">{{ $registration->calculated_ten_count }}</td>
                                    <td class="p-3 font-semibold">{{ $registration->calculated_x_count }}</td>
                                    <td class="p-3 {{ $registration->result_status === 'dns' ? 'text-amber-700' : ($complete ? 'text-green-700' : 'text-indigo-700') }}">{{ $registration->result_status === 'dns' ? '未報到（DNS）' : ($complete ? '完整' : ($registration->score_verified_at ? '以現有分數結算' : '部分成績待審核')) }}</td>
                                    <td class="p-3">{{ $registration->score_verified_at ? '已核准' : '待核准' }}</td>
                                    <td class="p-3">{{ $registration->result_published_at ? '已發布' : '—' }}</td>
                                    <td class="p-3"><a href="{{ route('organizer.events.results.registrations.edit', [$event, $registration]) }}" class="whitespace-nowrap font-medium text-indigo-600 hover:underline">{{ $registration->result_published_at || !$canCorrectScores || $registration->result_status === 'dns' ? '查看明細' : '查看／修正' }}</a></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if($canApproveResults && $items->contains(fn ($item) => !$item->score_verified_at))
                        <div class="flex justify-end border-t p-4">
                            <button form="verify-form" onclick="return confirm('確定核准選取成績？已報到的未完賽選手將以現有分數結算；未報到選手才會標記為 DNF。')" class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white">核准選取成績</button>
                        </div>
                    @endif
                @endif
            </section>
        @endforeach
    </div>
</div>
@endsection
