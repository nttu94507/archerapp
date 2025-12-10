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

        @if($overallSummary['top_row'] ?? false)
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 shadow-sm">
                <p class="text-xs uppercase tracking-widest text-amber-700 font-semibold">當前領先</p>
                <div class="mt-2 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-lg font-semibold text-gray-900">{{ $overallSummary['top_row']['registration']->name ?? '未命名選手' }}</p>
                        <p class="text-sm text-gray-600">{{ optional($overallSummary['top_row']['registration']->event_group)->name ?? '未分組' }}</p>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="text-right">
                            <p class="text-3xl font-bold text-gray-900">{{ $overallSummary['top_row']['total_score'] }}</p>
                            <p class="text-xs text-gray-500">{{ $overallSummary['top_row']['ends_recorded'] }} 趟 · {{ $overallSummary['top_row']['arrow_count'] }} 支</p>
                        </div>
                        <div class="text-xs text-gray-500">更新：{{ optional($overallSummary['top_row']['last_updated'])->diffForHumans() ?? '—' }}</div>
                    </div>
                </div>
            </div>
        @endif

        <div class="space-y-8">
            @forelse($groupsBoard as $board)
                <div class="rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                    <div class="flex flex-col gap-3 border-b border-gray-100 bg-gray-50 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-xs uppercase tracking-widest text-gray-500">組別</p>
                            <h2 class="text-lg font-semibold text-gray-900">{{ optional($board['group'])->name ?? '未分組' }}</h2>
                            <p class="text-sm text-gray-500">
                                {{ optional($board['group'])->distance ? optional($board['group'])->distance.'m · ' : '' }}
                                {{ $event->mode === 'indoor' ? '室內' : '室外' }} · 共 {{ $board['totalArrows'] }} 支 / 每趟 {{ $board['arrowsPerEnd'] }} 支
                            </p>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-xs sm:grid-cols-4">
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
