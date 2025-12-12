@extends('layouts.app')

@section('title', $event->name.' 即時戰況')

@section('content')
    <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 py-8 space-y-8">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-xs uppercase tracking-widest text-indigo-600 font-semibold">Live</p>
                <h1 class="text-2xl font-bold text-gray-900">{{ $event->name }} 即時戰況</h1>
                <p class="text-sm text-gray-600">{{ $event->organizer }} </p>
                <p class="text-sm text-gray-500">{{ $event->start_date }} ~ {{ $event->end_date }}</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('events.show', $event) }}" class="inline-flex items-center rounded-xl border border-gray-200 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">回到賽事頁</a>
            </div>
        </div>

        @if(isset($groupsBoard) && $groupsBoard->isNotEmpty())
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs uppercase tracking-widest text-gray-500">組別清單</p>
                        <h2 class="text-lg font-semibold text-gray-900">選擇組別查看戰況</h2>
{{--                        <p class="text-xs text-gray-500">點擊組別後開啟詳情。</p>--}}
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($groupsBoard as $board)
                        @php
                            $groupId = optional($board['group'])->id;
                        @endphp
                        <a
                            href="{{ $groupId ? route('events.live', ['event' => $event, 'group' => $groupId]) : '#' }}"
                            class="group relative block rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 shadow-sm transition hover:-translate-y-0.5 hover:border-indigo-200 hover:bg-white hover:shadow-md"
                        >
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
        @else
            <div class="rounded-2xl border border-gray-200 bg-white p-6 text-center text-gray-500">目前尚無組別資訊。</div>
        @endif

        <div class="space-y-8">
            @if($selectedBoard)
                <div id="group-{{ optional($selectedBoard['group'])->id ?? 'none' }}" class="rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                    <div class="flex flex-col gap-3 border-b border-gray-100 bg-gray-50 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-xs uppercase tracking-widest text-gray-500">組別</p>
                            <h2 class="text-lg font-semibold text-gray-900">{{ optional($selectedBoard['group'])->name ?? '未分組' }}</h2>
                            <p class="text-sm text-gray-500">
                                {{ optional($selectedBoard['group'])->distance ? optional($selectedBoard['group'])->distance : '' }}
                                {{ $event->mode === 'indoor' ? '室內' : '室外' }} · 共 {{ $selectedBoard['totalArrows'] }} 支 / 每趟 {{ $selectedBoard['arrowsPerEnd'] }} 支
                            </p>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-xs sm:grid-cols-2">
                            <div class="rounded-xl bg-white px-3 py-2 text-center shadow-sm">
                                <p class="text-[11px] text-gray-500">狀態</p>
                                <p class="text-base font-semibold text-gray-900">{{ $selectedBoard['status_label'] }}</p>
                            </div>
                            <div class="rounded-xl bg-white px-3 py-2 text-center shadow-sm">
                                <p class="text-[11px] text-gray-500">選手數</p>
                                <p class="text-base font-semibold text-gray-900">{{ $selectedBoard['rows']->count() }}</p>
                            </div>
                        </div>
                    </div>

                    @if($selectedBoard['rows']->isEmpty())
                        <p class="px-4 py-5 text-sm text-gray-500">尚無成績資料。</p>
                    @else
                        <div class="divide-y divide-gray-100">
                            @foreach($selectedBoard['rows'] as $row)
                                <details class="group">
                                    <summary class="flex cursor-pointer items-center gap-3 px-4 py-3 hover:bg-gray-50">
                                        <div class="w-10 text-center text-sm font-semibold text-gray-800">#{{ $row['rank_position'] }}</div>
                                        <div class="flex-1">
                                            <p class="text-sm font-semibold text-gray-900">{{ $row['registration']->name ?? '未命名選手' }}</p>
                                            <p class="text-xs text-gray-500">{{ $row['registration']->team_name ?? '未填隊伍' }}</p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-lg font-bold text-gray-900">{{ $row['total_score'] }}</p>
                                            <p class="text-xs text-gray-500">{{ $row['ends_recorded'] }} / {{ $selectedBoard['totalEnds'] }} 趟</p>
                                        </div>
{{--                                        <div class="text-right text-xs text-gray-500 w-28">{{ optional($row['last_updated'])->diffForHumans() ?? '—' }}</div>--}}
                                    </summary>
                                    <div class="bg-gray-50 px-4 pb-4 pt-2">
                                        <div class="flex flex-wrap gap-2 text-xs text-gray-600 mb-3">
{{--                                            <span class="inline-flex items-center rounded-full bg-white px-2 py-1 shadow-sm">箭數 {{ $row['arrow_count'] }}</span>--}}
{{--                                            <span class="inline-flex items-center rounded-full bg-white px-2 py-1 shadow-sm">每箭均值 {{ $row['avg_per_arrow'] ?? '—' }}</span>--}}
                                            <span class="inline-flex items-center rounded-full bg-white px-2 py-1 shadow-sm">完成 {{ $row['ends_recorded'] }} / {{ $selectedBoard['totalEnds'] }} 趟</span>
                                        </div>

                                        @php
                                            $per = $selectedBoard['arrowsPerEnd'];
                                            $totalEnds = $selectedBoard['totalEnds'];
                                            $entriesByEnd = $row['entries']->keyBy('end_number');
                                            $cumulative = 0;
                                        @endphp

                                        <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
                                            <table class="min-w-full text-sm text-gray-800">
                                                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                                                <tr>
{{--                                                    <th class="px-3 py-2 text-left">趟次</th>--}}
                                                    @for($i = 1; $i <= $per; $i++)
                                                        <th class="px-2 py-2 text-center">A{{ $i }}</th>
                                                    @endfor
                                                    <th class="px-3 py-2 text-right">小計</th>
                                                    <th class="px-3 py-2 text-right">累計</th>
                                                    <th class="px-3 py-2 text-right">X+10</th>
                                                    <th class="px-3 py-2 text-right">X</th>
                                                    <th class="px-3 py-2 text-right">每箭均值</th>


                                                </tr>
                                                </thead>
                                                <tbody>
                                                @for($end = 1; $end <= $totalEnds; $end++)
                                                    @php
                                                        $entry = $entriesByEnd->get($end);
                                                        $scores = $entry?->scores ?? [];
                                                        $endTotal = $entry->end_total ?? $entry->score_total ?? null;
                                                        $hasEnd = !is_null($endTotal);

                                                        $endTenPlus = $entry->ten_plus ?? 0;
                                                        $endX = $entry->x_count ?? 0;
                                                        $endAvg = $entry->avg_per_arrow ?? null;

                                                        if ($hasEnd) {
                                                            $cumulative += $endTotal;
                                                        }
                                                    @endphp
                                                    <tr class="border-t border-gray-100">
{{--                                                        <td class="px-3 py-2 text-xs text-gray-600">第 {{ $end }} 趟</td>--}}
                                                        @for($shot = 0; $shot < $per; $shot++)
                                                            <td class="px-2 py-2 text-center font-semibold text-gray-900">
                                                                {{ $scores[$shot] ?? '—' }}
                                                            </td>
                                                        @endfor
                                                        <td class="px-3 py-2 text-right font-semibold text-gray-900">{{ $hasEnd ? $endTotal : '—' }}</td>
                                                        <td class="px-3 py-2 text-right text-gray-800">{{ $hasEnd ? $cumulative : '—' }}</td>
                                                        <td class="px-3 py-2 text-right font-semibold text-gray-900">{{ $hasEnd ? $endTenPlus : '—' }}</td>
                                                        <td class="px-3 py-2 text-right font-semibold text-gray-900">{{ $hasEnd ? $endX : '—' }}</td>
                                                        <td class="px-3 py-2 text-right text-gray-800">{{ $hasEnd && !is_null($endAvg) ? $endAvg : '—' }}</td>

                                                    </tr>
                                                @endfor
                                                </tbody>
                                                <tfoot class="bg-gray-50">
                                                    <tr class="border-t border-gray-100">
                                                        <td class="px-3 py-2 text-xs text-gray-600">總計</td>
                                                        <td colspan="{{ $per }}" class="px-2 py-2 text-center text-[11px] text-gray-400"></td>
                                                        <td class="px-3 py-2 text-right font-semibold text-gray-900">{{ $row['total_score'] }}</td>
                                                        <td class="px-3 py-2 text-right font-semibold text-gray-900">{{ $row['ten_plus'] }}</td>
                                                        <td class="px-3 py-2 text-right font-semibold text-gray-900">{{ $row['x_count'] }}</td>
                                                        <td class="px-3 py-2 text-right font-semibold text-gray-800">{{ $row['avg_per_arrow'] ?? '—' }}</td>

                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>
                                </details>
                            @endforeach
                        </div>
                    @endif
                </div>
            @elseif(isset($groupsBoard) && $groupsBoard->isNotEmpty())
                <div class="rounded-2xl border border-dashed border-gray-300 bg-white p-6 text-center text-gray-500">
                    選擇上方組別即可查看排名與對抗賽詳細成績。
                </div>
            @endif
        </div>
    </div>
@endsection
