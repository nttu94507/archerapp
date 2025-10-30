{{-- resources/views/scores/setup.blade.php --}}
@extends('layouts.app')

@section('title', 'ArrowTrack — 排名輸入')

@section('content')
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">
        {{-- Header --}}
        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">即時排名輸入</h1>
                <p class="mt-1 text-sm text-gray-600">規則：先比 <span class="font-medium">Score</span>（高到低），同分再比 <span class="font-medium">X</span>（高到低）。</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button id="btn-load-sample" type="button" class="rounded-xl border px-4 py-2 text-sm hover:bg-gray-50">載入範例</button>
{{--                <button id="btn-clear-all" type="button" class="rounded-xl border px-4 py-2 text-sm hover:bg-gray-50">清空</button>--}}
                <button id="btn-download-csv" type="button" class="rounded-xl bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800">下載 CSV</button>
            </div>
        </div>

        {{-- Input Card --}}
        <div class="rounded-2xl border p-4">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-semibold">輸入選手</h2>
                <div class="text-xs text-gray-500">支援逐列輸入與快速貼上</div>
            </div>

            {{-- 桌機：表格模式 --}}
            <div class="hidden sm:block overflow-x-auto overscroll-contain">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-3 py-2 text-left w-16">#</th>
                        <th class="px-3 py-2 text-left">姓名</th>
                        <th class="px-3 py-2 text-right w-40">Score</th>
                        <th class="px-3 py-2 text-right w-40">X</th>
                        <th class="px-3 py-2 text-left w-24">操作</th>
                    </tr>
                    </thead>
                    <tbody id="players-tbody" class="divide-y">
                    {{-- JS 注入 --}}
                    </tbody>
                </table>
            </div>

            {{-- 手機：卡片模式（直向一排排） --}}
            <div id="players-list-mobile" class="sm:hidden space-y-3">
                {{-- JS 注入 --}}
            </div>

            <div class="mt-3 flex items-center justify-between">
                <div class="flex gap-2">
                    <button id="btn-add-row"  type="button" class="rounded-xl border px-3 py-2 text-sm hover:bg-gray-50">＋新增一列</button>
                    <button id="btn-add-rows5" type="button" class="rounded-xl border px-3 py-2 text-sm hover:bg-gray-50">＋新增 5 列</button>
                </div>
                <div class="text-xs text-gray-500">同名次將自動跳號（如 1,2,2,4…）</div>
            </div>

{{--             Bulk paste --}}
            <div class="mt-6 grid grid-cols-1 lg:grid-cols-2 gap-4">
            </div>
        </div>

        {{-- Options --}}
        <div class="mt-4 flex flex-wrap items-center gap-3">
            <label class="inline-flex items-center gap-2 text-sm">
                <input id="chk-dense" type="checkbox" class="rounded border-gray-300">
                使用密集排名（1,2,2,3…）
            </label>
        </div>

        {{-- Live Ranking --}}
        <div class="mt-6 rounded-2xl border p-4">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-semibold">即時排名</h2>
                <div class="text-xs text-gray-500"><span id="ranked-count">共 0 人</span></div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-3 py-2 text-left w-16">名次</th>
                        <th class="px-3 py-2 text-left">姓名</th>
                        <th class="px-3 py-2 text-right w-40">Score</th>
                        <th class="px-3 py-2 text-right w-40">X</th>
                    </tr>
                    </thead>
                    <tbody id="ranked-tbody" class="divide-y">
                    {{-- JS 注入 --}}
                    </tbody>
                </table>
            </div>

            <div class="mt-3 text-xs text-gray-500">規則：Score（高→低）；若同分，以 X（高→低）決定順序；完全相同者同名次。</div>
        </div>
        {{-- Bracket Controls & View --}}
        <div class="mt-6 rounded-2xl border p-4">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-semibold">對戰樹（單淘汰）</h2>
                <div class="flex flex-wrap gap-2 text-sm">
                    <label class="inline-flex items-center gap-2">
                        <span class="text-xs text-gray-500">種子配對</span>
                        <select id="seed-mode" class="rounded-lg border px-2 py-1 text-sm">
                            <option value="standard" selected>1 對末位（標準）</option>
                            <option value="snake">蛇形簽位</option>
                        </select>
                    </label>
                    <button id="btn-build-bracket" class="rounded-xl bg-gray-900 px-3 py-2 text-sm font-medium text-white hover:bg-gray-800">
                        產生對戰樹
                    </button>
                    <button id="btn-download-bracket" class="rounded-xl border px-3 py-2 text-sm hover:bg-gray-500">
                        下載 PNG
                    </button>
                </div>
            </div>

            <div id="bracket-empty" class="text-xs text-gray-500">
                先在上方輸入並完成排序，按「產生對戰樹」即可建立對戰簽表。會自動補滿至 4/8/16/32… 的簽位，缺額以 BYE 補。
            </div>

            <div id="bracket" class="mt-3 overflow-x-auto">
                {{-- JS 動態產生樹狀圖 --}}
            </div>
        </div>

    </div>

    {{-- 原生 JS --}}
    <script>
        (function () {
            // ===== 單一全域 state =====
            const state = { players: [], ranked: [], denseRanking: false, bracketRounds: null };

            // ===== Helpers =====
            const $ = (sel, root=document) => root.querySelector(sel);
            const $$ = (sel, root=document) => Array.from(root.querySelectorAll(sel));
            function cryptoRandom(){ if (crypto?.getRandomValues){ const a=new Uint32Array(1); crypto.getRandomValues(a); return String(a[0]); } return String(Math.random()).slice(2); }
            function toInt(v){ const n=Number(v); return Number.isFinite(n)?Math.trunc(n):null; }
            function isFiniteNum(v){ return Number.isFinite(Number(v)); }
            function numOrNeg(v){ return Number.isFinite(Number(v)) ? Number(v) : Number.NEGATIVE_INFINITY; }
            function escapeCSV(s){ return /[",\n]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s; }
            function escapeHtml(s){ return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
            const pad2 = n => n<10 ? '0'+n : n;
            const formatDate = d => `${d.getFullYear()}-${pad2(d.getMonth()+1)}-${pad2(d.getDate())}`;


            // ===== DOM refs =====
            const $playersTbody   = $('#players-tbody');         // 桌機表格
            const $playersMobile  = $('#players-list-mobile');   // 手機卡片
            const $rankedTbody    = $('#ranked-tbody');
            const $rankedCount    = $('#ranked-count');

            const $btnAddRow      = $('#btn-add-row');
            const $btnAddRows5    = $('#btn-add-rows5');
            const $btnLoadSample  = $('#btn-load-sample');
            const $btnDownload    = $('#btn-download-csv');
            const $chkDense       = $('#chk-dense');

            // Bracket
            const $btnBuildBracket = $('#btn-build-bracket');
            const $seedMode        = $('#seed-mode');
            const $bracket         = $('#bracket');
            const $bracketEmpty    = $('#bracket-empty');

            // ===== Renderers: players =====
            function renderPlayers() {
                // 桌機表格
                $playersTbody.innerHTML = state.players.map((p, idx) => `
      <tr class="hover:bg-gray-50" data-idx="${idx}">
        <td class="px-3 py-2 text-left text-gray-500">${idx + 1}</td>
        <td class="px-3 py-2">
          <input type="text" value="${p.name ?? ''}" data-field="name"
            placeholder="姓名" autocomplete="off" autocapitalize="none" autocorrect="off" spellcheck="false"
            class="w-full min-w-0 rounded-lg border px-3 py-2 text-[16px] sm:text-sm focus:outline-none focus:ring-2 focus:ring-gray-900/20">
        </td>
        <td class="px-3 py-2 text-right">
          <input type="number" inputmode="numeric" step="1" min="0" value="${p.score ?? ''}" data-field="score"
            class="w-full min-w-0 text-right rounded-lg border px-3 py-2 text-[16px] sm:text-sm focus:outline-none focus:ring-2 focus:ring-gray-900/20">
        </td>
        <td class="px-3 py-2 text-right">
          <input type="number" inputmode="numeric" step="1" min="0" value="${p.x ?? ''}" data-field="x"
            class="w-full min-w-0 text-right rounded-lg border px-3 py-2 text-[16px] sm:text-sm focus:outline-none focus:ring-2 focus:ring-gray-900/20">
        </td>
        <td class="px-3 py-2">
          <button data-action="remove" class="text-xs text-rose-700 hover:underline">移除</button>
        </td>
      </tr>
    `).join('');

                // 手機卡片
                $playersMobile.innerHTML = state.players.map((p, idx) => `
      <div class="rounded-xl border p-3" data-idx="${idx}">
        <div class="flex items-center justify-between">
          <div class="text-xs text-gray-500">#${idx+1}</div>
          <button data-action="remove" class="text-xs text-rose-700 hover:underline">移除</button>
        </div>
        <div class="mt-2 grid grid-cols-1 gap-2">
          <label class="text-xs text-gray-500">姓名
            <input type="text" value="${p.name ?? ''}" data-field="name"
              placeholder="姓名" autocomplete="off" autocapitalize="none" autocorrect="off" spellcheck="false"
              class="mt-1 w-full rounded-lg border px-3 py-2 text-[16px] focus:outline-none focus:ring-2 focus:ring-gray-900/20">
          </label>
          <div class="grid grid-cols-2 gap-2">
            <label class="text-xs text-gray-500">Score
              <input type="number" inputmode="numeric" step="1" min="0" value="${p.score ?? ''}" data-field="score"
                class="mt-1 w-full text-right rounded-lg border px-3 py-2 text-[16px] focus:outline-none focus:ring-2 focus:ring-gray-900/20">
            </label>
            <label class="text-xs text-gray-500">X
              <input type="number" inputmode="numeric" step="1" min="0" value="${p.x ?? ''}" data-field="x"
                class="mt-1 w-full text-right rounded-lg border px-3 py-2 text-[16px] focus:outline-none focus:ring-2 focus:ring-gray-900/20">
            </label>
          </div>
        </div>
      </div>
    `).join('');

                bindPlayerInputs();
            }

            function bindPlayerInputs() {
                // 桌機
                $playersTbody.querySelectorAll('input').forEach(el => {
                    el.addEventListener('input', onPlayerInput);
                    el.addEventListener('keydown', onEnterDown);
                });
                $playersTbody.querySelectorAll('button[data-action="remove"]').forEach(btn => {
                    btn.addEventListener('click', onRemoveRow);
                });
                // 手機
                $playersMobile.querySelectorAll('input').forEach(el => {
                    el.addEventListener('input', onPlayerInput);
                    el.addEventListener('keydown', onEnterDown);
                });
                $playersMobile.querySelectorAll('button[data-action="remove"]').forEach(btn => {
                    btn.addEventListener('click', onRemoveRow);
                });
            }

            function renderRanked() {
                $rankedTbody.innerHTML = state.ranked.map(r => `
      <tr class="hover:bg-gray-50">
        <td class="px-3 py-2 font-semibold">${r.rank}</td>
        <td class="px-3 py-2">${r.name ? escapeHtml(r.name) : '—'}</td>
        <td class="px-3 py-2 text-right">${r.score ?? ''}</td>
        <td class="px-3 py-2 text-right">${r.x ?? ''}</td>
      </tr>
    `).join('');
                $rankedCount.textContent = `共 ${state.ranked.length} 人`;
            }

            // ===== Core: players/ranking =====
            function addRow() { state.players.push({ id: cryptoRandom(), name: '', score: null, x: null }); update(); }
            function addRows(n=1){ for (let i=0;i<n;i++) addRow(); }

            function onPlayerInput(e) {
                const holder = e.target.closest('[data-idx]'); if (!holder) return;
                const idx = Number(holder.dataset.idx);
                const field = e.target.dataset.field;
                if (!(idx >= 0) || !field) return;

                if (field === 'name')  state.players[idx].name  = e.target.value;
                if (field === 'score') state.players[idx].score = toInt(e.target.value);
                if (field === 'x')     state.players[idx].x     = toInt(e.target.value);

                update(false);
            }

            // Enter：往下（同欄位）— 沒有下一列就新增
            function onEnterDown(e) {
                if (e.key !== 'Enter') return;
                e.preventDefault();

                const holder = e.target.closest('[data-idx]'); if (!holder) return;
                const idx = Number(holder.dataset.idx);
                const field = e.target.dataset.field;
                const nextIdx = idx + 1;

                if (nextIdx >= state.players.length) addRow();

                requestAnimationFrame(() => {
                    const selTable = `#players-tbody tr[data-idx="${nextIdx}"] [data-field="${field}"]`;
                    const selMobile= `#players-list-mobile [data-idx="${nextIdx}"] [data-field="${field}"]`;
                    const nextInput = document.querySelector(selTable) || document.querySelector(selMobile);
                    nextInput?.focus(); nextInput?.select?.();
                });
            }

            function onRemoveRow(e) {
                const holder = e.target.closest('[data-idx]'); if (!holder) return;
                const idx = Number(holder.dataset.idx);
                state.players.splice(idx, 1);
                update();
            }

            function update(reRenderPlayers = true) {
                const clean = state.players
                    .map((p, _idx) => ({...p, _idx}))
                    .filter(p => (p.name && p.name.trim().length) || isFiniteNum(p.score) || isFiniteNum(p.x));

                const sorted = [...clean].sort((a,b)=>{
                    const as = numOrNeg(a.score), bs = numOrNeg(b.score);
                    if (bs !== as) return bs - as;
                    const ax = numOrNeg(a.x), bx = numOrNeg(b.x);
                    return bx - ax;
                });

                const ranked = [];
                let rank = 0, denseRank = 0, prev=null;
                for (let i=0;i<sorted.length;i++) {
                    const cur = sorted[i];
                    const isNewGroup = !prev || cur.score !== prev.score || cur.x !== prev.x;
                    if (state.denseRanking) { if (isNewGroup) denseRank++; ranked.push({...cur, rank: denseRank}); }
                    else { if (isNewGroup) rank = i + 1; ranked.push({...cur, rank}); }
                    prev = cur;
                }
                state.ranked = ranked;

                if (reRenderPlayers) renderPlayers();
                renderRanked();

                // 若已經產生過簽表，且人數/排序更新，就自動重新產生（友善）
                if (state.bracketRounds) {
                    buildAndRenderBracket();
                }
            }

            function downloadCSV() {
                const header = ['Rank','Name','Score','X'];
                const rows = state.ranked.map(r => [r.rank, escapeCSV(r.name||''), r.score??'', r.x??'']);
                const csv = [header, ...rows].map(row=>row.join(',')).join('\r\n');
                const blob = new Blob([csv], {type: 'text/csv;charset=utf-8;'});
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `ranking_${formatDate(new Date())}.csv`;
                document.body.appendChild(a); a.click(); a.remove();
                URL.revokeObjectURL(url);
            }

            // ===== Bracket =====
            function buildAndRenderBracket(){
                if (!state.ranked.length) {
                    $bracket.innerHTML = '';
                    $bracketEmpty.textContent = '尚未有排序名單，請先輸入並完成排序。';
                    state.bracketRounds = null;
                    return;
                }
                const rounds = buildBracket(state.ranked, $seedMode.value);
                state.bracketRounds = rounds;
                renderBracket(rounds);
                $bracketEmpty.textContent = '';
            }

            function buildBracket(ranked, mode='standard') {
                const names = ranked.map((r, i) => ({
                    seed: i+1,
                    name: r.name || `Player ${i+1}`,
                    score: r.score ?? null,
                    x: r.x ?? null
                }));

                const size = nextPow2(names.length);
                const byes = size - names.length;
                for (let i=0;i<byes;i++) names.push({ seed: null, name: 'BYE', bye:true });

                const seededOrder = (mode === 'snake') ? snakeOrder(size) : standardOrder(size);

                const slotsBySeed = {};
                seededOrder.forEach((seed, idx) => { slotsBySeed[seed] = idx; });

                const filled = new Array(size).fill(null);
                // 先把真正有 seed 的選手（非 BYE）放到對應位置
                for (let i=0;i<names.length;i++){
                    const s = names[i].seed;
                    if (s) {
                        const pos = slotsBySeed[s];
                        filled[pos] = names[i];
                    }
                }
                // 再把剩下的空位填 BYE
                for (let i=0;i<size;i++){
                    if (!filled[i]) filled[i] = { seed: null, name: 'BYE', bye:true };
                }

                // 第一回合
                const rounds = [];
                const round1 = [];
                for (let i=0;i<size;i+=2){
                    round1.push({ p1: filled[i], p2: filled[i+1] });
                }
                rounds.push(round1);

                // 後續回合（空框）
                let prevSize = round1.length;
                while(prevSize > 1){
                    const r = new Array(Math.ceil(prevSize/2)).fill(0).map(()=>({ p1:null, p2:null }));
                    rounds.push(r);
                    prevSize = r.length;
                }
                return rounds;
            }

            function standardOrder(n){
                let arr = [1];
                while(arr.length < n){
                    const m = arr.length * 2;
                    const mirror = arr.map(x => m + 1 - x);
                    arr = arr.flatMap((x, i) => [x, mirror[i]]);
                }
                return arr;
            }

            function snakeOrder(n){
                let arr = [1,2];
                while(arr.length < n){
                    const next = [];
                    const base = arr.length + 1;
                    for (let i=0;i<arr.length;i++) next.push(base + i);
                    if ((Math.log2(arr.length)+1) % 2 === 1) next.reverse();
                    arr = arr.concat(next);
                }
                return arr;
            }

            const nextPow2 = (x) => 1 << (Math.ceil(Math.log2(Math.max(1,x))));
            const seeds32 = [
                1,32,16,17,9,24,8,25,
                5,28,12,21,13,20,4,29,
                3,30,14,19,11,22,6,27,
                7,26,10,23,15,18,2,31
            ];
            function standardOrder(n){
                let arr=[1];
                while(arr.length<n){
                    const m=arr.length*2, mirror=arr.map(x=>m+1-x);
                    arr = arr.flatMap((x,i)=>[x,mirror[i]]);
                }
                return arr;
            }
            function renderBracket(rounds){
                const $bracket = document.getElementById('bracket');
                if (!rounds?.length) { $bracket.innerHTML=''; return; }

                // ===== 讓 HTML 跟 PNG 用同一套順序與上下規則 =====

                const cols   = rounds.length;
                const leaves = rounds[0].length * 2;

                // 32 強用客製順序，其它用標準籤位
                const seedOrder = (leaves === 32) ? seeds32 : standardOrder(leaves);
                const seedToLeafIndex = {};
                seedOrder.forEach((seed, idx) => { seedToLeafIndex[seed] = idx; });

                // 依「葉子最小索引」建立每回合的可視順序（roundOrd）與反查（roundInv）
                const roundMinIdx = [];
                const roundOrd = [];
                const roundInv = [];

                // 第一輪：由種子 → 葉子索引；BYE 以對手索引 ^1 補
                {
                    const m0 = rounds[0];
                    const minIdx = new Array(m0.length);
                    for (let i=0;i<m0.length;i++){
                        const a = m0[i].p1, b = m0[i].p2;
                        let ai = null, bi = null;
                        if (a?.seed) ai = seedToLeafIndex[a.seed];
                        if (b?.seed) bi = seedToLeafIndex[b.seed];
                        if (ai==null && bi!=null) ai = bi ^ 1;
                        if (bi==null && ai!=null) bi = ai ^ 1;
                        if (ai==null) ai = i*2;
                        if (bi==null) bi = i*2+1;
                        minIdx[i] = Math.min(ai, bi);
                    }
                    roundMinIdx[0] = minIdx;
                    const ord0 = [...m0.keys()].sort((i,j)=>minIdx[i]-minIdx[j]);
                    roundOrd[0] = ord0;
                    const inv0 = {}; ord0.forEach((origIdx,g)=>{inv0[origIdx]=g;}); roundInv[0]=inv0;
                }

                // 後續回合：父場 = 子場(2k) ∪ 子場(2k+1)
                for (let c=1;c<cols;c++){
                    const prev = roundMinIdx[c-1];
                    const len  = rounds[c].length;
                    const minIdx = new Array(len);
                    for (let k=0;k<len;k++){
                        minIdx[k] = Math.min(prev[2*k], prev[2*k+1]);
                    }
                    roundMinIdx[c] = minIdx;
                    const ord = [...Array(len).keys()].sort((i,j)=>minIdx[i]-minIdx[j]);
                    roundOrd[c] = ord;
                    const inv = {}; ord.forEach((origIdx,g)=>{inv[origIdx]=g;}); roundInv[c]=inv;
                }

                // ===== 把 rounds 依 roundOrd 轉成「可視順序」；並在第一輪處理上下行 =====
                const visualRounds = rounds.map((matches, c) => {
                    const ordered = roundOrd[c].map(origIdx => {
                        let {p1, p2} = matches[origIdx];

                        if (c === 0) {
                            // 第一輪：確保「上排 = 上方葉子、下排 = 下方葉子」
                            const g = roundInv[c][origIdx];            // 這場在可視中的位置（0..）
                            const topLeaf = 2*g, botLeaf = 2*g+1;

                            let aLeaf = p1?.seed!=null ? seedToLeafIndex[p1.seed] : null;
                            let bLeaf = p2?.seed!=null ? seedToLeafIndex[p2.seed] : null;
                            if (aLeaf==null && bLeaf!=null) aLeaf = bLeaf ^ 1;
                            if (bLeaf==null && aLeaf!=null) bLeaf = aLeaf ^ 1;

                            // 如果上排不是 topLeaf，就交換
                            if (aLeaf !== topLeaf && bLeaf === topLeaf) {
                                const tmp = p1; p1 = p2; p2 = tmp;
                            }
                        }
                        return { p1, p2 };
                    });
                    return ordered;
                });

                // ===== Render：每欄一個 column，場次依「可視順序」由上而下 =====
                const colHtml = visualRounds.map((matches, roundIdx) => {
                    const title = roundTitle(roundIdx, visualRounds.length);
                    const cards = matches.map((m) => {
                        const a = m.p1, b = m.p2;
                        const aName = a ? escapeHtml(a.name || '—') : '—';
                        const bName = b ? escapeHtml(b.name || '—') : '—';
                        const aSeed = a?.seed ? `<span class="brk-seed">#${a.seed}</span>` : '';
                        const bSeed = b?.seed ? `<span class="brk-seed">#${b.seed}</span>` : '';
                        const aCls = a?.bye ? 'brk-bye' : '';
                        const bCls = b?.bye ? 'brk-bye' : '';

                        // 第一欄加連線裝飾（你的既有樣式 brk-link）
                        const linkCls = (roundIdx === 0) ? ' brk-link' : '';

                        return `
        <div class="brk-match${linkCls}">
          <div class="brk-name">
            <span class="${aCls}">${aName}</span>${aSeed}
          </div>
          <div class="brk-name mt-1">
            <span class="${bCls}">${bName}</span>${bSeed}
          </div>
        </div>
      `;
                    }).join('');

                    return `
      <div class="brk-col">
        <div class="text-xs text-gray-500 mb-1">${title}</div>
        ${cards}
      </div>
    `;
                }).join('');

                $bracket.innerHTML = `<div class="brk-columns">${colHtml}</div>`;

                // 小工具（沿用你既有的）
                function roundTitle(idx,total){
                    const left=total-idx;
                    if (left===1) return '決賽';
                    if (left===2) return '準決賽';
                    if (left===3) return '8 強';
                    if (left===4) return '16 強';
                    if (left===5) return '32 強';
                    if (left===6) return '64 強';
                    if (left===7) return '128 強';
                    return `第 ${idx+1} 回合`;
                }
                function escapeHtml(s){ return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
            }

            function roundTitle(idx, total){
                const left = total - idx;
                if (left === 1) return '決賽';
                if (left === 2) return '準決賽';
                if (left === 3) return '8 強';
                if (left === 4) return '16 強';
                if (left === 5) return '32 強';
                return `第 ${idx+1} 回合`;
            }

            // ===== Events =====
            const $btnDownloadBracket = document.getElementById('btn-download-bracket');

            $btnAddRow     ?.addEventListener('click', () => addRow());
            $btnAddRows5   ?.addEventListener('click', () => addRows(5));
            $btnLoadSample ?.addEventListener('click', () => {
                state.players = [
                    { id: cryptoRandom(), name: 'Alice', score: 650, x: 22 },
                    { id: cryptoRandom(), name: 'Bob',   score: 650, x: 19 },
                    { id: cryptoRandom(), name: 'Cody',  score: 648, x: 25 },
                    { id: cryptoRandom(), name: 'Dora',  score: 650, x: 22 },
                    { id: cryptoRandom(), name: 'Evan',  score: 640, x: 18 },
                ];
                update();
            });
            $btnDownload   ?.addEventListener('click', () => downloadCSV());
            $chkDense      ?.addEventListener('change', (e) => { state.denseRanking = !!e.target.checked; update(false); });

            $btnBuildBracket?.addEventListener('click', buildAndRenderBracket);
            $seedMode       ?.addEventListener('change', () => { if (state.bracketRounds) buildAndRenderBracket(); });
            $btnDownloadBracket?.addEventListener('click', downloadBracketPNG);

            // ===== Init =====
            addRows(8);
            update();

            // ===== Canvas Export (Bracket -> PNG) =====
            // ===== Canvas Export (Bracket -> PNG) — 標準籤位 + 指定上下顯示 =====
            // 下載對戰樹 PNG（32 強固定為你要的順序）
            function downloadBracketPNG() {
                if (!state.bracketRounds || !state.bracketRounds.length) {
                    alert('請先產生對戰樹');
                    return;
                }
                const rounds = state.bracketRounds;

                // 幾何
                const colWidth=260, colGap=48, cardH=64, cardPad=10, titleH=24, margin=36, radius=12;
                const lineColor='#111', textColor='#111827', subColor='#111', bgColor='#fff';

                const cols = rounds.length;
                const leaves = rounds[0].length * 2;           // 第一輪葉子數
                const stride = cardH + 32;                     // 葉子中心垂直間距
                const totalLeafSpan = (leaves - 1) * stride;
                const baseTop = margin + titleH;
                const canvasW = margin + cols * colWidth + (cols - 1) * colGap + margin;
                const canvasH = margin + titleH + totalLeafSpan + cardH + margin;

                // Retina
                const scale = window.devicePixelRatio > 1 ? 2 : 1;
                const canvas = document.createElement('canvas');
                canvas.width = canvasW * scale; canvas.height = canvasH * scale;
                canvas.style.width = canvasW + 'px'; canvas.style.height = canvasH + 'px';
                const ctx = canvas.getContext('2d'); ctx.scale(scale, scale);

                // 背景
                ctx.fillStyle = bgColor; ctx.fillRect(0, 0, canvasW, canvasH);

                const fontMain='14px system-ui,-apple-system,Segoe UI,Roboto,Noto Sans,Helvetica,Arial';
                const fontSub ='12px system-ui,-apple-system,Segoe UI,Roboto,Noto Sans,Helvetica,Arial';
                ctx.lineWidth = 2;

                // 工具
                function roundRect(x,y,w,h,r){ctx.beginPath();ctx.moveTo(x+r,y);ctx.arcTo(x+w,y,x+w,y+h,r);ctx.arcTo(x+w,y+h,x,y+h,r);ctx.arcTo(x,y+h,x,y,r);ctx.arcTo(x,y,x+w,y,r);ctx.closePath();}
                function roundTitle(idx,total){const left=total-idx;if(left===1)return'決賽';if(left===2)return'準決賽';if(left===3)return'8 強';if(left===4)return'16 強';if(left===5)return'32 強';if(left===6)return'64 強';if(left===7)return'128 強';return`第 ${idx+1} 回合`;}
                const colX = (c)=> margin + c*(colWidth+colGap);
                const centerYByGroup = (c,g)=>{const pow=2**c,offset=(pow-1)/2;return baseTop + cardH/2 + (pow*g + offset)*stride;};

                // 標準籤位（通用）
                function standardOrder(n){
                    let arr=[1];
                    while(arr.length<n){
                        const m=arr.length*2, mirror=arr.map(x=>m+1-x);
                        arr = arr.flatMap((x,i)=>[x,mirror[i]]);
                    }
                    return arr;
                }

                // 32 強固定順序（從上到下）
                const seeds32 = [
                    1,32,16,17,9,24,8,25,
                    5,28,12,21,13,20,4,29,
                    3,30,14,19,11,22,6,27,
                    7,26,10,23,15,18,2,31
                ];

                // 葉子順序（32 強用客製，其它用標準）
                const seedOrder = (leaves === 32) ? seeds32 : standardOrder(leaves);
                const seedToLeafIndex = {};
                seedOrder.forEach((seed, idx) => { seedToLeafIndex[seed] = idx; });

                // 依「葉子最小索引」決定每回合可視順序（讓所有回合幾何中心對齊）
                const roundMinIdx = [];
                const roundOrd = [];
                const roundInv = [];

                // 第一輪：由種子 → 葉子索引；BYE 用對手索引 ^1 補
                {
                    const m0 = rounds[0];
                    const minIdx = new Array(m0.length);
                    for (let i=0;i<m0.length;i++){
                        const a=m0[i].p1, b=m0[i].p2;
                        let ai=null, bi=null;
                        if (a?.seed) ai = seedToLeafIndex[a.seed];
                        if (b?.seed) bi = seedToLeafIndex[b.seed];
                        if (ai==null && bi!=null) ai = bi ^ 1;
                        if (bi==null && ai!=null) bi = ai ^ 1;
                        if (ai==null) ai = i*2;
                        if (bi==null) bi = i*2+1;
                        minIdx[i] = Math.min(ai, bi);
                    }
                    roundMinIdx[0] = minIdx;
                    const ord0 = [...m0.keys()].sort((i,j)=>minIdx[i]-minIdx[j]);
                    roundOrd[0] = ord0;
                    const inv0 = {}; ord0.forEach((origIdx,g)=>{inv0[origIdx]=g;}); roundInv[0]=inv0;
                }

                // 後續回合
                for (let c=1;c<cols;c++){
                    const prev = roundMinIdx[c-1];
                    const len = rounds[c].length;
                    const minIdx = new Array(len);
                    for (let k=0;k<len;k++) minIdx[k] = Math.min(prev[2*k], prev[2*k+1]);
                    roundMinIdx[c]=minIdx;
                    const ord = [...Array(len).keys()].sort((i,j)=>minIdx[i]-minIdx[j]);
                    roundOrd[c]=ord;
                    const inv={}; ord.forEach((origIdx,g)=>{inv[origIdx]=g;}); roundInv[c]=inv;
                }

                // 連線
                ctx.strokeStyle = lineColor;
                for (let c=0;c<cols-1;c++){
                    for (let i=0;i<rounds[c].length;i++){
                        const g = roundInv[c][i];
                        const pOrig = Math.floor(i/2);
                        const gParent = roundInv[c+1][pOrig];
                        const x1 = colX(c)+colWidth, y1 = centerYByGroup(c,g);
                        const x2 = colX(c+1),       y2 = centerYByGroup(c+1,gParent);
                        const midX=(x1+x2)/2;
                        ctx.beginPath(); ctx.moveTo(x1,y1); ctx.lineTo(midX,y1); ctx.lineTo(midX,y2); ctx.lineTo(x2,y2); ctx.stroke();
                    }
                }

                // 卡片＋標題
                for (let c=0;c<cols;c++){
                    ctx.fillStyle=subColor; ctx.font=fontSub;
                    ctx.fillText(roundTitle(c,cols), colX(c), margin+14);

                    for (let i=0;i<rounds[c].length;i++){
                        const g = roundInv[c][i];
                        const x = colX(c), y = centerYByGroup(c,g) - cardH/2;

                        // 卡片
                        roundRect(x,y,colWidth,cardH,radius);
                        ctx.fillStyle='#fff'; ctx.fill();
                        ctx.strokeStyle='#e5e7eb'; ctx.stroke();

                        // 參賽者（第一輪把「上排」固定為該場上方葉子）
                        let a = rounds[c][i].p1, b = rounds[c][i].p2;
                        if (c === 0) {
                            const topLeaf = 2*g, botLeaf = 2*g+1;
                            // 取 a,b 的葉子索引
                            let aLeaf = a?.seed!=null ? seedToLeafIndex[a.seed] : null;
                            let bLeaf = b?.seed!=null ? seedToLeafIndex[b.seed] : null;
                            if (aLeaf==null && bLeaf!=null) aLeaf = bLeaf ^ 1;
                            if (bLeaf==null && aLeaf!=null) bLeaf = aLeaf ^ 1;

                            // 需要時交換，確保上排是 topLeaf
                            if (aLeaf!==topLeaf && bLeaf===topLeaf) { const tmp=a; a=b; b=tmp; }
                        }

                        // 上列
                        ctx.font=fontMain; ctx.fillStyle = a?.bye ? '#9ca3af' : textColor;
                        ctx.fillText(a ? (a.name||'—') : '—', x+cardPad, y+cardPad+14);
                        if (a?.seed){ const s=`#${a.seed}`; ctx.font=fontSub; ctx.fillStyle=subColor; const wS=ctx.measureText(s).width; ctx.fillText(s, x+colWidth-cardPad-wS, y+cardPad+12); }

                        // 下列
                        ctx.font=fontMain; ctx.fillStyle = b?.bye ? '#9ca3af' : textColor;
                        ctx.fillText(b ? (b.name||'—') : '—', x+cardPad, y+cardH-cardPad-6);
                        if (b?.seed){ const s=`#${b.seed}`; ctx.font=fontSub; ctx.fillStyle=subColor; const wS=ctx.measureText(s).width; ctx.fillText(s, x+colWidth-cardPad-wS, y+cardH-cardPad-6); }
                    }
                }

                // 下載
                const a = document.createElement('a');
                a.href = canvas.toDataURL('image/png');
                a.download = `bracket_${new Date().toISOString().slice(0,10)}.png`;
                document.body.appendChild(a); a.click(); a.remove();
            }
        })();
    </script>

    <style>
        /* 簡易樹狀圖樣式（不靠外部套件） */
        .brk-columns { display:flex; gap:1rem; min-width:640px; }
        .brk-col { display:flex; flex-direction:column; gap:1rem; min-width:180px; }
        .brk-match { position:relative; border:1px solid #e5e7eb; border-radius:0.75rem; padding:0.5rem 0.75rem; background:#fff; }
        .brk-name { display:flex; justify-content:space-between; gap:0.5rem; font-size:0.875rem; }
        .brk-seed { color:#6b7280; font-size:0.7rem; }
        .brk-bye { color:#9ca3af; font-style:italic; }
        /* 連線（僅桌機顯示），手機太擠就不畫線 */
        @media (min-width:640px){
            .brk-link:before, .brk-link:after {
                content:""; position:absolute; right:-0.5rem; width:0.5rem; border-right:2px solid #e5e7eb;
            }
            .brk-link:before { top:25%; height:50%; border-top:2px solid #e5e7eb; }
            .brk-link:after  { bottom:25%; height:50%; border-bottom:2px solid #e5e7eb; }
        }
    </style>
@endsection


