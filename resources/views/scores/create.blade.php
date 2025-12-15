{{-- resources/views/scores/index.blade.php --}}
@extends('layouts.app')

@section('title','ArrowTrack | 訓練計分')

@section('content')
    @php
        $defaults = [
            'bow_type'       => request('bow_type', 'recurve'),
            'venue'          => request('venue', 'outdoor'),
            'distance'       => (int) request('distance', 18),
            'arrows_total'   => (int) request('arrows_total', 36),
            'arrows_per_end' => (int) request('arrows_per_end', 6),
            'target_face'    => request('target_face', 'ten-ring'),
        ];
    @endphp

    {{-- 根節點，提供 JS 讀取的初始值 --}}
    <div id="score-root"
         data-bow="{{ $defaults['bow_type'] }}"
         data-venue="{{ $defaults['venue'] }}"
         data-distance="{{ $defaults['distance'] }}"
         data-arrows-total="{{ $defaults['arrows_total'] }}"
         data-arrows-per-end="{{ $defaults['arrows_per_end'] }}"
         data-target-face="{{ $defaults['target_face'] }}">
    </div>

    <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 py-8 pb-40 lg:pb-0">
        {{-- Page Header --}}
        <div class="mb-4 flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-gray-900">訓練計分</h1>
            </div>
        </div>

        <div class="grid lg:grid-cols-2 gap-4 mb-4">
            {{-- 靶面輸入卡 --}}
            <div class="rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <div class="text-sm font-semibold text-gray-900">靶面輸入</div>
                        <p class="text-xs text-gray-500">點擊或拖曳落點，自動填入分值。</p>
                    </div>
                    <div class="text-xs text-gray-500" id="active-cell-label"></div>
                </div>
                <div class="p-4 flex flex-col items-center gap-3">
                    <div class="relative w-full max-w-[440px] aspect-square" id="target-container">
                        <div class="absolute inset-0 target-face" aria-hidden="true"></div>
                        <canvas id="target-overlay" class="absolute inset-0"></canvas>
                        <div id="target-surface" class="absolute inset-0"></div>
                        <div id="target-zoom" class="target-zoom absolute pointer-events-none opacity-0 scale-95 transition duration-150 ease-out">
                            <div class="target-zoom-bg absolute inset-0 rounded-full" aria-hidden="true"></div>
                            <div class="absolute inset-0 flex items-center justify-center" aria-hidden="true">
                                <div class="target-zoom-center"></div>
                            </div>
                            <div class="absolute top-2 left-2 rounded-full bg-gray-900/85 px-3 py-1 text-xs font-semibold text-white shadow-sm" id="zoom-score"></div>
                        </div>
                    </div>
                    <div class="text-xs text-gray-500 text-center">
                        點擊標示落點；拖曳可精調位置。系統會自動判定 X / 10 / Miss 並填入當前箭位。
                    </div>
                    <div class="flex flex-wrap items-center justify-center gap-2 text-xs text-gray-700">
                        <span class="font-semibold text-gray-800">靶面：</span>
                        <div class="inline-flex rounded-full border border-gray-200 bg-gray-50 p-1 shadow-sm">
                            <button type="button" data-target-face="ten-ring" class="px-3 py-1 rounded-full text-xs font-medium">
                                十分靶
                            </button>
                            <button type="button" data-target-face="six-ring" class="px-3 py-1 rounded-full text-xs font-medium">
                                六分靶
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Table Card --}}
            <div class="rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                <div class="p-4 border-b border-gray-100">
                    <div id="meta-line" class="text-sm text-gray-600"></div>
                </div>

            <div class="max-h-[70vh] overflow-auto">
                <table class="min-w-full text-sm" id="score-table">
                    <thead class="bg-gray-50 text-[11px] uppercase text-gray-500 sticky top-0 z-10">
                    <tr id="thead-row">
                        <th class="px-3 py-2 text-left w-16">End</th>
                        {{-- 動態插入 A1~An、EndSum、Cumu --}}
                    </tr>
                    </thead>
                    <tbody id="tbody" class="divide-y divide-gray-100"></tbody>
                </table>
            </div>

{{--            <div class="p-4 border-t border-gray-100 flex flex-col sm:flex-row sm:items-center gap-2 sm:justify-between">--}}
{{--                <div class="text-sm text-gray-600">快捷鍵：<span class="font-mono">0–9（可連按成 10/11）</span>、<span class="font-mono">M</span>=Miss、<span class="font-mono">Enter/Space</span>=下一格、<span class="font-mono">Backspace</span>=清除、方向鍵移動。</div>--}}
{{--                <div class="text-sm">總分：<span id="total" class="font-semibold">0</span></div>--}}
{{--            </div>--}}

            <div class="p-4 border-t border-gray-100 flex flex-wrap items-center gap-2">
                <form id="export-form" method="POST" action="{{ route('scores.store') }}" class="flex items-center gap-2">
                    @csrf
                    <div class="flex flex-col gap-1">
                        <label for="note" class="text-xs font-medium text-gray-700">備註（可寫下心得或當下狀態）</label>
                        <textarea
                            id="note"
                            name="note"
                            rows="2"
                            maxlength="255"
                            class="w-64 rounded-xl border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="今天的狀態、風況、想記住的重點..."
                        ></textarea>
                    </div>
                    <input type="hidden" name="payload" id="payload" />
                    <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">結束計分</button>
                </form>
{{--                <button id="export-json" type="button" class="inline-flex items-center justify-center rounded-xl border px-3 py-2 text-sm hover:bg-gray-50">匯出 JSON</button>--}}
{{--                <button id="export-csv" type="button" class="inline-flex items-center justify-center rounded-xl border px-3 py-2 text-sm hover:bg-gray-50">匯出 CSV</button>--}}
            </div>
        </div>
    </div>

    {{-- ===== On-screen Numpad ===== --}}
    <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 py-8 pb-40 lg:pb-0">
    <div id="numpad"
         class="fixed inset-x-0 bottom-0 z-40 border-t border-gray-200 bg-white shadow-2xl
            sm:rounded-none sm:border-t
            lg:hidden  {{-- >=1024px 隱藏 --}}
            [padding-bottom:env(safe-area-inset-bottom)]">

        {{-- 行動裝置抓握列 + 收合按鈕 --}}
        <div class="sm:hidden flex items-center justify-between px-3 py-2">
            <div class="flex-1 flex items-center justify-center">
                <div class="h-1.5 w-10 rounded-full bg-gray-300"></div>
            </div>
            <button id="numpad-collapse"
                    type="button"
                    class="ml-3 rounded-lg border px-2 py-1 text-xs text-gray-600 hover:bg-gray-50"
                    aria-label="收合鍵盤"
                    title="收合鍵盤">
                收合
            </button>
        </div>
{{--        --}}{{-- 拖拉/標題列（行動裝置抓握用） --}}
{{--        <div class="sm:hidden flex items-center justify-center py-2">--}}
{{--            <div class="h-1.5 w-10 rounded-full bg-gray-300"></div>--}}
{{--        </div>--}}

        <div class="px-3 py-2 sm:p-4">
            {{-- 第一列：7 8 9 ⌫ --}}
            <div class="grid grid-cols-4 gap-2 mb-2">
                <button type="button" data-key="x"  class="nkey">X</button>
                <button type="button" data-key="10"  class="nkey">10</button>
                <button type="button" data-key="9"  class="nkey">9</button>
                <button type="button" data-key="BKSP" class="nkey nkey-muted">⌫</button>
            </div>

            {{-- 第二列：4 5 6 ← --}}
            <div class="grid grid-cols-4 gap-2 mb-2">
                <button type="button" data-key="8" class="nkey">8</button>
                <button type="button" data-key="7" class="nkey">7</button>
                <button type="button" data-key="6" class="nkey">6</button>
                <button type="button" data-key="PREV" class="nkey nkey-muted">←</button>
            </div>

            {{-- 第三列：1 2 3 → --}}
            <div class="grid grid-cols-4 gap-2 mb-2">
                <button type="button" data-key="5" class="nkey">5</button>
                <button type="button" data-key="4" class="nkey">4</button>
                <button type="button" data-key="3" class="nkey">3</button>
                <button type="button" data-key="NEXT" class="nkey nkey-accent">→</button>
            </div>

            {{-- 第四列：M 0 10 11 --}}
            <div class="grid grid-cols-4 gap-2">
                <button type="button" data-key="2"  class="nkey">2</button>
                <button type="button" data-key="1" class="nkey">1</button>
                <button type="button" data-key="M"  class="nkey nkey-miss">M</button>
{{--                <button type="button" data-key="M"  class="nkey nkey-miss">*</button>--}}
            </div>

            {{-- 功能列：清除／收起（可選） --}}
            <div class="mt-3 flex items-center justify-between">
{{--                <button type="button" data-key="CLR" class="rounded-xl border px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">清除此格</button>--}}
{{--                --}}{{-- 若想收合可加一顆切換鈕；預設不做收合 --}}
            </div>
        </div>
    </div>
    </div>
    {{-- 重新展開的浮動按鈕（只在鍵盤收合時出現） --}}
    <button id="numpad-reopen"
            type="button"
            class="fixed bottom-4 right-4 z-40 rounded-full bg-indigo-600 text-white shadow-lg px-4 py-3 text-sm lg:hidden hidden
               hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-300"
            aria-label="展開鍵盤"
            title="展開鍵盤">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <!-- 外框 -->
            <rect x="2.75" y="5.75" width="18.5" height="12.5" rx="2"></rect>
            <!-- 上排幾顆鍵 -->
            <path d="M6 10h2m2 0h2m2 0h2"></path>
            <!-- 空白鍵 -->
            <path d="M7 14h10"></path>
        </svg>
        <span class="sr-only">展開鍵盤</span>
    </button>
@endsection

@section('js')
    {{-- 簡易樣式（沿用 Tailwind） --}}
    <style>
        :root { --target-image: url('{{ $defaults['target_face'] === 'six-ring' ? '/images/target-6plus.svg' : '/images/target-122.svg' }}'); }
        .target-face {
            background: var(--target-image) center/contain no-repeat;
            background-color: #f8fafc;
            border-radius: 9999px;
            box-shadow: inset 0 0 0 2px #0f172a;
        }
        .target-zoom,
        .target-zoom-bg {
            background-image: var(--target-image);
            background-size: 260% 260%;
            background-repeat: no-repeat;
            background-position: center;
            background-color: #f8fafc;
        }
        .target-zoom {
            position: fixed;
            width: 140px;
            height: 140px;
            border-radius: 9999px;
            box-shadow: 0 20px 30px rgba(0,0,0,0.18);
            border: 3px solid rgba(255,255,255,0.85);
            transform-origin: center;
            z-index: 60;
        }
        .target-zoom-center {
            width: 16px;
            height: 16px;
            border-radius: 9999px;
            border: 3px solid rgba(34,197,94,0.95);
            background: rgba(34,197,94,0.35);
            box-shadow: 0 0 0 1px rgba(255,255,255,0.9);
        }
        #target-surface {
            cursor: crosshair;
            touch-action: none;
        }
        #numpad .nkey{
            @apply rounded-xl border px-4 py-3 text-base font-medium text-gray-900 bg-white hover:bg-gray-50 active:scale-95 transition;
        }
        #numpad .nkey-muted{
            @apply text-gray-600 border-gray-300;
        }
        #numpad .nkey-accent{
            @apply bg-indigo-600 text-white border-indigo-600 hover:bg-indigo-500;
        }
        #numpad .nkey-miss{
            @apply bg-rose-50 text-rose-700 border-rose-200 hover:bg-rose-100;
        }
    </style>


    <script>
        (function () {
            // ---- DOM（若沒有表單也能運作） ----
            const root = document.getElementById('score-root');
            const urlq = new URLSearchParams(location.search);

// 嘗試抓表單元素（如果你未來又加回表單，這些會生效）
            const bow     = document.getElementById('bow_type');
            const venue   = document.getElementById('venue');
            const distance= document.getElementById('distance');
            const total   = document.getElementById('arrows_total');
            const perEnd  = document.getElementById('arrows_per_end');

            const chips         = document.getElementById('chips-line');
            const totalPresets  = document.getElementById('total-presets');
            const targetFaceButtons = Array.from(document.querySelectorAll('[data-target-face]'));

            const theadRow = document.getElementById('thead-row');
            const tbody    = document.getElementById('tbody');
            const totalSpan= document.getElementById('total');
            const metaLine = document.getElementById('meta-line');
            const payload  = document.getElementById('payload');

            const btnReset      = document.getElementById('reset-grid');
            const btnExportJSON = document.getElementById('export-json');
            const btnExportCSV  = document.getElementById('export-csv');
            const btnBuild      = document.getElementById('build-grid'); // 備援按鈕

// 沒有表單時用 data-* 或 query string 當資料來源
            const FALLBACKS = {
                bow   : (root?.dataset.bow)           || urlq.get('bow_type')       || 'recurve',
                venue : (root?.dataset.venue)         || urlq.get('venue')          || 'outdoor',
                dist  : parseInt((root?.dataset.distance)     || urlq.get('distance')       || '18', 10),
                total : parseInt((root?.dataset.arrowsTotal)  || urlq.get('arrows_total')   || '36', 10),
                per   : parseInt((root?.dataset.arrowsPerEnd) || urlq.get('arrows_per_end') || '6',  10),
                targetFace: (root?.dataset.targetFace)       || urlq.get('target_face')    || 'ten-ring',
            };

// 建立「表單元素的墊片」，讓後續程式可以用 .value
            const bowEl     = bow     || { value: FALLBACKS.bow };
            const venueEl   = venue   || { value: FALLBACKS.venue };
            const distanceEl= distance|| { value: String(FALLBACKS.dist) };
            const totalEl   = total   || { value: String(FALLBACKS.total) };
            const perEndEl  = perEnd  || { value: String(FALLBACKS.per) };

// 顯示文字對照（因為沒有 <option> 時拿不到 .text）
            const BOW_TEXT = {
                recurve: 'Recurve（反曲）',
                compound: 'Compound（複合）',
                barebow: 'Barebow（裸弓）',
                yumi: 'Yumi（和弓）',
                longbow: 'Longbow',
            };
            const VENUE_TEXT = { indoor: '室內', outdoor: '室外' };
            const TARGET_FACE_TEXT = { 'ten-ring': '十分靶', 'six-ring': '六分靶' };

            const TARGET_CONFIGS = {
                'ten-ring': {
                    image: "url('/images/target-122.svg')",
                    rings: [
                        { r: 0.05, score: 10, isX: true },
                        { r: 0.10, score: 10, isX: false },
                        { r: 0.20, score: 9,  isX: false },
                        { r: 0.30, score: 8,  isX: false },
                        { r: 0.40, score: 7,  isX: false },
                        { r: 0.50, score: 6,  isX: false },
                        { r: 0.60, score: 5,  isX: false },
                        { r: 0.70, score: 4,  isX: false },
                        { r: 0.80, score: 3,  isX: false },
                        { r: 0.90, score: 2,  isX: false },
                        { r: 1.00, score: 1,  isX: false },
                    ],
                },
                'six-ring': {
                    image: "url('/images/target-6plus.svg')",
                    rings: [
                        { r: 0.10, score: 10, isX: true },
                        { r: 0.20, score: 10, isX: false },
                        { r: 0.40, score: 9,  isX: false },
                        { r: 0.60, score: 8,  isX: false },
                        { r: 0.80, score: 7,  isX: false },
                        { r: 1.00, score: 6,  isX: false },
                    ],
                },
            };

// 如果沒有 thead/tbody 就沒辦法生成
            if (!theadRow || !tbody) return;

            const ACTIVE = ['bg-gray-900', 'text-white', 'border-gray-900'];
            const INACTIVE = ['bg-white', 'text-gray-700', 'border'];
            const $ = (id) => document.getElementById(id);
            const clamp = (n, min, max) => Math.max(min, Math.min(max, n));

            function ready(fn) {
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', fn, { once: true });
                } else fn();
            }

            ready(() => {
                // ---- 狀態 ----
                let scores = [];   // number|null
                let isMiss = [];   // boolean
                let isX = [];
                let ends = 0;
                let active = { end: 0, idx: 0 };
                let coords = [];
                let pendingDigit = null;
                let pendingTimer = null;
                let overlayCtx = null;
                let targetRect = null;
                let isPointerDown = false;
                let lastPointerCommitTs = 0;
                let targetFace = TARGET_CONFIGS[FALLBACKS.targetFace] ? FALLBACKS.targetFace : 'ten-ring';

                // ========== 表單 part（與 form-only 相同） ==========
                function setActive(groupBtns, targetBtn) {
                    groupBtns.forEach((btn) => {
                        btn.classList.remove(...ACTIVE);
                        INACTIVE.forEach((c) => !btn.classList.contains(c) && btn.classList.add(c));
                        btn.setAttribute('aria-pressed', 'false');
                    });
                    if (targetBtn) {
                        targetBtn.classList.remove(...INACTIVE);
                        targetBtn.classList.add(...ACTIVE);
                        targetBtn.setAttribute('aria-pressed', 'true');
                    }
                }

                function renderChips() {
                    if (!chips) return;
                    const items = [
                        `弓種：${BOW_TEXT[bowEl.value] || bowEl.value}`,
                        `場地：${VENUE_TEXT[venueEl.value] || venueEl.value}`,
                        `距離：${distanceEl.value}m`,
                        `總箭數：${totalEl.value}`,
                        `每趟：${perEndEl.value}`,
                        `靶面：${TARGET_FACE_TEXT[targetFace] || targetFace}`,
                    ];
                    chips.classList.remove('hidden');
                    chips.innerHTML = items
                        .map((t) => `<span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-xs text-gray-700">${t}</span>`)
                        .join(' ');
                }

                function renderMetaLine() {
                    if (!metaLine) return;
                    const totalArrows = parseInt(totalEl.value || '0', 10);
                    const per = parseInt(perEndEl.value || '0', 10);
                    metaLine.textContent =
                        `弓種：${BOW_TEXT[bowEl.value] || bowEl.value}　` +
                        `場地：${VENUE_TEXT[venueEl.value] || venueEl.value}　` +
                        `距離：${distanceEl.value}m　` +
                        `靶面：${TARGET_FACE_TEXT[targetFace] || targetFace}　` +
                        `總箭數：${totalArrows}　` +
                        `每趟：${per}`;
                }

                function renderTotalPresetButtons() {
                    if (!totalPresets) return;
                    const presets = venue.value === 'indoor' ? [30, 60] : [36, 72];
                    totalPresets.innerHTML = presets
                        .map((n) => `<button type="button" data-total="${n}" class="px-3 py-1 rounded-xl ${INACTIVE.join(' ')} text-xs sm:text-sm" aria-pressed="false">${n}</button>`)
                        .join('');
                    const curr = parseInt(total.value || '0', 10);
                    if (!presets.includes(curr)) total.value = String(presets[0]);
                    const btns = Array.from(totalPresets.querySelectorAll('[data-total]'));
                    const match = btns.find((b) => Number(b.dataset.total) === Number(total.value)) || btns[0];
                    setActive(btns, match);
                }

                function updateActiveStates() {
                    const distBtns = Array.from(document.querySelectorAll('[data-distance]'));
                    setActive(distBtns, distBtns.find((b) => Number(b.dataset.distance) === Number(distance.value)) || null);

                    const totalBtns = Array.from(document.querySelectorAll('#total-presets [data-total]'));
                    setActive(totalBtns, totalBtns.find((b) => Number(b.dataset.total) === Number(total.value)) || null);

                    const perEndBtns = Array.from(document.querySelectorAll('[data-per-end]'));
                    setActive(perEndBtns, perEndBtns.find((b) => Number(b.dataset.perEnd) === Number(perEnd.value)) || null);
                }

                function updateTargetFaceStates() {
                    if (!targetFaceButtons.length) return;
                    setActive(targetFaceButtons, targetFaceButtons.find((b) => b.dataset.targetFace === targetFace) || null);
                }

                // [bow, distance, total, perEnd].forEach((el) => {
                //     el.addEventListener('input', () => { updateActiveStates(); renderChips(); buildGrid(); });
                //     el.addEventListener('change', () => { updateActiveStates(); renderChips(); buildGrid(); });
                // });

                targetFaceButtons.forEach((btn) => {
                    btn.addEventListener('click', () => {
                        applyTargetFace(btn.dataset.targetFace || 'ten-ring');
                        updateGrid();
                    });
                });

                // ========== 計分表 part ==========
                function buildGrid() {
                    const totalArrows = clamp(parseInt(totalEl.value || '0', 10), 1, 300);
                    const per = clamp(parseInt(perEndEl.value || '0', 10), 1, 12);
                    totalEl.value  = String(totalArrows);
                    perEndEl.value = String(per);

                    ends = Math.ceil(totalArrows / per);
                    scores = Array.from({ length: ends }, () => Array(per).fill(null));
                    isMiss = Array.from({ length: ends }, () => Array(per).fill(false));
                    isX    = Array.from({ length: ends }, () => Array(per).fill(false));
                    coords = Array.from({ length: ends }, () => Array(per).fill(null));
                    active = { end: 0, idx: 0 };

                    if (pendingTimer) { clearTimeout(pendingTimer); pendingTimer = null; }
                    pendingDigit = null;

                    // Meta 行
                    renderMetaLine();

                    // thead
                    theadRow.innerHTML =
                        '<th class="px-3 py-2 text-left w-16">End</th>' +
                        Array.from({ length: per }, (_, i) => `<th class="px-3 py-2 text-center w-14 sm:w-16">A${i + 1}</th>`).join('') +
                        '<th class="px-2 sm:px-3 py-2 text-right w-20 sm:w-24">End 合計</th>' +
                        '<th class="px-2 sm:px-3 py-2 text-right w-20 sm:w-24">累計</th>';

                    // tbody
                    let rows = '';
                    for (let e = 0; e < ends; e++) {
                        const zebra = e % 2 ? 'bg-white' : 'bg-gray-50/50';
                        rows += `<tr class="${zebra}">` +
                            `<td class="px-3 sm:px-4 py-2 font-medium">${e + 1}</td>` +
                            Array.from({ length: per }, (_, i) =>
                                `<td class="p-0">` +
                                `<button type="button" class="w-full min-h-10 sm:min-h-0 px-2 sm:px-4 py-2 text-center focus:outline-none touch-manipulation" data-cell="${e}-${i}" title="點選以從此格開始連續輸入"></button>` +
                                `</td>`
                            ).join('') +
                            `<td class="px-2 sm:px-4 py-2 text-right font-medium" data-end-sum="${e}">0</td>` +
                            `<td class="px-2 sm:px-4 py-2 text-right font-semibold" data-cumu="${e}">0</td>` +
                            `</tr>`;
                    }
                    tbody.innerHTML = rows;

                    updateGrid();
                    focusActive();
                }
                function setActiveCell(eIdx, iIdx, options = {}) {
                    active = { end: clamp(eIdx, 0, ends - 1), idx: clamp(iIdx, 0, scores[0].length - 1) };
                    if (pendingTimer) { clearTimeout(pendingTimer); pendingTimer = null; }
                    pendingDigit = null;
                    focusActive(options);
                }

                function focusActive(options = {}) {
                    const { suppressScroll = false } = options;
                    tbody.querySelectorAll('[data-cell]').forEach((btn) => btn.classList.remove('bg-yellow-100'));
                    const btn = tbody.querySelector(`[data-cell="${active.end}-${active.idx}"]`);
                    const label = document.getElementById('active-cell-label');
                    if (label) label.textContent = `End ${active.end + 1} / Arrow ${active.idx + 1}`;
                    btn?.classList.add('bg-yellow-100');
                    if (!suppressScroll) btn?.scrollIntoView({ block: 'nearest', inline: 'nearest' });
                    btn?.focus({ preventScroll: true });
                    renderTarget();
                }

                function normalizeScore(val, miss = false, x = false) {
                    const key = TARGET_CONFIGS[targetFace] ? targetFace : 'ten-ring';
                    let nVal = val ?? null;
                    let nMiss = !!miss;
                    let nX = !!x;

                    if (!Number.isNaN(parseInt(nVal, 10))) {
                        nVal = clamp(parseInt(nVal, 10), 0, 11);
                    }

                    if (nX) nVal = 10;

                    if (key === 'six-ring' && !nMiss) {
                        if (nVal !== null && nVal < 6) {
                            nMiss = true;
                            nX = false;
                            nVal = 0;
                        }
                    }

                    return { val: nVal, miss: nMiss, x: nX };
                }

                function commitScore(val, miss = false, x = false, point = null, options = {}) {
                    const normalized = normalizeScore(val, miss, x);
                    scores[active.end][active.idx] = normalized.val;
                    isMiss[active.end][active.idx] = normalized.miss;
                    isX[active.end][active.idx]    = normalized.x;
                    coords[active.end][active.idx] = point ? { x: point.x, y: point.y } : null;
                    moveNext(options);
                    updateGrid();
                }

                function moveNext(options = {}) {
                    const per = scores[0].length;
                    let e = active.end, i = active.idx + 1;
                    if (i >= per) { i = 0; e++; }
                    if (e >= ends) { e = ends - 1; i = per - 1; }
                    active = { end: e, idx: i };
                    focusActive(options);
                }

                function movePrev(options = {}) {
                    const per = scores[0].length;
                    let e = active.end, i = active.idx - 1;
                    if (i < 0) { e = Math.max(0, e - 1); i = per - 1; }
                    active = { end: e, idx: i };
                    focusActive(options);
                }

                function clearCell() {
                    scores[active.end][active.idx] = null;
                    isMiss[active.end][active.idx] = false;
                    isX[active.end][active.idx]    = false;
                    coords[active.end][active.idx] = null;
                    updateGrid();
                }

                function updateGrid() {
                    if (!scores.length) return;
                    const per = scores[0].length;
                    let cumu = 0;
                    for (let e = 0; e < ends; e++) {
                        let endSum = 0;
                        for (let i = 0; i < per; i++) {
                            const val = scores[e][i];
                            const miss = isMiss[e][i];
                            const xHit = isX[e][i];

                            const btn = tbody.querySelector(`[data-cell="${e}-${i}"]`);

                            let text = '';
                            if (val === null) {
                                text = '';
                            } else if (xHit) {
                                text = 'X';         // 顯示 X
                            } else if (miss && val === 0) {
                                text = 'M';
                            } else {
                                text = String(val);
                            }
                            if (btn) btn.textContent = text;

                            endSum += (val ?? 0);
                        }
                        cumu += endSum;
                        const sumCell = tbody.querySelector(`[data-end-sum="${e}"]`);
                        const cumCell = tbody.querySelector(`[data-cumu="${e}"]`);
                        if (sumCell) sumCell.textContent = endSum;
                        if (cumCell) cumCell.textContent = cumu;
                    }
                    if (totalSpan) totalSpan.textContent = String(cumu);

                    if (payload) {
                            payload.value = JSON.stringify({
                                meta: {
                                    bow: bowEl.value,
                                    venue: venueEl.value,
                                    distance: parseInt(distanceEl.value || '0', 10),
                                    arrows_total: parseInt(totalEl.value || '0', 10),
                                    arrows_per_end: parseInt(perEndEl.value || '0', 10),
                                    target_face: targetFace,
                                },
                                scores,
                                isMiss,
                            isX,
                            coords,
                            createdAt: new Date().toISOString(),
                        });
                    }

                    renderTarget();
                }
                // === Numpad（僅手機/平板） ===
                (function attachNumpadMobileOnly(){
                    const numpad = document.getElementById('numpad');
                    const reopenBtn = document.getElementById('numpad-reopen');
                    const collapseBtn = document.getElementById('numpad-collapse');
                    if (!numpad) return;

                    const isMobilePad = () =>
                        window.matchMedia('(pointer: coarse)').matches ||
                        window.matchMedia('(max-width: 1023.98px)').matches;

                    let bound = false;
                    let isOpen = (localStorage.getItem('numpad_open') ?? '1') === '1'; // ← 新：讀取記憶 // 目前是否展開

                    // 讓頁面底部留空，避免鍵盤遮住內容
                    function applyBottomInset() {
                        if (!isOpen) { document.body.style.paddingBottom = ''; return; }
                        // 取鍵盤高度 + 裝置安全區
                        const h = numpad.getBoundingClientRect().height;
                        document.body.style.paddingBottom = `${Math.ceil(h)}px`;
                    }

                    function openPad() {
                        isOpen = true;
                        localStorage.setItem('numpad_open','1');
                        numpad.classList.remove('translate-y-full', 'pointer-events-none');
                        reopenBtn?.classList.add('hidden');
                        applyBottomInset();
                    }
                    function closePad() {
                        isOpen = false
                        localStorage.setItem('numpad_open','0');
                        numpad.classList.add('translate-y-full', 'pointer-events-none');
                        reopenBtn?.classList.remove('hidden');
                        applyBottomInset();
                    }

                    function flash(btn){
                        btn.classList.add('ring-2','ring-indigo-200');
                        setTimeout(()=>btn.classList.remove('ring-2','ring-indigo-200'),120);
                    }
                    function pressKey(key){
                        switch(key){
                            case 'BKSP': clearCell(); break;
                            case 'PREV': movePrev(); break;
                            case 'NEXT': moveNext(); break;
                            case 'M':    commitScore(0, true); break;
                            case 'CLR':  clearCell(); break;
                            case 'x':
                            case 'X':    commitScore(10, false, true); break; // 你已加的 X=10 顯示 X
                            case '0': case '1': case '2': case '3': case '4':
                            case '5': case '6': case '7': case '8': case '9':
                            case '10': case '11':
                                commitScore(parseInt(key,10), false);
                                break;
                            default: break;
                        }
                    }

                    function onClick(e){
                        const btn = e.target.closest('[data-key]');
                        if(!btn) return;
                        e.preventDefault();
                        flash(btn);
                        pressKey(String(btn.dataset.key));
                    }
                    function onTouchStart(e){
                        const btn = e.target.closest('[data-key]');
                        if(btn){ btn.style.transform='scale(0.98)'; }
                    }
                    function onTouchEnd(e){
                        const btn = e.target.closest('[data-key]');
                        if(btn){ btn.style.transform=''; }
                    }

                    function bind(){
                        if (bound) return;
                        numpad.addEventListener('click', onClick);
                        numpad.addEventListener('touchstart', onTouchStart, {passive:true});
                        numpad.addEventListener('touchend', onTouchEnd, {passive:true});
                        reopenBtn?.addEventListener('click', openPad);
                        collapseBtn?.addEventListener('click', closePad);
                        bound = true;
                    }
                    function unbind(){
                        if (!bound) return;
                        numpad.removeEventListener('click', onClick);
                        numpad.removeEventListener('touchstart', onTouchStart);
                        numpad.removeEventListener('touchend', onTouchEnd);
                        reopenBtn?.removeEventListener('click', openPad);
                        collapseBtn?.removeEventListener('click', closePad);
                        bound = false;
                    }

                    function refresh(){
                        const mobile = isMobilePad();

                        if (mobile){
                            numpad.classList.remove('hidden');
                            bind();

                            // 依現有狀態呈現，**不**改變 isOpen
                            if (isOpen){
                                numpad.classList.remove('translate-y-full','pointer-events-none');
                                reopenBtn?.classList.add('hidden');
                                applyBottomInset();
                            } else {
                                numpad.classList.add('translate-y-full','pointer-events-none');
                                reopenBtn?.classList.remove('hidden');
                                document.body.style.paddingBottom = '';
                            }
                        } else {
                            numpad.classList.add('hidden');
                            reopenBtn?.classList.add('hidden');
                            unbind();
                            document.body.style.paddingBottom = '';
                        }
                    }

                    // 初始 & 之後視窗改變
                    refresh();
                    // 視窗變動（含行動瀏覽器地址列伸縮）只重算樣式，不改 isOpen
                    window.addEventListener('resize', () => {
                        refresh();
                        if (isOpen && isMobilePad()) applyBottomInset();
                    });

                    // 如果內容滾動到最底，展開時也更新一次底部間距
                    window.addEventListener('scroll', () => {
                        if (isOpen && isMobilePad()) applyBottomInset();
                    }, { passive: true });
                })();
                // 點任一 cell 開始連打
                document.addEventListener('click', (e) => {
                    const cell = e.target.closest('[data-cell]');
                    if (!cell) return;
                    const [eIdx, iIdx] = cell.dataset.cell.split('-').map(Number);
                    setActiveCell(eIdx, iIdx);
                });

                // 依照落點自動計分（依目前靶面設定）
                function calcScoreFromPoint(x, y) {
                    const dist = Math.sqrt(x * x + y * y);
                    const EPS = 0.003; // 提高邊界容忍度，落在線上給高分
                    const config = TARGET_CONFIGS[targetFace] || TARGET_CONFIGS['ten-ring'];
                    const rings = config.rings;
                    const outer = rings[rings.length - 1]?.r || 1;

                    if (dist > outer + EPS) return { score: 0, isMissFlag: true, isXFlag: false };

                    for (const ring of rings) {
                        if (dist <= ring.r + EPS) {
                            return { score: ring.score, isMissFlag: false, isXFlag: ring.isX };
                        }
                    }

                    return { score: 0, isMissFlag: true, isXFlag: false };
                }

                function renderTarget() {
                    const canvas = document.getElementById('target-overlay');
                    if (!canvas) return;
                    if (!overlayCtx) overlayCtx = canvas.getContext('2d');
                    if (!targetRect) {
                        const box = document.getElementById('target-container');
                        if (!box) return;
                        const rect = box.getBoundingClientRect();
                        canvas.width = rect.width * devicePixelRatio;
                        canvas.height = rect.height * devicePixelRatio;
                        canvas.style.width = `${rect.width}px`;
                        canvas.style.height = `${rect.height}px`;
                        targetRect = rect;
                    }
                    overlayCtx.clearRect(0, 0, canvas.width, canvas.height);
                    const halfW = canvas.width / 2;
                    const halfH = canvas.height / 2;
                    const drawPoint = (pt, color, size = 8, outline = false) => {
                        if (!pt) return;
                        const cx = halfW + pt.x * halfW;
                        const cy = halfH + pt.y * halfH;
                        overlayCtx.beginPath();
                        overlayCtx.fillStyle = color;
                        overlayCtx.arc(cx, cy, size * devicePixelRatio, 0, Math.PI * 2);
                        overlayCtx.fill();
                        if (outline) {
                            overlayCtx.lineWidth = 2 * devicePixelRatio;
                            overlayCtx.strokeStyle = 'rgba(255,255,255,0.9)';
                            overlayCtx.stroke();
                        }
                    };

                    coords.forEach((row, eIdx) => {
                        row.forEach((pt, iIdx) => {
                            if (!pt) return;
                            const isActive = eIdx === active.end && iIdx === active.idx;
                            // 使用與靶面色環不同的顏色，以確保落點清晰可辨
                            const pointColor = isActive ? 'rgba(124,58,237,0.9)' : 'rgba(34,197,94,0.85)';
                            drawPoint(pt, pointColor, isActive ? 10 : 7, isActive);
                        });
                    });
                }

                const zoomEl = document.getElementById('target-zoom');
                const zoomScoreEl = document.getElementById('zoom-score');
                let targetImage = getComputedStyle(document.documentElement).getPropertyValue('--target-image').trim();
                let zoomTimer = null;

                function hideZoom(after = 0) {
                    if (!zoomEl) return;
                    if (zoomTimer) { clearTimeout(zoomTimer); zoomTimer = null; }
                    if (after) {
                        zoomTimer = setTimeout(() => hideZoom(0), after);
                        return;
                    }
                    zoomEl.classList.add('opacity-0', 'scale-95');
                }

                function showZoom(nx, ny, label, persist = false, opts = {}) {
                    if (!zoomEl || !zoomScoreEl) return;
                    const container = document.getElementById('target-container');
                    const rect = container?.getBoundingClientRect();
                    if (!rect) return;

                    const lensWidth = zoomEl.offsetWidth || zoomEl.getBoundingClientRect().width;
                    const lensHeight = zoomEl.offsetHeight || zoomEl.getBoundingClientRect().height;

                    // 以實際落點為放大中心，畫面同步靠像素對齊；鏡面本身仍上移避免被手指遮住
                    const pointPx = opts.pointOverridePx || {
                        x: rect.width * (0.5 + nx / 2),
                        y: rect.height * (0.5 + ny / 2),
                    };
                    const clientX = opts.clientPos?.x ?? rect.left + pointPx.x;
                    const clientY = opts.clientPos?.y ?? rect.top + pointPx.y;
                    const zoomFactor = 2.6; // 與背景 260% 等效，但用像素定位以校準中心
                    const bgW = rect.width * zoomFactor;
                    const bgH = rect.height * zoomFactor;
                    const bgPosX = (lensWidth / 2) - pointPx.x * zoomFactor;
                    const bgPosY = (lensHeight / 2) - pointPx.y * zoomFactor;

                    const lensOffsetY = lensHeight * 0.8; // keep lens offset from the finger
                    const viewportW = window.innerWidth;
                    const viewportH = window.innerHeight;
                    const lensX = clamp(clientX, lensWidth / 2 + 4, viewportW - lensWidth / 2 - 4);

                    const minLensY = lensHeight / 2 + 4;
                    const maxLensY = viewportH - lensHeight / 2 - 4;
                    const desiredAbove = clientY - lensOffsetY;
                    const wouldHitTop = desiredAbove - lensHeight / 2 <= 0;
                    const lensY = wouldHitTop
                        ? clamp(clientY + lensOffsetY, minLensY, maxLensY)
                        : clamp(desiredAbove, minLensY, maxLensY);

                    zoomEl.style.left = `${lensX}px`;
                    zoomEl.style.top = `${lensY}px`;
                    zoomEl.style.transform = 'translate(-50%, -50%)';
                    zoomEl.style.backgroundSize = `${bgW}px ${bgH}px`;
                    zoomEl.style.backgroundPosition = `${bgPosX}px ${bgPosY}px`;
                    if (targetImage) zoomEl.style.backgroundImage = targetImage;
                    const zoomBg = zoomEl.querySelector('.target-zoom-bg');
                    if (zoomBg) {
                        zoomBg.style.backgroundSize = `${bgW}px ${bgH}px`;
                        zoomBg.style.backgroundPosition = `${bgPosX}px ${bgPosY}px`;
                        if (targetImage) zoomBg.style.backgroundImage = targetImage;
                    }
                    zoomScoreEl.textContent = label;
                    zoomEl.classList.remove('opacity-0', 'scale-95');
                    if (persist) hideZoom(1100);
                }

                function applyTargetFace(face) {
                    const key = TARGET_CONFIGS[face] ? face : 'ten-ring';
                    targetFace = key;
                    const image = TARGET_CONFIGS[key].image;
                    document.documentElement.style.setProperty('--target-image', image);
                    targetImage = image;
                    const zoomBg = zoomEl?.querySelector('.target-zoom-bg');
                    if (zoomEl && targetImage) zoomEl.style.backgroundImage = targetImage;
                    if (zoomBg && targetImage) zoomBg.style.backgroundImage = targetImage;
                    targetRect = null;
                    updateTargetFaceStates();
                    renderChips();
                    renderMetaLine();
                    renderTarget();
                }

                function handlePointer(evt, commit = false) {
                    const surface = document.getElementById('target-surface');
                    if (!surface) return;
                    const rect = surface.getBoundingClientRect();
                    targetRect = rect;
                    const nx = ((evt.clientX - rect.left) / rect.width - 0.5) * 2;
                    const ny = ((evt.clientY - rect.top) / rect.height - 0.5) * 2;
                    const pointPx = {
                        x: (evt.clientX - rect.left),
                        y: (evt.clientY - rect.top),
                    };
                    const { score, isMissFlag, isXFlag } = calcScoreFromPoint(nx, ny);
                    const label = isMissFlag ? 'M' : (isXFlag ? 'X' : score);
                    if (commit) {
                        commitScore(score, isMissFlag, isXFlag, { x: nx, y: ny }, { suppressScroll: true });
                        showZoom(nx, ny, label, true, { pointOverridePx: pointPx, clientPos: { x: evt.clientX, y: evt.clientY } });
                    } else {
                        // 即時預覽目前落點
                        const canvas = document.getElementById('target-overlay');
                        if (!canvas) return;
                        if (!overlayCtx) overlayCtx = canvas.getContext('2d');
                        renderTarget();
                        const halfW = canvas.width / 2;
                        const halfH = canvas.height / 2;
                        overlayCtx.beginPath();
                        overlayCtx.setLineDash([6 * devicePixelRatio, 6 * devicePixelRatio]);
                        overlayCtx.lineWidth = 2 * devicePixelRatio;
                        overlayCtx.strokeStyle = 'rgba(17,24,39,0.45)';
                        overlayCtx.arc(halfW + nx * halfW, halfH + ny * halfH, 14 * devicePixelRatio, 0, Math.PI * 2);
                        overlayCtx.stroke();
                        overlayCtx.setLineDash([]);
                        showZoom(nx, ny, label, false, { pointOverridePx: pointPx, clientPos: { x: evt.clientX, y: evt.clientY } });
                    }
                }

                (function bindTargetSurface() {
                    const surface = document.getElementById('target-surface');
                    if (!surface) return;

                    surface.addEventListener('pointerdown', (e) => {
                        isPointerDown = true;
                        surface.setPointerCapture(e.pointerId);
                        // 先預覽落點，不立即計分，避免點擊觸發兩次
                        handlePointer(e, false);
                    });
                    surface.addEventListener('pointermove', (e) => {
                        if (!isPointerDown) return;
                        handlePointer(e, false);
                    });
                    surface.addEventListener('pointerup', (e) => {
                        if (!isPointerDown) return;
                        isPointerDown = false;
                        surface.releasePointerCapture(e.pointerId);
                        handlePointer(e, true);
                        lastPointerCommitTs = performance.now();
                    });
                    surface.addEventListener('pointerleave', () => {
                        isPointerDown = false;
                        renderTarget();
                        hideZoom();
                    });
                    surface.addEventListener('pointercancel', () => {
                        isPointerDown = false;
                        hideZoom();
                    });

                    // 觸控裝置的保險機制：若未觸發 pointerup，也能靠 click 提交一次
                    surface.addEventListener('click', (e) => {
                        if (performance.now() - lastPointerCommitTs < 220) return;
                        handlePointer(e, true);
                    });

                    const resizeObserver = new ResizeObserver(() => {
                        targetRect = null;
                        renderTarget();
                    });
                    resizeObserver.observe(surface);
                })();

                // 鍵盤
                function clearPending() { pendingDigit = null; if (pendingTimer) { clearTimeout(pendingTimer); pendingTimer = null; } }
                function schedulePending() { clearPending(); pendingTimer = setTimeout(() => { pendingDigit = null; pendingTimer = null; }, 900); }

                document.addEventListener('keydown', (e) => {
                    const tag = (e.target.tagName || '').toLowerCase();
                    if (['input', 'select', 'textarea'].includes(tag)) return;
                    if (!scores.length) return;

                    if (e.key >= '0' && e.key <= '9') {
                        e.preventDefault();
                        if (pendingDigit === null) {
                            pendingDigit = e.key;
                            if (e.key === '0') { clearPending(); commitScore(0, false); }
                            else schedulePending();
                        } else {
                            const num = parseInt(pendingDigit + e.key, 10);
                            clearPending();
                            if (num >= 0 && num <= 11) commitScore(num, false);
                            else commitScore(parseInt(e.key, 10), false);
                        }
                    } else if (e.key === 'm' || e.key === 'M') {
                        e.preventDefault(); clearPending(); commitScore(0, true);
                    }else if (e.key === 'x' || e.key === 'X') {
                        e.preventDefault(); clearPending(); commitScore(10, false, true);
                    } else if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault(); clearPending(); moveNext();
                    } else if (e.key === 'Backspace') {
                        e.preventDefault(); clearPending(); clearCell();
                    } else if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
                        e.preventDefault(); moveNext();
                    } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
                        e.preventDefault(); movePrev();
                    }
                });

                // ---- Init ----
                updateTargetFaceStates();
                renderChips();
                buildGrid(); // 預設進頁就建表
            });
        })();



    </script>


@endsection

{{-- 用 Vite 載入對應的 JS 模組 --}}
{{--@push('scripts')--}}
{{--    @vite('resources/js/pages/scores-index.js')--}}
{{--@endpush--}}
