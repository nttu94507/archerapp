@extends('layouts.app')

@section('title', $event->name.' 即時戰況')

@section('content')
    <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 py-8 space-y-8">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-xs uppercase tracking-widest text-indigo-600 font-semibold">Live</p>
                <h1 class="text-2xl font-bold text-gray-900">{{ $event->name }} 即時戰況</h1>
                <p class="text-sm text-gray-600">{{ $event->organizer }} · {{ $event->mode === 'indoor' ? '室內賽' : '室外賽' }}</p>
                <p class="text-sm text-gray-500">{{ $event->start_date }} ~ {{ $event->end_date }}</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('events.show', $event) }}" class="inline-flex items-center rounded-xl border border-gray-200 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">回到賽事頁</a>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs text-gray-500">有效報名</p>
                <p class="text-2xl font-semibold text-gray-900 mt-1">{{ $overallSummary['registrations'] }}</p>
                <p class="text-xs text-gray-500">{{ $overallSummary['groups'] }} 個組別</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs text-gray-500">已送出趟數</p>
                <p class="text-2xl font-semibold text-indigo-700 mt-1">{{ $overallSummary['entry_records'] }}</p>
                <p class="text-xs text-gray-500">單趟紀錄筆數</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs text-gray-500">累計箭數</p>
                <p class="text-2xl font-semibold text-gray-900 mt-1">{{ $overallSummary['arrows_recorded'] }}</p>
                <p class="text-xs text-gray-500">已登錄箭值</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs text-gray-500">最近更新</p>
                <p class="text-sm font-semibold text-gray-900 mt-1">{{ optional($overallSummary['last_updated'])->diffForHumans() ?? '—' }}</p>
                <p class="text-xs text-gray-500">持續即時刷新資料</p>
            </div>
        </div>

        @if(isset($activeGroup))
            <div class="rounded-2xl border border-indigo-100 bg-white shadow-sm ring-1 ring-indigo-50 p-5 space-y-4">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-3">
                        <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-indigo-50 text-indigo-700 font-bold">⏱</span>
                        <div>
                            <p class="text-xs uppercase tracking-widest text-indigo-600">目前賽事</p>
                            <p class="text-lg font-semibold text-gray-900">{{ optional($activeGroup['group'])->name ?? '未分組' }}</p>
                            <p class="text-sm text-gray-500">{{ $activeGroup['status_label'] }} · 最近更新 {{ optional($activeGroup['analysis']['recent_update'])->diffForHumans() ?? '—' }}</p>
                        </div>
                    </div>
                    <a href="#group-{{ optional($activeGroup['group'])->id ?? 'none' }}" class="inline-flex items-center gap-1 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">
                        查看組別戰況
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                        </svg>
                    </a>
                </div>
                @if(!is_null($activeGroup['progress']))
                    <div class="h-3 w-full rounded-full bg-indigo-50 overflow-hidden">
                        <div class="h-full bg-indigo-500" style="width: {{ $activeGroup['progress'] }}%"></div>
                    </div>
                    <p class="text-xs text-gray-500">完成度 {{ $activeGroup['progress'] }}%</p>
                @endif
            </div>
        @endif

        @if(isset($groupsBoard) && $groupsBoard->isNotEmpty())
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-2">
                    <div>
                        <p class="text-xs uppercase tracking-widest text-gray-500">組別清單</p>
                        <h2 class="text-lg font-semibold text-gray-900">即時賽況總覽</h2>
                    </div>
                    <p class="text-xs text-gray-400">點擊組別即可跳轉至詳細排名與對抗賽紀錄</p>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($groupsBoard as $board)
                        <a href="#group-{{ optional($board['group'])->id ?? 'none' }}" class="group relative block rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 shadow-sm transition hover:-translate-y-0.5 hover:border-indigo-200 hover:bg-white hover:shadow-md">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="text-xs uppercase tracking-widest text-gray-500">組別</p>
                                    <p class="text-base font-semibold text-gray-900 group-hover:text-indigo-700">{{ optional($board['group'])->name ?? '未分組' }}</p>
                                    <p class="text-xs text-gray-500 mt-1">狀態：<span class="font-semibold text-gray-800">{{ $board['status_label'] }}</span></p>
                                </div>
                                <span class="inline-flex items-center rounded-full bg-white px-2 py-1 text-[11px] font-semibold text-indigo-700 shadow-sm">{{ $board['rows']->count() }} 位選手</span>
                            </div>
                            <div class="mt-3 h-2 w-full rounded-full bg-white">
                                <div class="h-full rounded-full bg-indigo-500" style="width: {{ $board['progress'] ?? 0 }}%"></div>
                            </div>
                            <p class="mt-1 text-[11px] text-gray-500">完成度 {{ $board['progress'] ?? 0 }}%</p>
                            <span class="absolute inset-0 rounded-xl ring-2 ring-transparent transition group-hover:ring-indigo-200"></span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        @if(isset($groupLeaders) && $groupLeaders->isNotEmpty())
            <div class="rounded-2xl border border-indigo-100 bg-gradient-to-r from-indigo-50 via-white to-indigo-50 p-4 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-2 text-indigo-700">
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-white text-sm font-bold shadow-sm">★</span>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-widest">組別領先</p>
                            <p class="text-sm text-indigo-700">左右滑動查看每個組別的即時領先者</p>
                        </div>
                    </div>
                    <span class="text-xs text-indigo-500">像跑馬燈一樣側滑查看更多</span>
                </div>

                <div class="mt-3 overflow-x-auto pb-2">
                    <div class="flex min-w-full items-stretch gap-3 snap-x snap-mandatory">
                        @foreach($groupLeaders as $leader)
                            <div class="min-w-[240px] snap-start rounded-xl bg-white/90 p-4 shadow-md ring-1 ring-indigo-100">
                                <p class="text-[11px] font-semibold uppercase tracking-widest text-indigo-600">{{ optional($leader['group'])->name ?? '未分組' }}</p>
                                <p class="mt-1 text-base font-semibold text-gray-900">{{ $leader['registration']->name ?? '未命名選手' }}</p>
                                <p class="text-xs text-gray-500">{{ $leader['registration']->team_name ?? '未填隊伍' }}</p>
                                <div class="mt-2 flex items-center justify-between">
                                    <div>
                                        <p class="text-2xl font-bold text-gray-900">{{ $leader['total_score'] }}</p>
                                        <p class="text-[11px] text-gray-500">{{ $leader['ends_recorded'] }} 趟 · {{ $leader['arrow_count'] }} 支</p>
                                    </div>
                                    <div class="text-right text-[11px] text-gray-500">{{ optional($leader['last_updated'])->diffForHumans() ?? '—' }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        <div class="space-y-8">
            @forelse($groupsBoard as $board)
                <div id="group-{{ optional($board['group'])->id ?? 'none' }}" class="rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                    <div class="flex flex-col gap-3 border-b border-gray-100 bg-gray-50 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-xs uppercase tracking-widest text-gray-500">組別</p>
                            <h2 class="text-lg font-semibold text-gray-900">{{ optional($board['group'])->name ?? '未分組' }}</h2>
                            <p class="text-sm text-gray-500">
                                {{ optional($board['group'])->distance ? optional($board['group'])->distance.'m · ' : '' }}
                                {{ $event->mode === 'indoor' ? '室內' : '室外' }} · 共 {{ $board['totalArrows'] }} 支 / 每趟 {{ $board['arrowsPerEnd'] }} 支
                            </p>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-xs sm:grid-cols-5">
                            <div class="rounded-xl bg-white px-3 py-2 text-center shadow-sm">
                                <p class="text-[11px] text-gray-500">狀態</p>
                                <p class="text-base font-semibold text-gray-900">{{ $board['status_label'] }}</p>
                            </div>
                            <div class="rounded-xl bg-white px-3 py-2 text-center shadow-sm">
                                <p class="text-[11px] text-gray-500">選手數</p>
                                <p class="text-base font-semibold text-gray-900">{{ $board['rows']->count() }}</p>
                            </div>
                            <div class="rounded-xl bg-white px-3 py-2 text-center shadow-sm">
                                <p class="text-[11px] text-gray-500">平均總分</p>
                                <p class="text-base font-semibold text-gray-900">{{ $board['analysis']['average_total'] ?? '—' }}</p>
                            </div>
                            <div class="rounded-xl bg-white px-3 py-2 text-center shadow-sm">
                                <p class="text-[11px] text-gray-500">完成度</p>
                                <p class="text-base font-semibold text-gray-900">{{ $board['analysis']['completion_rate'] ? $board['analysis']['completion_rate'].'%' : '—' }}</p>
                            </div>
                            <div class="rounded-xl bg-white px-3 py-2 text-center shadow-sm">
                                <p class="text-[11px] text-gray-500">最近更新</p>
                                <p class="text-base font-semibold text-gray-900">{{ optional($board['analysis']['recent_update'])->diffForHumans() ?? '—' }}</p>
                            </div>
                        </div>
                    </div>

                    @if($board['rows']->isEmpty())
                        <p class="px-4 py-5 text-sm text-gray-500">尚無成績資料。</p>
                    @else
                        <div class="divide-y divide-gray-100">
                            @foreach($board['rows'] as $row)
                                <details class="group">
                                    <summary class="flex cursor-pointer items-center gap-3 px-4 py-3 hover:bg-gray-50">
                                        <div class="w-10 text-center text-sm font-semibold text-gray-800">#{{ $row['rank_position'] }}</div>
                                        <div class="flex-1">
                                            <p class="text-sm font-semibold text-gray-900">{{ $row['registration']->name ?? '未命名選手' }}</p>
                                            <p class="text-xs text-gray-500">{{ $row['registration']->team_name ?? '未填隊伍' }}</p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-lg font-bold text-gray-900">{{ $row['total_score'] }}</p>
                                            <p class="text-xs text-gray-500">{{ $row['ends_recorded'] }} / {{ $board['totalEnds'] }} 趟</p>
                                        </div>
                                        <div class="text-right text-xs text-gray-500 w-28">{{ optional($row['last_updated'])->diffForHumans() ?? '—' }}</div>
                                    </summary>
                                    <div class="bg-gray-50 px-4 pb-4 pt-2">
                                        <div class="flex flex-wrap gap-2 text-xs text-gray-600 mb-2">
                                            <span class="inline-flex items-center rounded-full bg-white px-2 py-1 shadow-sm">箭數 {{ $row['arrow_count'] }}</span>
                                            <span class="inline-flex items-center rounded-full bg-white px-2 py-1 shadow-sm">平均每趟 {{ $row['ends_recorded'] ? round($row['total_score'] / $row['ends_recorded'], 1) : '—' }} 分</span>
                                        </div>
                                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                            @foreach($row['entries'] as $entry)
                                                <div class="rounded-xl border border-gray-200 bg-white p-3">
                                                    <div class="flex items-center justify-between text-xs text-gray-500">
                                                        <span>第 {{ $entry->end_number }} 趟</span>
                                                        <span>{{ optional($entry->updated_at)->format('m-d H:i') }}</span>
                                                    </div>
                                                    <div class="mt-2 flex flex-wrap gap-1">
                                                        @foreach(($entry->scores ?? []) as $arrow)
                                                            <span class="inline-flex h-7 w-8 items-center justify-center rounded-md bg-gray-100 text-[13px] font-semibold text-gray-800">{{ $arrow === '' ? '—' : $arrow }}</span>
                                                        @endforeach
                                                    </div>
                                                    <p class="mt-2 text-sm font-semibold text-gray-900">小計：{{ $entry->end_total }}</p>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </details>
                            @endforeach
                        </div>
                    @endif
                </div>
            @empty
                <div class="rounded-2xl border border-gray-200 bg-white p-6 text-center text-gray-500">目前尚無組別或成績。</div>
            @endforelse
        </div>
    </div>
@endsection
