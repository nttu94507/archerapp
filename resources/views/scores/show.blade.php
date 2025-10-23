@extends('layouts.app')

@section('title', '訓練紀錄')

@section('content')
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">

        {{-- Page Header --}}
        <div class="mb-6 flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-gray-900">訓練分析</h1>
                <p class="text-sm text-gray-500 mt-1">檢視此場訓練的每趟每箭成績、合計與累計。</p>
            </div>
{{--            <div class="flex items-center gap-2">--}}
{{--                <button type="button" onclick="history.back()"--}}
{{--                        class="inline-flex items-center justify-center rounded-xl border px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">--}}
{{--                    返回--}}
{{--                </button>--}}
{{--                --}}{{-- 需要可再加編輯/刪除 --}}
{{--            </div>--}}
        </div>

        {{-- Meta Chips --}}
        <div class="mb-4 flex flex-wrap items-center gap-2 text-xs">
        <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-gray-700">
            弓種：{{ ucfirst($session->bow_type) }}
        </span>
            <span class="inline-flex items-center rounded-full {{ $session->venue==='indoor'?'bg-blue-50 text-blue-700':'bg-emerald-50 text-emerald-700' }} px-2.5 py-1">
            場地：{{ $session->venue==='indoor'?'室內':'室外' }}
        </span>
            <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-gray-700">
            距離：{{ $session->distance_m }}m
        </span>
            <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-gray-700">
            總箭數：{{ $session->arrows_total }}
        </span>
            <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-gray-700">
            每趟：{{ $session->arrows_per_end }}
        </span>
            <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-gray-700">
            建立：{{ $session->created_at->format('Y-m-d H:i') }}
        </span>
            <span class="inline-flex items-center rounded-full bg-indigo-50 px-2.5 py-1 text-indigo-700">
            總分：{{ $session->score_total }}
        </span>
            <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-1 text-amber-700">
            X：{{ $session->x_count }}
        </span>
            <span class="inline-flex items-center rounded-full bg-rose-50 px-2.5 py-1 text-rose-700">
            M：{{ $session->m_count }}
        </span>

            @if($session->note)
                <span class="inline-flex items-center rounded-full bg-yellow-50 px-2.5 py-1 text-yellow-800">
                備註：{{ $session->note }}
            </span>
            @endif
        </div>

        {{-- Scoring Table --}}
        @php
            // 將 shots 依 end_seq 群組
            $grouped = ($shots ?? collect())->groupBy('end_seq')->sortKeys();
            $per = (int) $session->arrows_per_end;
            $cumu = 0;

            // 安全：確保有集合
            if (!($grouped instanceof \Illuminate\Support\Collection)) {
                $grouped = collect($grouped);
            }
        @endphp

        <div class="overflow-x-auto rounded-2xl border">
            <table id="score-table" class="min-w-full text-sm table-fixed">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500 sticky top-0 z-10">
                <tr id="thead-row">
{{--                    <th class="px-3 py-2 text-left w-16">End</th>--}}
                    @for($i=1; $i<=$per; $i++)
                        <th class="px-3 py-2 text-center w-14 sm:w-16 whitespace-nowrap">A{{ $i }}</th>
                    @endfor
                    <th class="px-2 sm:px-3 py-2 text-right w-20 sm:w-24">End 合計</th>
                    <th class="px-2 sm:px-3 py-2 text-right w-20 sm:w-24">累計</th>
                </tr>
                </thead>
                <tbody id="tbody" class="divide-y">
                @forelse($grouped as $endSeq => $rows)
                    @php
                        // 以 shot_seq 排序
                        $rows = $rows->sortBy('shot_seq')->values();
                        // 計算 end 合計（X=10, M=0）
                        $endSum = $rows->sum('score');
                        $cumu += $endSum;
                    @endphp
                    <tr class="{{ $loop->even ? 'bg-white' : 'bg-gray-50/50' }}">
{{--                        <td class="px-3 sm:px-4 py-2 font-medium">{{ $endSeq }}</td>--}}

                        {{-- 每箭 --}}
                        @for($i=1; $i<=$per; $i++)
                            @php
                                $shot = $rows->firstWhere('shot_seq', $i);
                                $txt  = '';
                                if ($shot) {
                                    if ($shot->is_x && (int)$shot->score === 10)      $txt = 'X';
                                    elseif ($shot->is_miss && (int)$shot->score === 0) $txt = 'M';
                                    else $txt = (string) $shot->score;
                                }
                            @endphp
                            <td class="p-0">
                                <div class="w-full px-3 sm:px-4 py-2 text-center text-sm leading-5 min-h-9
                                            font-variant-numeric tabular-nums">
                                    {{ $txt }}
                                </div>
                            </td>
                        @endfor

                        {{-- 合計／累計 --}}
                        <td class="px-2 sm:px-4 py-2 text-right font-medium font-mono tabular-nums">{{ $endSum }}</td>
                        <td class="px-2 sm:px-4 py-2 text-right font-semibold font-mono tabular-nums">{{ $cumu }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ 2 + $per }}" class="px-4 py-12">
                            <div class="text-center text-gray-600">尚無箭資料</div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{-- Bottom Actions --}}
        <div class="mt-4 flex items-center justify-between">
{{--            <div class="text-sm text-gray-600">--}}
{{--                總分：<span class="font-semibold">{{ $session->score_total }}</span>--}}
{{--                ・ X：<span class="font-semibold">{{ $session->x_count }}</span>--}}
{{--                ・ M：<span class="font-semibold">{{ $session->m_count }}</span>--}}
{{--            </div>--}}

{{--            <div class="flex items-center gap-2">--}}
{{--                <button type="button" id="export-json"--}}
{{--                        class="inline-flex items-center justify-center rounded-xl border px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">--}}
{{--                    匯出 JSON--}}
{{--                </button>--}}
{{--                <button type="button" id="export-csv"--}}
{{--                        class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500">--}}
{{--                    匯出 CSV--}}
{{--                </button>--}}
{{--            </div>--}}
        </div>
    </div>

    {{-- 讓數字等寬更整齊 --}}
    <style>
        #score-table [class*="tabular-nums"] { font-variant-numeric: tabular-nums; }
    </style>

    {{-- 匯出（純前端，從頁面資料組裝） --}}
    <script>
        (() => {
            const per = {{ (int)$session->arrows_per_end }};
            const meta = {
                bow: @json($session->bow_type),
                venue: @json($session->venue),
                distance: {{ (int)$session->distance_m }},
                arrows_total: {{ (int)$session->arrows_total }},
                arrows_per_end: per,
                created_at: @json($session->created_at?->toIso8601String()),
                note: @json($session->note),
            };

            // 從表格回組 scores / isX / isMiss（僅做匯出用）
            function collectFromTable() {
                const tbody = document.getElementById('tbody');
                const rows = Array.from(tbody.querySelectorAll('tr'));
                const scores = [], isX = [], isMiss = [];

                rows.forEach((tr) => {
                    const cells = Array.from(tr.querySelectorAll('td')).slice(1, 1 + per); // 跳過 End 欄
                    const s=[], x=[], m=[];
                    cells.forEach((td) => {
                        const v = (td.textContent || '').trim();
                        if (v === '') { s.push(null); x.push(false); m.push(false); }
                        else if (v === 'X') { s.push(10); x.push(true); m.push(false); }
                        else if (v === 'M') { s.push(0);  x.push(false); m.push(true); }
                        else { s.push(parseInt(v,10)); x.push(false); m.push(false); }
                    });
                    scores.push(s); isX.push(x); isMiss.push(m);
                });
                return { scores, isX, isMiss };
            }

            function download(name, mime, content) {
                const blob = new Blob([content], { type: mime });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a'); a.href = url; a.download = name; a.click();
                URL.revokeObjectURL(url);
            }

            document.getElementById('export-json')?.addEventListener('click', () => {
                const { scores, isX, isMiss } = collectFromTable();
                const data = {
                    meta,
                    scores,
                    isX,
                    isMiss,
                    totals: {
                        score_total: {{ (int)$session->score_total }},
                        x_count:     {{ (int)$session->x_count }},
                        m_count:     {{ (int)$session->m_count }},
                    }
                };
                download(`archery_session_{{ $session->id }}.json`, 'application/json', JSON.stringify(data));
            });

            document.getElementById('export-csv')?.addEventListener('click', () => {
                const { scores, isX, isMiss } = collectFromTable();
                const header = ['End', ...Array.from({length:per},(_,i)=>`A${i+1}`), 'EndSum', 'Cumu'];
                let csv = '';
                // Meta
                csv += `Bow,${meta.bow}\nVenue,${meta.venue}\nDistance(m),${meta.distance}\nArrows,${meta.arrows_total}\nPer End,${per}\n\n`;
                csv += header.join(',') + '\n';

                let cum = 0;
                for (let e=0; e<scores.length; e++){
                    const arr = scores[e].map((v,i)=>{
                        if (isX[e][i]) return 'X(10)';
                        if (isMiss[e][i]) return 'M(0)';
                        return (v ?? '');
                    });
                    const endSum = scores[e].reduce((a,b)=>a+(b||0),0);
                    cum += endSum;
                    csv += [e+1, ...arr, endSum, cum].join(',') + '\n';
                }
                download(`archery_session_{{ $session->id }}.csv`, 'text/csv;charset=utf-8;', csv);
            });
        })();
    </script>
@endsection
