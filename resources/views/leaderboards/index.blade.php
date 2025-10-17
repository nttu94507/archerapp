{{-- resources/views/leaderboards/index.blade.php --}}
@extends('layouts.app') {{-- 依你的專案調整 --}}

@section('title', 'ArrowTrack')

@section('content')
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">

        {{-- Page Header --}}
        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Archery Leaderboard</h1>
                <p class="text-sm text-gray-500">依你選擇的分組與期間計算綜合分數（R = 0.7×PI + 0.3×Elo）。</p>
            </div>

            {{-- Filters --}}
            <form method="GET" class="grid grid-cols-2 sm:flex sm:flex-wrap gap-2 sm:gap-3">
                <label class="sr-only" for="bow_type">Bow Type</label>
                <select id="bow_type" name="bow_type" class="rounded-xl border-gray-300 text-sm">
                    @php $bow = request('bow_type'); @endphp
                    <option value="">All Bows</option>
                    <option value="recurve"  @selected($bow==='recurve')>Recurve</option>
                    <option value="compound" @selected($bow==='compound')>Compound</option>
                    <option value="barebow"  @selected($bow==='barebow')>Barebow</option>
                    <option value="longbow"  @selected($bow==='longbow')>Longbow</option>
                </select>

                <label class="sr-only" for="mode">Mode</label>
                @php $mode = request('mode'); @endphp
                <select id="mode" name="mode" class="rounded-xl border-gray-300 text-sm">
                    <option value="">Indoor + Outdoor</option>
                    <option value="indoor"  @selected($mode==='indoor')>Indoor</option>
                    <option value="outdoor" @selected($mode==='outdoor')>Outdoor</option>
                </select>

                <label class="sr-only" for="round_id">Round</label>
                <select id="round_id" name="round_id" class="rounded-xl border-gray-300 text-sm">
                    <option value="">All Rounds</option>
                    @foreach(($rounds ?? []) as $round)
                        <option value="{{ $round->id }}" @selected((string)request('round_id')===(string)$round->id)>
                            {{ $round->name }} • {{ $round->distance }}m • {{ $round->target_face }}cm
                        </option>
                    @endforeach
                </select>

                <label class="sr-only" for="range">Range</label>
                @php $range = request('range', '90d'); @endphp
                <select id="range" name="range" class="rounded-xl border-gray-300 text-sm">
                    <option value="30d" @selected($range==='30d')>最近 30 天</option>
                    <option value="90d" @selected($range==='90d')>最近 90 天</option>
                    <option value="180d" @selected($range==='180d')>最近 180 天</option>
                    <option value="365d" @selected($range==='365d')>最近 365 天</option>
                    <option value="all" @selected($range==='all')>全部</option>
                </select>

                <button type="submit"
                        class="inline-flex items-center justify-center rounded-xl bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800">
                    套用
                </button>
                <button id="glossary-open" type="button"
                >
                    <span class="text-base">ⓘ</span>
                </button>

                {{-- Reset link --}}
                @if(request()->query())
                    <a href="{{ route('leaderboards.index') }}"
                       class="inline-flex items-center justify-center rounded-xl border px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                        重設
                    </a>
                @endif
            </form>
        </div>

        {{-- sort helper --}}
        @php
            $sort = request('sort', 'R_desc'); // e.g. "R_desc", "PI_desc", "Elo_desc"
            [$sortKey, $sortDir] = array_pad(explode('_', $sort), 2, 'desc');
            $link = fn($key) => request()->fullUrlWithQuery([
              'sort' => $sortKey===$key && $sortDir==='asc' ? "{$key}_desc" : "{$key}_asc"
            ]);
            $arrow = fn($key) => $sortKey===$key ? ($sortDir==='asc' ? '▲' : '▼') : '';
        @endphp

        {{-- Desktop Table --}}
        <div class="hidden md:block overflow-x-auto rounded-2xl border">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500 sticky top-0 z-10">
                <tr>
                    <th class="px-4 py-3 text-left w-16">#</th>
                    <th class="px-4 py-3 text-left min-w-[180px]">Archer</th>
                    <th class="px-4 py-3 text-right w-28">
                        <a href="{{ $link('R') }}" class="inline-flex items-center gap-1">R<span>{{ $arrow('R') }}</span></a>
                    </th>
                    <th class="px-4 py-3 text-right w-28">
                        <a href="{{ $link('PI') }}" class="inline-flex items-center gap-1">PI<span>{{ $arrow('PI') }}</span></a>
                    </th>
                    <th class="px-4 py-3 text-right w-24">
                        <a href="{{ $link('Elo') }}" class="inline-flex items-center gap-1">Elo<span>{{ $arrow('Elo') }}</span></a>
                    </th>
                    <th class="px-4 py-3 text-right w-24">AAE</th>
                    <th class="px-4 py-3 text-right w-20">X%</th>
                    <th class="px-4 py-3 text-right w-20">10%</th>
                    <th class="px-4 py-3 text-right w-24">σ</th>
                    <th class="px-4 py-3 text-right w-32">Best (90d)</th>
                    <th class="px-4 py-3 text-right w-36">Last Active</th>
                </tr>
                </thead>
                <tbody class="divide-y">
                @forelse($leaders as $i => $row)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-gray-500">
                            {{ method_exists($leaders,'firstItem') ? $leaders->firstItem() + $i : $loop->iteration }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <div class="h-8 w-8 rounded-full bg-gray-200 flex items-center justify-center text-xs font-medium">
                                    {{ strtoupper(substr($row->archer_name ?? '',0,1)) }}
                                </div>
                                <div>
                                    <div class="font-medium text-gray-900">{{ $row->archer_name }}</div>
                                    <div class="text-xs text-gray-500">
                                        {{ ucfirst($row->bow_type) }}
                                        @if(!empty($row->mode)) • {{ ucfirst($row->mode) }} @endif
                                        @if(!empty($row->round_name)) • {{ $row->round_name }} @endif
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-right font-semibold">{{ number_format($row->R ?? 0, 2) }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format($row->PI ?? 0, 3) }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format($row->Elo ?? 0, 0) }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format($row->AAE ?? 0, 3) }}</td>
                        <td class="px-4 py-3 text-right">{{ isset($row->X_rate) ? number_format($row->X_rate*100, 1).'%' : '—' }}</td>
                        <td class="px-4 py-3 text-right">{{ isset($row->ten_rate) ? number_format($row->ten_rate*100, 1).'%' : '—' }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format($row->sigma ?? 0, 3) }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format($row->best_90d ?? 0, 0) }}</td>
                        <td class="px-4 py-3 text-right text-gray-600">
                            @if(!empty($row->last_active))
                                {{ \Carbon\Carbon::parse($row->last_active)->diffForHumans() }}
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" class="px-4 py-16">
                            <div class="flex flex-col items-center justify-center text-center">
                                <div class="h-12 w-12 rounded-full bg-gray-100 flex items-center justify-center mb-3">🏹</div>
                                <p class="font-medium text-gray-900">目前沒有符合條件的射手</p>
                                <p class="text-sm text-gray-500">調整上方過濾條件或稍後再試。</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{-- Mobile Cards --}}
        <div class="md:hidden space-y-3">
            @forelse($leaders as $i => $row)
                <div class="rounded-2xl border p-4">
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-3">
                            <div class="h-10 w-10 rounded-full bg-gray-200 flex items-center justify-center text-sm font-medium">
                                {{ strtoupper(substr($row->archer_name ?? '',0,1)) }}
                            </div>
                            <div>
                                <div class="text-sm font-semibold">{{ $row->archer_name }}</div>
                                <div class="text-xs text-gray-500">
                                    #{{ method_exists($leaders,'firstItem') ? $leaders->firstItem() + $i : $loop->iteration }}
                                    • {{ ucfirst($row->bow_type) }}
                                    @if(!empty($row->mode)) • {{ ucfirst($row->mode) }} @endif
                                    @if(!empty($row->round_name)) • {{ $row->round_name }} @endif
                                </div>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-xs text-gray-500">R</div>
                            <div class="text-base font-bold">{{ number_format($row->R ?? 0, 2) }}</div>
                        </div>
                    </div>

                    <dl class="mt-3 grid grid-cols-3 gap-2 text-xs">
                        <div class="rounded-xl bg-gray-50 p-2">
                            <dt class="text-gray-500">PI</dt><dd class="font-medium">{{ number_format($row->PI ?? 0, 3) }}</dd>
                        </div>
                        <div class="rounded-xl bg-gray-50 p-2">
                            <dt class="text-gray-500">Elo</dt><dd class="font-medium">{{ number_format($row->Elo ?? 0, 0) }}</dd>
                        </div>
                        <div class="rounded-xl bg-gray-50 p-2">
                            <dt class="text-gray-500">AAE</dt><dd class="font-medium">{{ number_format($row->AAE ?? 0, 3) }}</dd>
                        </div>
                        <div class="rounded-xl bg-gray-50 p-2">
                            <dt class="text-gray-500">X%</dt>
                            <dd class="font-medium">{{ isset($row->X_rate) ? number_format($row->X_rate*100, 1).'%' : '—' }}</dd>
                        </div>
                        <div class="rounded-xl bg-gray-50 p-2">
                            <dt class="text-gray-500">10%</dt>
                            <dd class="font-medium">{{ isset($row->ten_rate) ? number_format($row->ten_rate*100, 1).'%' : '—' }}</dd>
                        </div>
                        <div class="rounded-xl bg-gray-50 p-2">
                            <dt class="text-gray-500">σ</dt><dd class="font-medium">{{ number_format($row->sigma ?? 0, 3) }}</dd>
                        </div>
                    </dl>

                    <div class="mt-3 flex items-center justify-between text-xs text-gray-500">
                        <div>Best(90d): <span class="font-medium text-gray-800">{{ number_format($row->best_90d ?? 0, 0) }}</span></div>
                        <div>
                            @if(!empty($row->last_active))
                                {{ \Carbon\Carbon::parse($row->last_active)->diffForHumans() }}
                            @else
                                —
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-2xl border p-8 text-center">
                    <div class="mb-3 text-2xl">🏹</div>
                    <p class="font-medium text-gray-900">目前沒有符合條件的射手</p>
                    <p class="text-sm text-gray-500">調整上方過濾條件或稍後再試。</p>
                </div>
            @endforelse
        </div>

        {{-- Pagination --}}
        @if(method_exists($leaders,'links'))
            <div class="mt-6">{{ $leaders->withQueryString()->links() }}</div>
        @endif
    </div>


{{-- ===== 名詞解釋：右下角說明按鈕＋Modal ===== --}}
<div id="glossary-modal" class="fixed inset-0 z-[9998] hidden" role="dialog" aria-modal="true" aria-labelledby="glossary-title">
        {{-- 遮罩 --}}
        <div class="absolute inset-0 bg-black/40" data-glossary-close></div>

        {{-- 置中容器（含 padding） --}}
        <div class="fixed inset-0 flex items-start justify-center p-4 sm:p-6">
            {{-- 視窗本體：手機近全螢幕、桌機 80vh；內部用 flex-col + overflow-hidden --}}
            <div data-glossary-panel
                 class="w-full sm:max-w-2xl bg-white rounded-none sm:rounded-2xl shadow-2xl ring-1 ring-black/5
                max-h-[100vh] sm:max-h-[80vh] flex flex-col overflow-hidden">
                {{-- 標題列 sticky，關閉鈕 --}}
                <div class="sticky top-0 flex items-center justify-between gap-2 px-5 py-3 border-b bg-white/95 backdrop-blur">
                    <h2 id="glossary-title" class="text-base sm:text-lg font-semibold">排行榜名詞解釋</h2>
                    <button type="button" class="rounded-lg p-2 text-gray-500 hover:bg-gray-100" aria-label="關閉" data-glossary-close>✕</button>
                </div>

                {{-- 內容區：可以很長，這裡會出捲軸 --}}
                <div class="px-5 py-4 overflow-y-auto overscroll-contain">
                    <dl class="space-y-3">
                        <div class="rounded-xl bg-gray-50 p-3">
                            <dt class="font-medium">R（綜合分）</dt>
                            <dd class="mt-1 text-sm text-gray-600"><span class="font-mono">R = 0.7 × PI + 0.3 × Elo</span>，最終排名用分數。</dd>
                        </div>
                        <div class="rounded-xl bg-gray-50 p-3">
                            <dt class="font-medium">PI（射擊品質指標）</dt>
                            <dd class="mt-1 text-sm text-gray-600"><span class="font-mono">≈ 0.55×(AAE/10) + 0.25×X% + 0.20×10%</span>，反映每箭表現與中靶率。</dd>
                        </div>
                        <div class="rounded-xl bg-gray-50 p-3">
                            <dt class="font-medium">AAE（平均每箭分）</dt>
                            <dd class="mt-1 text-sm text-gray-600">總分 ÷ 箭數，越高越好。</dd>
                        </div>
                        <div class="rounded-xl bg-gray-50 p-3">
                            <dt class="font-medium">Elo（對戰等級分）</dt>
                            <dd class="mt-1 text-sm text-gray-600">依勝負與分差調整的等級分，越高代表競賽實力越強。</dd>
                        </div>
                        <div class="rounded-xl bg-gray-50 p-3">
                            <dt class="font-medium">X%</dt>
                            <dd class="mt-1 text-sm text-gray-600">X 環數 ÷ 箭數。</dd>
                        </div>
                        <div class="rounded-xl bg-gray-50 p-3">
                            <dt class="font-medium">10%</dt>
                            <dd class="mt-1 text-sm text-gray-600">10 環數 ÷ 箭數。</dd>
                        </div>
                        <div class="rounded-xl bg-gray-50 p-3">
                            <dt class="font-medium">σ（穩定度）</dt>
                            <dd class="mt-1 text-sm text-gray-600">每箭分數標準差（越小越穩）。</dd>
                        </div>
                        <div class="rounded-xl bg-gray-50 p-3">
                            <dt class="font-medium">Best (90d)</dt>
                            <dd class="mt-1 text-sm text-gray-600">最近 90 天的單場最高總分。</dd>
                        </div>
                        <div class="rounded-xl bg-gray-50 p-3">
                            <dt class="font-medium">Last Active</dt>
                            <dd class="mt-1 text-sm text-gray-600">最近參賽或有成績紀錄的時間。</dd>
                        </div>

                        {{-- 你要再加更多條目，直接往下貼就行；超長也會在這裡出捲軸 --}}
                    </dl>
                </div>
            </div>
        </div>
    </div>

    {{-- 控制腳本：鎖背景捲動＋簡單焦點圈（Tab 迴圈） --}}
    <script>
        (function () {
            const openBtn = document.getElementById('glossary-open');
            const modal = document.getElementById('glossary-modal');
            const panel = modal?.querySelector('[data-glossary-panel]');
            const html = document.documentElement;
            let lastFocus = null;

            const getFocusables = (root) =>
                root.querySelectorAll('a[href], button:not([disabled]), textarea, input, select, [tabindex]:not([tabindex="-1"])');

            function open() {
                lastFocus = document.activeElement;
                modal.classList.remove('hidden');
                html.classList.add('overflow-hidden'); // 鎖背景捲動
                const f = getFocusables(panel);
                (f[0] || panel).focus();
            }

            function close() {
                modal.classList.add('hidden');
                html.classList.remove('overflow-hidden');
                lastFocus?.focus();
            }

            openBtn?.addEventListener('click', open);
            modal?.addEventListener('click', (e) => {
                if (e.target.matches('[data-glossary-close], [data-glossary-close] *') || e.target === modal.firstElementChild) close();
            });
            document.addEventListener('keydown', (e) => {
                if (modal.classList.contains('hidden')) return;
                if (e.key === 'Escape') { e.preventDefault(); close(); }
                if (e.key === 'Tab') {
                    const f = Array.from(getFocusables(panel));
                    if (!f.length) return;
                    const first = f[0], last = f[f.length - 1];
                    if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
                    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
                }
            });
        })();
    </script>
    {{-- ===== /名詞解釋 ===== --}}

@endsection
