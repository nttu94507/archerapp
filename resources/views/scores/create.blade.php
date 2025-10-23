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
        ];
    @endphp

    {{-- 根節點，提供 JS 讀取的初始值 --}}
    <div id="score-root"
         data-bow="{{ $defaults['bow_type'] }}"
         data-venue="{{ $defaults['venue'] }}"
         data-distance="{{ $defaults['distance'] }}"
         data-arrows-total="{{ $defaults['arrows_total'] }}"
         data-arrows-per-end="{{ $defaults['arrows_per_end'] }}">
    </div>

    <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 py-8 pb-40 lg:pb-0">
        {{-- Page Header --}}
        <div class="mb-4 flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-gray-900">訓練計分</h1>
                <p class="text-sm text-gray-500 mt-1">選好設定後立即產生表格；支援鍵盤快速輸入（0–10、X、M）、自動跳格、點任一格續打。</p>
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
                <button type="button" data-key="0" class="nkey">0</button>
                <button type="button" data-key="M"  class="nkey nkey-miss">M</button>
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
                let pendingDigit = null;
                let pendingTimer = null;

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
                    ];
                    chips.classList.remove('hidden');
                    chips.innerHTML = items
                        .map((t) => `<span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-xs text-gray-700">${t}</span>`)
                        .join(' ');
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

                // [bow, distance, total, perEnd].forEach((el) => {
                //     el.addEventListener('input', () => { updateActiveStates(); renderChips(); buildGrid(); });
                //     el.addEventListener('change', () => { updateActiveStates(); renderChips(); buildGrid(); });
                // });

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
                    active = { end: 0, idx: 0 };

                    if (pendingTimer) { clearTimeout(pendingTimer); pendingTimer = null; }
                    pendingDigit = null;

                    // Meta 行
                    if (metaLine) {
                        metaLine.textContent =
                            `弓種：${BOW_TEXT[bowEl.value] || bowEl.value}　` +
                            `場地：${VENUE_TEXT[venueEl.value] || venueEl.value}　` +
                            `距離：${distanceEl.value}m　` +
                            `總箭數：${totalArrows}　` +
                            `每趟：${per}`;
                    }

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
                function setActiveCell(eIdx, iIdx) {
                    active = { end: clamp(eIdx, 0, ends - 1), idx: clamp(iIdx, 0, scores[0].length - 1) };
                    if (pendingTimer) { clearTimeout(pendingTimer); pendingTimer = null; }
                    pendingDigit = null;
                    focusActive();
                }

                function focusActive() {
                    tbody.querySelectorAll('[data-cell]').forEach((btn) => btn.classList.remove('bg-yellow-100'));
                    const btn = tbody.querySelector(`[data-cell="${active.end}-${active.idx}"]`);
                    btn?.classList.add('bg-yellow-100');
                    btn?.scrollIntoView({ block: 'nearest', inline: 'nearest' });
                    btn?.focus({ preventScroll: true });
                }

                function commitScore(val, miss = false,x = false) {
                    scores[active.end][active.idx] = val;
                    isMiss[active.end][active.idx] = !!miss;
                    isX[active.end][active.idx]    = !!x;
                    moveNext();
                    updateGrid();
                }

                function moveNext() {
                    const per = scores[0].length;
                    let e = active.end, i = active.idx + 1;
                    if (i >= per) { i = 0; e++; }
                    if (e >= ends) { e = ends - 1; i = per - 1; }
                    active = { end: e, idx: i };
                    focusActive();
                }

                function movePrev() {
                    const per = scores[0].length;
                    let e = active.end, i = active.idx - 1;
                    if (i < 0) { e = Math.max(0, e - 1); i = per - 1; }
                    active = { end: e, idx: i };
                    focusActive();
                }

                function clearCell() {
                    scores[active.end][active.idx] = null;
                    isMiss[active.end][active.idx] = false;
                    isX[active.end][active.idx]    = false;
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
                            },
                            scores,
                            isMiss,
                            isX,
                            createdAt: new Date().toISOString(),
                        });
                    }
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
