@extends('layouts.app')
@section('title', 'ArrowTrack — 對抗表生成器')
@section('content')
<div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">🏆 對抗賽生成器</h1>
            <p class="text-sm text-gray-500">依輸入排名分數自動排種子、生成對戰樹</p>
        </div>


        {{-- Controls --}}
        <div class="flex flex-wrap gap-2 sm:gap-3">
            <button id="btn-download" type="button"
                    class="hidden inline-flex items-center justify-center rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-500">
                下載 PNG
            </button>
            <button id="btn-print" type="button"
                    class="hidden inline-flex items-center justify-center rounded-xl bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800">
                列印
            </button>
            <button id="btn-export-json" type="button"
                    class="hidden inline-flex items-center justify-center rounded-xl border px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                匯出 JSON
            </button>
            <button id="btn-reset" type="button"
                    class="hidden inline-flex items-center justify-center rounded-xl border px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                重新開始
            </button>
        </div>
    </div>


{{-- Setup Section --}}
<div id="setup-section" class="rounded-2xl border p-5 sm:p-6">
    <div class="grid gap-4 sm:grid-cols-3">
        <div class="space-y-1.5">
            <label for="tournament-name" class="text-sm font-medium text-gray-700">比賽名稱</label>
            <input id="tournament-name" type="text" value="2024 春季盃"
                   class="w-full rounded-xl border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                   placeholder="例如：2025 春季盃">
        </div>


        <div class="space-y-1.5">
            <label for="player-count" class="text-sm font-medium text-gray-700">參賽人數</label>
            <select id="player-count"
                    class="w-full rounded-xl border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="8">8 人</option>
                <option value="16">16 人</option>
                <option value="32">32 人</option>
                <option value="64">64 人</option>
                <option value="128">128 人</option>
            </select>
        </div>


        <div class="flex items-end">
            <button id="btn-gen-inputs" type="button"
                    class="inline-flex w-full items-center justify-center rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                產生選手輸入欄位
            </button>
        </div>
    </div>


    <div id="players-input-container" class="mt-6"></div>


    <div id="action-buttons" class="mt-4 hidden">
        <button id="btn-gen-bracket" type="button"
                class="inline-flex items-center justify-center rounded-xl bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500">
            生成賽程表
        </button>
    </div>
</div>


{{-- Bracket Section --}}
<div id="bracket-wrap" class="mt-6 hidden">
    <div id="bracket" class="overflow-x-auto rounded-2xl border p-4">
        {{-- bracket will render here --}}
    </div>
</div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js" integrity="sha512-BNa5H0bXjv1r+3Q2kP1V9g+0rQeT1Xo5b5o3i0mAqg7Yy2e0vSxqf2W3eB5cHkq5o0CwN8D2r9qEw3S0sGm5NQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script>
    const state = { name: '', totalPlayers: 0, players: [], matches: [] };
    const els = {
        setup: document.getElementById('setup-section'),
        genInputs: document.getElementById('btn-gen-inputs'),
        genBracket: document.getElementById('btn-gen-bracket'),
        playersBox: document.getElementById('players-input-container'),
        actionBtns: document.getElementById('action-buttons'),
        bracketWrap: document.getElementById('bracket-wrap'),
        bracket: document.getElementById('bracket'),
        btnDownload: document.getElementById('btn-download'),
        btnExportJson: document.getElementById('btn-export-json'),
        btnPrint: document.getElementById('btn-print'),
        btnReset: document.getElementById('btn-reset'),
    };

    window.addEventListener('load', generatePlayerInputs);
    els.genInputs.addEventListener('click', generatePlayerInputs);
    els.genBracket.addEventListener('click', generateBracket);


    function generatePlayerInputs() {
        const count = parseInt(document.getElementById('player-count').value, 10);
        const blocks = [];
        blocks.push('<h3 class="mb-3 text-base font-semibold">請輸入選手名稱與初始分數</h3>');
        blocks.push('<div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">');
        for (let i = 1; i <= count; i++) {
            const score = Math.floor(Math.random() * 100);
            blocks.push(`
<div class="rounded-xl border p-4">
<div class="mb-2 text-xs text-gray-500">選手 #${i}</div>
<div class="space-y-2">
<input id="player-name-${i}" type="text" value="選手${i}"
class="w-full rounded-xl border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="名稱">
<input id="player-score-${i}" type="number" value="${score}"
class="w-full rounded-xl border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="初始分數">
</div>
</div>`);
        }
        blocks.push('</div>');
        els.playersBox.innerHTML = blocks.join('');
        els.actionBtns.classList.remove('hidden');
    }

    function generateBracket() {
        const count = parseInt(document.getElementById('player-count').value, 10);
        state.name = document.getElementById('tournament-name').value || 'Tournament';
        state.totalPlayers = count;
        state.players = [];


        for (let i = 1; i <= count; i++) {
            const name = document.getElementById(`player-name-${i}`).value || `選手${i}`;
            const score = parseInt(document.getElementById(`player-score-${i}`).value, 10) || 0;
            state.players.push({ id: i, name, initialScore: score });
        }


// 依分數排序（高→低）並標註 seed
        state.players.sort((a, b) => b.initialScore - a.initialScore);
        state.players.forEach((p, idx) => p.seed = idx + 1);


        buildMatches();
        renderBracket();


// 顯示對戰表與工具列
        els.setup.classList.add('hidden');
        els.bracketWrap.classList.remove('hidden');
        [ els.btnPrint, els.btnReset, els.btnExportJson].forEach(b => b.classList.remove('hidden'));
    }

    function buildMatches() {
        state.matches = [];
        const totalRounds = Math.log2(state.totalPlayers);
        const firstPairs = getFirstRoundPairings(state.totalPlayers);


        let id = 1; const allRounds = [];
// 第一輪
        const round1 = firstPairs.map(([s1, s2]) => ({
            id: id++, round: 1,
            player1: state.players.find(p => p.seed === s1) || null,
            player2: state.players.find(p => p.seed === s2) || null,
            score1: null, score2: null, winner: null,
        }));
        allRounds.push(round1);


// 後續輪次（空白）
        let prev = round1;
        for (let r = 2; r <= totalRounds; r++) {
            const curr = [];
            for (let i = 0; i < prev.length; i += 2) {
                curr.push({ id: id++, round: r, player1: null, player2: null, score1: null, score2: null, winner: null, prevMatch1: prev[i]?.id, prevMatch2: prev[i+1]?.id });
            }
            allRounds.push(curr); prev = curr;
        }


        state.matches = allRounds.flat();
    }


    function renderBracket() {
        const totalRounds = Math.log2(state.totalPlayers);
        const cols = [];
        cols.push('<div class="flex gap-8 min-w-max">');
        for (let r = 1; r <= totalRounds; r++) {
            cols.push('<div class="min-w-[240px]">');
            cols.push(`<div class="mb-3 rounded-md bg-indigo-50 py-2 text-center text-sm font-semibold text-indigo-600">${getRoundName(r, totalRounds)}</div>`);
            const roundMatches = state.matches.filter(m => m.round === r);
            for (const m of roundMatches) cols.push(`<div class="mb-4">${renderMatchCard(m)}</div>`);
            cols.push('</div>');
        }
        cols.push('</div>');
        els.bracket.innerHTML = cols.join('');
    }


    function renderMatchCard(m) {
        const completed = !!m.winner;
        return `
<div class="rounded-xl border ${completed ? 'border-emerald-500' : 'border-gray-200'} bg-white shadow-sm">
<div class="divide-y">
${renderPlayerRow(m.player1, m.score1, m.winner && m.winner.id === m.player1?.id)}
${renderPlayerRow(m.player2, m.score2, m.winner && m.winner.id === m.player2?.id)}
${m.player1 && m.player2 && !m.winner ? renderScoreForm(m.id) : ''}
</div>
</div>`;
    }

    function renderPlayerRow(player, score, isWinner) {
        const rank = player && typeof player.seed === 'number' ? player.seed : '';
        const name = player ? player.name : '<span class="italic text-gray-400">待定</span>';
        const init = player ? `(<span class="text-xs text-gray-500">${player.initialScore}</span>)` : '';
        return `
  <div class="flex items-center justify-between px-3 py-2 ${isWinner ? 'bg-emerald-50 font-semibold' : ''}">
    <div class="text-sm flex items-center gap-2">
      ${player ? `<span class="inline-flex items-center rounded-md bg-gray-100 px-1.5 py-0.5 text-xs text-gray-700">#${rank}</span>` : ''}
      <span>${name} ${init}</span>
    </div>
    ${score !== null && score !== undefined ? `<div class="text-sm font-bold">${score}</div>` : ''}
  </div>`;
    }


    function renderScoreForm(matchId) {
        return `
<form class="bg-gray-50 px-3 py-2" onsubmit="updateMatch(event, ${matchId})">
<div class="flex flex-wrap items-center gap-2">
<input type="number" name="score1" min="0" placeholder="分數" class="w-20 rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
<span class="text-xs text-gray-500">vs</span>
<input type="number" name="score2" min="0" placeholder="分數" class="w-20 rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
<button type="submit" class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-emerald-500">更新</button>
</div>
</form>`;
    }


    function getRoundName(round, totalRounds) {
        if (round === totalRounds) return '決賽';
        if (round === totalRounds - 1) return '準決賽';
        if (round === totalRounds - 2) return '半準決賽';
        return `第 ${round} 輪`;
    }


function updateMatch(e, matchId) {
e.preventDefault();
const fd = new FormData(e.target);
const s1 = parseInt(fd.get('score1'), 10) || 0;
const s2 = parseInt(fd.get('score2'), 10) || 0;
const m = state.matches.find(x => x.id === matchId);
if (!m) return;
m.score1 = s1; m.score2 = s2; m.winner = s1 > s2 ? m.player1 : m.player2;
const next = state.matches.find(x => x.prevMatch1 === matchId || x.prevMatch2 === matchId);
if (next) { if (next.prevMatch1 === matchId) next.player1 = m.winner; else next.player2 = m.winner; }
renderBracket();
}


function getFirstRoundPairings(total) {
const pre = {
8: [[1,8],[5,4],[3,6],[7,2]],
16: [[1,16],[9,8],[5,12],[13,4],[3,14],[11,6],[7,10],[15,2]],
32: [[1,32],[17,16],[9,24],[25,8],[5,28],[21,12],[13,20],[29,4],[3,30],[19,14],[11,22],[27,6],[7,26],[23,10],[15,18],[31,2]],
64: [[1,64],[33,32],[17,48],[49,16],[9,56],[41,24],[25,40],[57,8],[5,60],[37,28],[21,44],[53,12],[13,52],[45,20],[29,36],[61,4],[3,62],[35,30],[19,46],[51,14],[11,54],[43,22],[27,38],[59,6],[7,58],[39,26],[23,42],[55,10],[15,50],[47,18],[31,34],[63,2]],
128: [[1,128],[65,64],[33,96],[97,32],[17,112],[81,48],[49,80],[113,16],[9,120],[73,56],[41,88],[105,24],[25,104],[89,40],[57,72],[121,8],[5,124],[69,60],[37,92],[101,28],[21,108],[85,44],[53,76],[117,12],[13,116],[77,52],[45,84],[109,20],[29,100],[93,36],[61,68],[125,4],[3,126],[67,62],[35,94],[99,30],[19,110],[83,46],[51,78],[115,14],[11,118],[75,54],[43,86],[107,22],[27,102],[91,38],[59,70],[123,6],[7,122],[71,58],[39,90],[103,26],[23,106],[87,42],[55,74],[119,10],[15,114],[79,50],[47,82],[111,18],[31,98],[95,34],[63,66],[127,2]],
};
return pre[total] || generatePairings(total);
}


function generatePairings(total) {
let seeds = [1];
const rounds = Math.log2(total);
for (let i = 0; i < rounds; i++) {
const next = [];
const sum = Math.pow(2, i + 1) + 1;
for (const s of seeds) { next.push(s); next.push(sum - s); }
seeds = next;
}
const res = [];
for (let i = 0; i < seeds.length; i += 2) {
const a = seeds[i], b = seeds[i + 1];
res.push(a < b ? [a, b] : [b, a]);
}
return res;
}


// ===== 匯出 / 下載 / 列印 / 重置 =====
function downloadBlob(filename, mime, text) {
const blob = new Blob([text], { type: mime });
const url = URL.createObjectURL(blob);
const a = document.createElement('a');
a.href = url; a.download = filename; a.click();
setTimeout(() => URL.revokeObjectURL(url), 1000);
}


function exportStateJSON() {
const payload = JSON.stringify(state, null, 2);
downloadBlob(`${state.name || 'tournament'}_state.json`, 'application/json', payload);
}


els.btnExportJson.addEventListener('click', exportStateJSON);


els.btnDownload.addEventListener('click', async () => {
// 只輸出對戰表且不裁切（完整 scroll 區域）
const container = document.getElementById('bracket');
const target = container.querySelector(':scope > div') || container;
const width = Math.max(target.scrollWidth, target.clientWidth);
const height = Math.max(target.scrollHeight, target.clientHeight);
const canvas = await html2canvas(target, { backgroundColor: '#ffffff', width, height, windowWidth: width, windowHeight: height, scrollX: 0, scrollY: 0, scale: 2 });
const a = document.createElement('a');
a.download = `${state.name}_bracket.png`;
a.href = canvas.toDataURL('image/png');
a.click();
});


els.btnPrint.addEventListener('click', () => {
// 只印出對戰表
const container = document.getElementById('bracket');
const target = container.querySelector(':scope > div') || container;
const win = window.open('', 'print-bracket', 'width=1280,height=800');
if (!win) return;
const doc = win.document;
doc.write('<!doctype html><html><head><meta charset="utf-8"><title>Bracket</title>');
    document.querySelectorAll('link[rel="stylesheet"], style').forEach(n => doc.write(n.outerHTML));
    doc.write('<style>@media print{ body{ margin:0!important; } }</style>');
    doc.write('</head><body class="bg-white p-4">');
doc.write(`<div id="print-bracket">${target.outerHTML}</div>`);
doc.write('</body></html>');
doc.close();
win.focus();
win.addEventListener('load', () => win.print());
});


els.btnReset.addEventListener('click', () => window.location.reload());
</script>
@endsection
