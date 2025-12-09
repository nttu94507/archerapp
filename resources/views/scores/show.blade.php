@extends('layouts.app')

@section('title', '訓練紀錄')

@section('content')
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">

        {{-- Page Header --}}
        <div class=" flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-gray-900">訓練分析</h1>
                {{-- 嘴砲總結（若有） --}}
                @if(!empty($summary) && is_array($summary))
                    @php
                        $level = $summary['level'] ?? 'neutral';
                        $tone = match($level) {
                            'great' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                            'good'  => 'bg-sky-50 text-sky-800 border-sky-200',
                            'warn'  => 'bg-amber-50 text-amber-900 border-amber-200',
                            'bad'   => 'bg-rose-50 text-rose-800 border-rose-200',
                            default => 'bg-gray-50 text-gray-800 border-gray-200',
                        };
                        $icon = match($level) {
                            'great' => '🔥', // 神仙發揮
                            'good'  => '✨',
                            'warn'  => '🫠',
                            'bad'   => '🤡',
                            default => '🎯',
                        };
                        $stats = $summary['stats'] ?? [];
                    @endphp

                    <div class="mt-4 mb-2 rounded-2xl border {{ $tone }} p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="text-sm leading-6">
                                    <span class="mr-1">{{ $icon }}</span>{{ $summary['text'] ?? '' }}
                            </div>

                            {{-- 可關閉的「再來一句」按鈕（重新整理會變新隨機句）--}}
                            <form method="GET" class="shrink-0 hidden sm:block">
                                @foreach(request()->except(['_token']) as $k=>$v)
                                    <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                                @endforeach
                                <button class="text-xs px-2 py-1 rounded-lg border hover:bg-white/60">
                                    再來一句
                                </button>
                            </form>
                        </div>
                    </div>
                @endif
            </div>
        </div>
        @php
            $tenTotal = $analysis['scoreDist'][10] ?? 0;   // 10 分（包含 X）
            $xOnly    = $analysis['xCount'] ?? 0;          // X 次數
            $total    = $analysis['totalArrows'] ?? 0;

            $tenRate  = $total ? number_format($tenTotal / $total * 100, 1) : '0.0';
            $xRate    = $analysis['xRate'] ?? ($total ? number_format($xOnly / $total * 100, 1) : '0.0');
        @endphp
        <div class=" space-y-4"> {{-- 原本 space-y-6 -> 4 --}}

            {{-- 指標卡片（更緊湊） --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 items-stretch">
                {{-- 每箭均分 --}}
                <div class="rounded-xl border p-3 h-full flex flex-col">
                    <div class="text-[11px] text-gray-500">每箭均分</div>
                    <div class="mt-auto text-right text-xl font-semibold font-mono tabular-nums leading-tight">
                        {{ $analysis['avg'] }}
                    </div>
                </div>

                {{-- X 命中/命中率 --}}
                <div class="rounded-xl border p-3 h-full flex flex-col">
                    <div class="text-[11px] text-gray-500">X 命中/命中率</div>
                    <div class="mt-auto text-right font-semibold leading-tight">
                        <span class="font-mono tabular-nums">{{ $analysis['xCount'] }}</span>
                        <span class="text-gray-500 text-xs">（{{ $analysis['xRate'] }}%）</span>
                    </div>
                </div>

                {{-- 標準差 --}}
                <div class="rounded-xl border p-3 h-full flex flex-col">
                    <div class="text-[11px] text-gray-500">標準差</div>
                    <div class="mt-auto text-right text-xl font-semibold font-mono tabular-nums leading-tight">
                        {{ $analysis['stddev'] }}
                    </div>
                </div>

                {{-- 黃圈命中率 --}}
                <div class="rounded-xl border p-3 h-full flex flex-col">
                    <div class="text-[11px] text-gray-500">黃圈 命中率</div>
                    <div class="mt-auto text-right text-xl font-semibold font-mono tabular-nums leading-tight">
                        {{ $analysis['nineUpRate'] }}%
                    </div>
                </div>

                {{-- X+10 / X（佔兩欄） --}}
                <div class="rounded-xl border p-3 sm:col-span-2 h-full">
                    <div class="grid grid-cols-2 gap-2">
                        {{-- X+10 --}}
                        <div class="rounded-lg border p-2 h-full flex flex-col">
                            <div class="flex items-baseline justify-between">
                                <span class="text-[11px] text-gray-500">X+10</span>
                                <span class="font-mono tabular-nums text-lg font-semibold">{{ $tenTotal }}</span>
                            </div>
                            <div class="mt-auto text-[11px] text-gray-500 text-right">（{{ $tenRate }}%）</div>
                        </div>

                        {{-- X --}}
                        <div class="rounded-lg border p-2 h-full flex flex-col">
                            <div class="flex items-baseline justify-between">
                                <span class="text-[11px] text-gray-500">X</span>
                                <span class="font-mono tabular-nums text-lg font-semibold">{{ $xOnly }}</span>
                            </div>
                            <div class="mt-auto text-[11px] text-gray-500 text-right">（{{ $xRate }}%）</div>
                        </div>
                    </div>
                </div>
                {{-- 後勁指數 --}}
                <div class="rounded-xl border p-3 h-full flex flex-col">
                    <div class="text-[11px] text-gray-500">後勁指數</div>
                    @if(!is_null($analysis['staminaDelta']))
                        <div class="mt-auto text-right text-xl font-semibold font-mono tabular-nums leading-tight
                    {{ $analysis['staminaDelta'] > 0 ? 'text-emerald-700' : ($analysis['staminaDelta'] < 0 ? 'text-rose-700' : 'text-gray-800') }}">
                            {{ $analysis['staminaDelta'] > 0 ? '+' : '' }}{{ $analysis['staminaDelta'] }}
                        </div>
                        <div class="text-right text-[11px] text-gray-500">
                            前 {{ $analysis['firstHalfAvg'] }} → 後 {{ $analysis['secondHalfAvg'] }}
                        </div>
                    @else
                        <div class="mt-auto text-right text-sm text-gray-400">資料不足</div>
                    @endif
                </div>
            </div>

            {{-- 落點群集 & 靶面 --}}
            @php
                $shotPoints = ($shots ?? collect())
                    ->filter(fn($s) => !is_null($s->target_x) && !is_null($s->target_y))
                    ->map(fn($s) => [
                        'x' => (float)$s->target_x,
                        'y' => (float)$s->target_y,
                        'score' => (int)$s->score,
                        'is_miss' => (bool)$s->is_miss,
                        'is_x' => (bool)$s->is_x,
                        'end_seq' => (int)$s->end_seq,
                        'shot_seq' => (int)$s->shot_seq,
                    ]);
            @endphp
            @if(($analysis['hasCoords'] ?? false) && $shotPoints->count())
                <div class="rounded-2xl border overflow-hidden">
                    <div class="px-3 py-2 bg-gray-50 text-xs font-medium flex items-center justify-between">
                        <span>落點圖</span>
                        <span class="text-gray-500">平均群集半徑 {{ $analysis['groupRadius'] ?? '—' }}，離中心 {{ $analysis['groupOffset'] ?? '—' }}</span>
                    </div>
                    <div class="p-4 flex flex-col items-center gap-2">
                        <div class="relative w-full max-w-[420px] aspect-square">
                            <div class="absolute inset-0 target-face"></div>
                            <canvas id="target-map" class="absolute inset-0"></canvas>
                        </div>
                        <div class="text-xs text-gray-500 text-center">色塊位置為實際記錄的落點，標示對應分值。</div>
                    </div>
                </div>
            @endif

            {{-- 分布圖（更緊湊） --}}
            <div class="rounded-2xl border overflow-hidden">
                <div class="px-3 py-2 bg-gray-50 text-xs font-medium">分值分布圖</div>
                <div class="p-3">
                    <canvas id="scoreDistChart" height="84"></canvas> {{-- 原 120 -> 84 --}}
                </div>
            </div>
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
        {{-- 排序控制列（新增） --}}
        <div class="flex justify-end items-center mb-2">
            <button id="toggle-sort-desc" class="text-xs px-2 py-1 rounded-lg border hover:bg-white/60">
                高→低排序
            </button>
        </div>
        <div class="overflow-x-auto rounded-2xl border">
            <table id="score-table" class="min-w-full text-sm table-fixed">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500 sticky top-0 z-10">
                <tr id="thead-row">
                    @for($i=1; $i<=$per; $i++)
                        <th class="px-3 py-2 text-center w-14 sm:w-16 whitespace-nowrap">A{{ $i }}</th>
                    @endfor
                    <th class="px-2 sm:px-3 py-2 text-right w-20 sm:w-24">小計</th>
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

    {{-- 讓數字等寬更整齊 --}}
    <style>
        #score-table [class*="tabular-nums"] { font-variant-numeric: tabular-nums; }
        .target-face {
            background:
                radial-gradient(circle at center, #111827 0 4%, transparent 4%),
                radial-gradient(circle at center,
                    #fcd34d 0 10%,
                    #fcd34d 10% 20%,
                    #ef4444 20% 30%,
                    #ef4444 30% 40%,
                    #3b82f6 40% 50%,
                    #3b82f6 50% 60%,
                    #111827 60% 70%,
                    #111827 70% 80%,
                    #ffffff 80% 90%,
                    #ffffff 90% 100%);
            border-radius: 9999px;
        }
    </style>
@endsection
@section('js')
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

            // 10（不含 X）與 X 的統計
            function countTenOnly(scores, isX) {
                let tenOnly = 0;
                for (let e = 0; e < scores.length; e++) {
                    for (let i = 0; i < scores[e].length; i++) {
                        if (scores[e][i] === 10 && !isX[e][i]) tenOnly++;
                    }
                }
                return tenOnly;
            }
            function countX(isX) {
                let xCount = 0;
                for (let e = 0; e < isX.length; e++) {
                    for (let i = 0; i < isX[e].length; i++) {
                        if (isX[e][i]) xCount++;
                    }
                }
                return xCount;
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

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const order = ['X', 10, 9, 8, 7, 6, 5, 4, 3, 2, 1, 'M'];
            const labels = order.map(String);
            const data = order.map(k => (k === 'X' ? {{ $analysis['xCount'] }}
                : k === 'M' ? {{ $analysis['missCount'] }}
                    : ({{ Js::from($analysis['scoreDist']) }}[k] || 0)));
            const xCount = {{ $analysis['xCount'] }};
            const mCount = {{ $analysis['missCount'] }};

            const ctx = document.getElementById('scoreDistChart');
            if (ctx) {
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: [...labels],
                        datasets: [{ label: '次數', data: [...data, xCount, mCount], borderWidth: 1 }]
                    },
                    options: {
                        responsive: true,
                        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
                        plugins: { legend: { display: false }, tooltip: { mode: 'index', intersect: false } }
                    }
                });
            }

            const shotPoints = @json($shotPoints ?? []);
            const mapCanvas = document.getElementById('target-map');
            if (mapCanvas && Array.isArray(shotPoints) && shotPoints.length) {
                const ctxMap = mapCanvas.getContext('2d');
                const wrapper = mapCanvas.parentElement;

                function resizeMap() {
                    const rect = wrapper.getBoundingClientRect();
                    mapCanvas.width = rect.width * devicePixelRatio;
                    mapCanvas.height = rect.height * devicePixelRatio;
                    mapCanvas.style.width = `${rect.width}px`;
                    mapCanvas.style.height = `${rect.height}px`;
                    renderMap();
                }

                function renderMap() {
                    ctxMap.clearRect(0, 0, mapCanvas.width, mapCanvas.height);
                    const halfW = mapCanvas.width / 2;
                    const halfH = mapCanvas.height / 2;
                    shotPoints.forEach((pt) => {
                        const cx = halfW + pt.x * halfW;
                        const cy = halfH + pt.y * halfH;
                        ctxMap.beginPath();
                        ctxMap.fillStyle = pt.is_x ? 'rgba(37,99,235,0.9)' : 'rgba(59,130,246,0.8)';
                        ctxMap.arc(cx, cy, 7 * devicePixelRatio, 0, Math.PI * 2);
                        ctxMap.fill();
                        ctxMap.font = `${12 * devicePixelRatio}px ui-sans-serif`;
                        ctxMap.fillStyle = 'rgba(17,24,39,0.75)';
                        const label = pt.is_miss ? 'M' : (pt.is_x ? 'X' : String(pt.score));
                        ctxMap.fillText(label, cx + 8 * devicePixelRatio, cy - 8 * devicePixelRatio);
                    });
                }

                resizeMap();
                window.addEventListener('resize', resizeMap);
            }
        });
    </script>
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    const btn = document.getElementById('toggle-sort-desc');
                    const tbody = document.getElementById('tbody');
                    const theadRow = document.getElementById('thead-row');

                    if (!btn || !tbody || !theadRow) return;

                    // 箭數欄位數（A1..A{per}），thead 最後兩欄是「小計 / 累計」
                    const per = theadRow.children.length - 2;
                    let sorted = false;

                    // 幫手：讀取/寫入單一儲存格（優先作用到內層 div）
                    function getCellText(td) {
                        const slot = td.querySelector('div');
                        return (slot ? slot.textContent : td.textContent).trim();
                    }
                    function setCellText(td, text) {
                        const slot = td.querySelector('div');
                        if (slot) slot.textContent = text;
                        else td.textContent = text;
                    }

                    function parseCellText(txt) {
                        const v = (txt || '').trim().toUpperCase();
                        if (v === 'X') return { value: 10, isX: true,  text: 'X' };
                        if (v === 'M') return { value: 0,  isX: false, text: 'M' };
                        if (v === '')  return { value: -1, isX: false, text: '' }; // 空白最後
                        const num = parseInt(v, 10);
                        return { value: isNaN(num) ? -1 : num, isX: false, text: isNaN(num) ? '' : String(num) };
                    }

                    function sortOneRow(tr) {
                        const cells = Array.from(tr.querySelectorAll('td')).slice(0, per); // 只處理 A1..A{per}
                        const items = cells.map((td) => {
                            const t = getCellText(td);
                            const parsed = parseCellText(t);
                            return { t, ...parsed };
                        });

                        // 規則：X > 10 > 9 > … > 1 > 0 > 空白；同為 10 時 X 優先
                        items.sort((a, b) => {
                            if (a.value !== b.value) return b.value - a.value;
                            if (a.value === 10 && (a.isX !== b.isX)) return a.isX ? -1 : 1;
                            return 0;
                        });

                        // 把排序後的文本依序寫回到 A1..A{per}
                        const sortedTexts = items.map(it => it.text);
                        cells.forEach((td, i) => setCellText(td, sortedTexts[i]));
                    }

                    function restoreOneRow(tr) {
                        const orig = tr.dataset.orig;
                        if (!orig) return;
                        try {
                            const arr = JSON.parse(orig);
                            const cells = Array.from(tr.querySelectorAll('td')).slice(0, per);
                            cells.forEach((td, i) => setCellText(td, arr[i] ?? ''));
                        } catch (_) {}
                    }

                    btn.addEventListener('click', () => {
                        const rows = Array.from(tbody.querySelectorAll('tr'));

                        if (!sorted) {
                            // 進入排序：先保存原始內容
                            rows.forEach(tr => {
                                const cells = Array.from(tr.querySelectorAll('td')).slice(0, per);
                                tr.dataset.orig = JSON.stringify(cells.map(getCellText));
                            });
                            rows.forEach(sortOneRow);
                            sorted = true;
                            btn.textContent = '原順序';
                        } else {
                            // 還原
                            rows.forEach(restoreOneRow);
                            sorted = false;
                            btn.textContent = '高→低排序';
                        }
                    });
                });
            </script>
@endsection
