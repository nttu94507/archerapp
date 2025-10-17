{{-- resources/views/dashboard/index.blade.php --}}
@extends('layouts.app') {{-- 依你的專案調整 --}}

@section('title', 'ArrowTrack — Dashboard')

@section('content')
    @php
        // ======== 假資料（之後用 Controller 填入真資料） ========
        $user = [
            'name' => 'Alex',
            'bow_type' => 'Recurve',
        ];
        $stats = [
            'first_session_at' => '2023/08/12',
            'days_since_start' => 432,
            'active_days_this_month' => 9,
            'hours_this_month' => 12.8,
            'arrows_this_month' => 1140,
            'avg_score_per_arrow' => 8.23,
            'streak_days' => 4,
            'best_end' => 58, // 單趟 6 箭
            'best_36' => 321,
            'gold_rate' => 0.34,
            'red_rate' => 0.47,
            'last_active' => '2 天前',
        ];
        $weeklyTrend = [
            ['week' => 'W35', 'arrows' => 420, 'avg' => 8.1, 'mins' => 340],
            ['week' => 'W36', 'arrows' => 360, 'avg' => 8.0, 'mins' => 300],
            ['week' => 'W37', 'arrows' => 510, 'avg' => 8.3, 'mins' => 390],
            ['week' => 'W38', 'arrows' => 480, 'avg' => 8.4, 'mins' => 370],
            ['week' => 'W39', 'arrows' => 600, 'avg' => 8.5, 'mins' => 420],
            ['week' => 'W40', 'arrows' => 540, 'avg' => 8.6, 'mins' => 410],
            ['week' => 'W41', 'arrows' => 450, 'avg' => 8.2, 'mins' => 360],
            ['week' => 'W42', 'arrows' => 510, 'avg' => 8.3, 'mins' => 380],
        ];
        $recentSessions = [
            ['date' => '2025/10/15', 'location' => 'Indoor A', 'arrows' => 180, 'avg' => 8.4, 'score' => 1510, 'wind' => '—', 'notes' => '專注呼吸節奏'],
            ['date' => '2025/10/12', 'location' => 'Outdoor 30m', 'arrows' => 144, 'avg' => 8.1, 'score' => 1167, 'wind' => '2.3 m/s', 'notes' => '順風左飄，調整瞄準'],
            ['date' => '2025/10/10', 'location' => 'Indoor B', 'arrows' => 120, 'avg' => 8.6, 'score' => 1032, 'wind' => '—', 'notes' => '拉弓穩定度提升'],
            ['date' => '2025/10/07', 'location' => 'Outdoor 50m', 'arrows' => 90,  'avg' => 7.9, 'score' => 711,  'wind' => '3.1 m/s', 'notes' => '注意扳指角度'],
        ];
        $goals = [
            ['title' => '36 箭 ≥ 330', 'progress' => 0.74, 'due' => '2025/12/31'],
            ['title' => '連續訓練 14 天', 'progress' => $stats['streak_days'] / 14, 'due' => '—'],
            ['title' => 'X% ≥ 38%', 'progress' => $stats['gold_rate'] < 0.38 ? $stats['gold_rate']/0.38 : 1, 'due' => '2026/03/31'],
        ];
        $notes = [
            ['tag' => '站姿', 'text' => '腳尖對準靶心微內扣，骨架撐住重心。'],
            ['tag' => '放箭', 'text' => '放鬆後臂，手肘沿線後移，不要側甩。'],
            ['tag' => '瞄準', 'text' => '呼吸停在第二拍，穩住 0.8 秒再放。'],
        ];
        $badges = [
            ['icon' => '🔥', 'title' => '7-Day Streak'],
            ['icon' => '🎯', 'title' => '1000 Arrows a Day'],
            ['icon' => '🏆', 'title' => 'Best End 58'],
        ];
        // ======== /假資料 ========

        $pct = fn($v) => number_format($v*100, 0) . '%';
    @endphp

    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">
        {{-- Page Header --}}
        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">歡迎回來，{{ $user['name'] }}</h1>
{{--                <p class="text-sm text-gray-500"><span class="font-medium"></span>。以下為你的練習概況。</p>--}}
            </div>
            <div class="flex gap-2">
                <a href="#" class="inline-flex items-center rounded-xl border px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">匯出月報</a>
                <a href="#" class="inline-flex items-center rounded-xl bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800">＋開始訓練</a>
            </div>
        </div>

        {{-- KPI Cards --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 sm:gap-4 mb-6">
            @include('components.kpi', [
              'title' => '開始日期',
              'value' => $stats['first_session_at'],
              'hint'  => '已 ' . $stats['days_since_start'] . ' 天'
            ])
            @include('components.kpi', [
              'title' => '本月練習天數',
              'value' => $stats['active_days_this_month']
            ])
            @include('components.kpi', [
              'title' => '本月總時長',
              'value' => number_format($stats['hours_this_month'], 1) . ' h'
            ])
            @include('components.kpi', [
              'title' => '本月總箭數',
              'value' => $stats['arrows_this_month']
            ])
            @include('components.kpi', [
              'title' => '平均單箭分',
              'value' => number_format($stats['avg_score_per_arrow'], 2)
            ])
            @include('components.kpi', [
              'title' => '連續天數',
              'value' => $stats['streak_days'] . ' d'
            ])
        </div>


        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            {{-- Trend Chart (fake) --}}
            <div class="rounded-2xl border p-4 lg:col-span-2">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-sm font-semibold">最近 8 週趨勢</h2>
                    <div class="text-xs text-gray-500">箭數 / 平均分 / 時長</div>
                </div>
                {{-- 無外部圖表庫：以純 CSS 長條 + 線條模擬 --}}
                <div class="overflow-x-auto">
                    <div class="min-w-[640px] grid grid-cols-8 gap-3">
                        @foreach($weeklyTrend as $w)
                            @php
                                $hArrows = min(200, $w['arrows'] / 3);  // 600 arrows -> 200px
                                $hMins   = min(200, $w['mins']   / 2);  // 400 mins -> 200px
                                $posAvg  = 200 - ($w['avg'] * 20);      // 10 -> top 0px
                            @endphp
                            <div class="flex flex-col items-center">
                                <div class="relative h-[200px] w-12">
                                    <div class="absolute bottom-0 left-0 right-0 rounded-t bg-gray-200" style="height: {{ $hArrows }}px" title="Arrows: {{ $w['arrows'] }}"></div>
                                    <div class="absolute bottom-0 left-2 right-2 rounded-t bg-gray-400/60" style="height: {{ $hMins }}px" title="Mins: {{ $w['mins'] }}"></div>
                                    <div class="absolute left-0 right-0 h-[2px] bg-gray-900/80" style="top: {{ $posAvg }}px" title="Avg: {{ $w['avg'] }}"></div>
                                </div>
                                <div class="mt-2 text-xs text-gray-600">{{ $w['week'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="mt-3 text-xs text-gray-500">說明：灰深＝時長、灰淺＝箭數、黑線＝平均單箭分。</div>
            </div>

            {{-- Notes / Coach To-Dos --}}
            <div class="rounded-2xl border p-4">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-sm font-semibold">教練／自我備忘</h2>
                    <a href="#" class="text-xs text-gray-500 hover:underline">管理</a>
                </div>
                <ul class="space-y-2">
                    @foreach($notes as $n)
                        <li class="rounded-xl bg-gray-50 p-3">
                            <div class="text-xs text-gray-500">{{ $n['tag'] }}</div>
                            <div class="text-sm">{{ $n['text'] }}</div>
                        </li>
                    @endforeach
                </ul>
                <div class="mt-3 text-xs text-gray-500">最近活動：{{ $stats['last_active'] }}</div>
            </div>
        </div>

        <div class="mt-6 grid grid-cols-1 lg:grid-cols-3 gap-4">
            {{-- Recent Sessions Table --}}
            <div class="rounded-2xl border p-4 lg:col-span-2 overflow-x-auto">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-sm font-semibold">最近訓練</h2>
                    <a href="#" class="text-xs text-gray-500 hover:underline">查看全部</a>
                </div>
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-3 py-2 text-left">日期</th>
                        <th class="px-3 py-2 text-left">場地</th>
                        <th class="px-3 py-2 text-right">箭數</th>
                        <th class="px-3 py-2 text-right">平均分</th>
                        <th class="px-3 py-2 text-right">總分</th>
                        <th class="px-3 py-2 text-left">風速</th>
                        <th class="px-3 py-2 text-left">備註</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y">
                    @foreach($recentSessions as $s)
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 py-2">{{ $s['date'] }}</td>
                            <td class="px-3 py-2">{{ $s['location'] }}</td>
                            <td class="px-3 py-2 text-right">{{ $s['arrows'] }}</td>
                            <td class="px-3 py-2 text-right">{{ number_format($s['avg'], 2) }}</td>
                            <td class="px-3 py-2 text-right">{{ number_format($s['score']) }}</td>
                            <td class="px-3 py-2">{{ $s['wind'] }}</td>
                            <td class="px-3 py-2">{{ $s['notes'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Goals / Progress --}}
            <div class="rounded-2xl border p-4">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-sm font-semibold">目標與進度</h2>
                    <a href="#" class="text-xs text-gray-500 hover:underline">設定</a>
                </div>
                <ul class="space-y-3">
                    @foreach($goals as $g)
                        <li>
                            <div class="flex items-center justify-between text-sm">
                                <div class="font-medium">{{ $g['title'] }}</div>
                                <div class="text-xs text-gray-500">到期：{{ $g['due'] }}</div>
                            </div>
                            <div class="mt-2 h-2 w-full rounded-full bg-gray-100">
                                <div class="h-2 rounded-full bg-gray-900" style="width: {{ min(100, max(0, round($g['progress']*100))) }}%"></div>
                            </div>
                        </li>
                    @endforeach
                </ul>

                {{-- Badges --}}
                <div class="mt-5">
                    <h3 class="text-xs font-semibold text-gray-500 mb-2">成就徽章</h3>
                    <div class="flex flex-wrap gap-2">
                        @foreach($badges as $b)
                            <div class="inline-flex items-center gap-2 rounded-xl border px-3 py-2 text-xs">
                                <span class="text-base">{{ $b['icon'] }}</span>
                                <span class="font-medium">{{ $b['title'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- Mobile Quick Stats --}}
        <div class="mt-6 grid grid-cols-2 sm:hidden gap-2">
            <div class="rounded-xl border p-3 text-center">
                <div class="text-xs text-gray-500">Gold 率</div>
                <div class="text-base font-semibold">{{ $pct($stats['gold_rate']) }}</div>
            </div>
            <div class="rounded-xl border p-3 text-center">
                <div class="text-xs text-gray-500">Red 率</div>
                <div class="text-base font-semibold">{{ $pct($stats['red_rate']) }}</div>
            </div>
        </div>

        {{-- Glossary / 說明按鈕 --}}
        <div class="fixed bottom-6 right-6 z-50">
            <button id="dash-glossary-open" type="button" class="h-10 w-10 rounded-full bg-gray-900 text-white flex items-center justify-center shadow-lg">i</button>
        </div>
    </div>

    {{-- ===== 名詞解釋：Modal ===== --}}
    <div id="dash-glossary-modal" class="fixed inset-0 z-[9998] hidden" role="dialog" aria-modal="true" aria-labelledby="dash-glossary-title">
        <div class="absolute inset-0 bg-black/40" data-dash-glossary-close></div>
        <div class="fixed inset-0 flex items-start justify-center p-4 sm:p-6">
            <div data-dash-glossary-panel class="w-full sm:max-w-2xl bg-white rounded-none sm:rounded-2xl shadow-2xl ring-1 ring-black/5 max-h-[100vh] sm:max-h-[80vh] flex flex-col overflow-hidden">
                <div class="sticky top-0 flex items-center justify-between gap-2 px-5 py-3 border-b bg-white/95 backdrop-blur">
                    <h2 id="dash-glossary-title" class="text-base sm:text-lg font-semibold">儀表板名詞解釋</h2>
                    <button type="button" class="rounded-lg p-2 text-gray-500 hover:bg-gray-100" aria-label="關閉" data-dash-glossary-close>✕</button>
                </div>
                <div class="px-5 py-4 overflow-y-auto overscroll-contain">
                    <dl class="space-y-3">
                        <div class="rounded-xl bg-gray-50 p-3"><dt class="font-medium">平均單箭分 (AAE)</dt><dd class="mt-1 text-sm text-gray-600">總分 ÷ 箭數，越高越好。</dd></div>
                        <div class="rounded-xl bg-gray-50 p-3"><dt class="font-medium">Gold / Red 率</dt><dd class="mt-1 text-sm text-gray-600">Gold：約等於 9–10 環；Red：約等於 7–8 環。</dd></div>
                        <div class="rounded-xl bg-gray-50 p-3"><dt class="font-medium">Streak</dt><dd class="mt-1 text-sm text-gray-600">連續有訓練紀錄的天數。</dd></div>
                        <div class="rounded-xl bg-gray-50 p-3"><dt class="font-medium">最佳單趟 / 36 箭</dt><dd class="mt-1 text-sm text-gray-600">單趟 6 箭或 36 箭合計的個人最佳。</dd></div>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    {{-- 控制腳本：沿用排行榜的焦點管理寫法 --}}
    <script>
        (function () {
            const openBtn = document.getElementById('dash-glossary-open');
            const modal = document.getElementById('dash-glossary-modal');
            const panel = modal?.querySelector('[data-dash-glossary-panel]');
            const html = document.documentElement;
            let lastFocus = null;
            const getFocusables = (root) => root.querySelectorAll('a[href], button:not([disabled]), textarea, input, select, [tabindex]:not([tabindex="-1"])');
            function open() {
                lastFocus = document.activeElement;
                modal.classList.remove('hidden');
                html.classList.add('overflow-hidden');
                const f = getFocusables(panel); (f[0] || panel).focus();
            }
            function close() {
                modal.classList.add('hidden');
                html.classList.remove('overflow-hidden');
                lastFocus?.focus();
            }
            openBtn?.addEventListener('click', open);
            modal?.addEventListener('click', (e) => {
                if (e.target.matches('[data-dash-glossary-close], [data-dash-glossary-close] *') || e.target === modal.firstElementChild) close();
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
@endsection

{{-- ====== Blade 小元件：KPI 卡（可放在 resources/views/components/kpi.blade.php） ====== --}}
@once
    @push('components')
        @verbatim
            {{-- <x-kpi title="文字" :value="$value" :hint="$hint ?? null" /> --}}
        @endverbatim
    @endpush
@endonce

@php
    // 直接內嵌一個簡易 KPI 元件（若你已有 components，請改放 components 檔案）
@endphp
@if (!function_exists('render_kpi_component'))
    @php
        function render_kpi_component($title, $value, $hint = null) {
            echo '<div class="rounded-2xl border p-4"><div class="text-xs text-gray-500">'.e($title).'</div><div class="mt-1 text-xl font-semibold">'.e($value).'</div>'.($hint ? '<div class="mt-1 text-xs text-gray-500">'.e($hint).'</div>' : '').'</div>';
        }
    @endphp
@endif

@php
    // 提供 <x-kpi> 標籤的替代渲染方式（無需註冊 View Component）
@endphp
@once
    @push('scripts')
        <script>
            // 這裡預留，如需動態載入可使用。
        </script>
    @endpush
@endonce

{{-- Blade 指令替代：把 <x-kpi> 呼叫轉為 PHP 函式輸出 --}}
@php
    // 簡易的自定義指令：在這個檔內快速替代 <x-kpi>
@endphp
@php
    Blade::directive('kpi', function($expression) {
        // 用法：@kpi('標題', $value, 'hint 可選')
        return "<?php render_kpi_component(...[$expression]); ?>";
    });
@endphp

{{-- 把上方 <x-kpi .../> 改為 @kpi("開始日期", $stats['first_session_at'], '已 999 天') 的寫法也可以 --}}
