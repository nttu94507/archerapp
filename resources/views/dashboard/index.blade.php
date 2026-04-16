{{-- resources/views/dashboard/index.blade.php --}}
@extends('layouts.app') {{-- 依你的專案調整 --}}

@section('title', 'ArrowTrack — Dashboard')

@section('content')
    @php
        // ======== 假資料（之後用 Controller 填入真資料） ========
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
        ];
        // ======== /假資料 ========

    @endphp

    {{-- 放在 @section('content') 裡面，建議把原本內容包成 @auth ... @endauth --}}
    @guest
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-10">
            {{-- Hero / 嘲諷挑性宣傳 --}}
            <div class="relative overflow-hidden rounded-3xl border bg-gradient-to-b from-white to-gray-50 p-6 sm:p-10">
                <div class="max-w-3xl">
                    <h1 class="text-3xl sm:text-4xl font-extrabold leading-tight tracking-tight">
                        還在靠運氣射箭??
                    </h1>
                    <p class="mt-3 text-gray-600 text-base sm:text-lg">
                        你說「今天手感超好」；數據說：<span class="font-semibold">別嘴硬。</span>
                        只要登入，<span class="font-semibold">平均單箭分、X% 、連續天數</span>直接打臉你的錯覺——
                        用數據長進，比用藉口舒服多了。
                    </p>



                    <div class="mt-4 text-xs text-gray-500">
                        可隨時刪除資料｜支援手機與桌機
                    </div>
                </div>

                {{-- 右側假圖：手機版（文案下方顯示） --}}
                <div class="mt-6 sm:hidden">
                    <div class="h-56 w-full max-w-md rounded-2xl border bg-white shadow-xl p-4 mx-auto">
                        <div class="text-xs text-gray-500 mb-2">ArrowTrack 展示</div>
                        <div class="grid grid-cols-3 gap-2">
                            <div class="rounded-xl border p-3">
                                <div class="text-[10px] text-gray-500">AAE</div>
                                <div class="text-xl font-bold">8.42</div>
                                <div class="mt-1 h-2 rounded-full bg-gray-100">
                                    <div class="h-2 rounded-full bg-gray-900" style="width:78%"></div>
                                </div>
                            </div>
                            <div class="rounded-xl border p-3">
                                <div class="text-[10px] text-gray-500">X%</div>
                                <div class="text-xl font-bold">36%</div>
                                <div class="mt-1 text-[10px] text-emerald-700">↑ 4.2%</div>
                            </div>
                            <div class="rounded-xl border p-3">
                                <div class="text-[10px] text-gray-500">Streak</div>
                                <div class="text-xl font-bold">7</div>
                                <div class="mt-1 text-[10px] text-gray-500">天</div>
                            </div>
                            <div class="col-span-3 rounded-xl border p-3">
                                <div class="text-[10px] text-gray-500 mb-1">最近 8 週</div>
                                <div class="h-24 w-full bg-[linear-gradient(180deg,#000_2px,transparent_2px)] bg-[length:100%_24px]">
                                    <div class="flex items-end gap-2 h-full">
                                        @for($i=0;$i<12;$i++)
                                            @php $h = rand(20,90); @endphp
                                            <div class="w-4 bg-gray-900/80 rounded-t" style="height: {{ $h }}%"></div>
                                        @endfor
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-6 flex flex-col sm:flex-row gap-3">
                    <a href="{{ route('login.options') }}" class="inline-flex items-center justify-center rounded-xl bg-gray-900 px-5 py-3 text-sm font-semibold text-white hover:bg-gray-800">
                        立即登入
                    </a>
                </div>

                {{-- 右側假圖：桌機版（維持絕對定位） --}}
                <div class="pointer-events-none absolute -right-6 -bottom-6 hidden sm:block">
                    <div class="h-56 w-96 rounded-2xl border bg-white shadow-xl p-4">
                        <div class="text-xs text-gray-500 mb-2">ArrowTrack 展示</div>
                        <div class="grid grid-cols-3 gap-2">
                            <div class="rounded-xl border p-3">
                                <div class="text-[10px] text-gray-500">AAE</div>
                                <div class="text-xl font-bold">8.42</div>
                                <div class="mt-1 h-2 rounded-full bg-gray-100">
                                    <div class="h-2 rounded-full bg-gray-900" style="width:78%"></div>
                                </div>
                            </div>
                            <div class="rounded-xl border p-3">
                                <div class="text-[10px] text-gray-500">X%</div>
                                <div class="text-xl font-bold">36%</div>
                                <div class="mt-1 text-[10px] text-emerald-700">↑ 4.2%</div>
                            </div>
                            <div class="rounded-xl border p-3">
                                <div class="text-[10px] text-gray-500">Streak</div>
                                <div class="text-xl font-bold">7</div>
                                <div class="mt-1 text-[10px] text-gray-500">天</div>
                            </div>
                            <div class="col-span-3 rounded-xl border p-3">
                                <div class="text-[10px] text-gray-500 mb-1">最近 8 週</div>
                                <div class="h-24 w-full bg-[linear-gradient(180deg,#000_2px,transparent_2px)] bg-[length:100%_24px]">
                                    <div class="flex items-end gap-2 h-full">
                                        @for($i=0;$i<12;$i++)
                                            @php $h = rand(20,90); @endphp
                                            <div class="w-4 bg-gray-900/80 rounded-t" style="height: {{ $h }}%"></div>
                                        @endfor
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 快速痛點 → 功能亮點 --}}
            <div class="mt-10 grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="rounded-2xl border p-5">
                    <div class="text-sm font-semibold">還在「感覺」訓練？</div>
                    <p class="mt-1 text-sm text-gray-600">你說今天9成好箭；實際只有 28% Gold。<span class="font-medium">登入</span>之後，嘴硬變硬實力。</p>
                </div>
                <div class="rounded-2xl border p-5">
                    <div class="text-sm font-semibold">一鍵看到弱點</div>
                    <p class="mt-1 text-sm text-gray-600">AAE、X/10、σ 一次到位。出手不穩？<span class="font-medium">數據先說話</span>，動作再調整。</p>
                </div>
                <div class="rounded-2xl border p-5">
                    <div class="text-sm font-semibold">連續挑戰，破個人榜</div>
                    <p class="mt-1 text-sm text-gray-600">Streak 斷了？別裝忙。每天 20 分鐘，換來你想要的 330+。</p>
                </div>
            </div>

            {{-- 對比表（嘲諷但克制） --}}
            <div class="mt-8 rounded-2xl border p-5">
                <div class="text-sm font-semibold mb-3">為什麼不要再用紙本筆記</div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-3 py-2 text-left">項目</th>
                            <th class="px-3 py-2 text-left">紙本 </th>
                            <th class="px-3 py-2 text-left">ArrowTrack</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y">
                        <tr>
                            <td class="px-3 py-2">AAE (單箭平均) / X% / 連續日</td>
                            <td class="px-3 py-2 text-gray-500">自己算、常忘記</td>
                            <td class="px-3 py-2"><span class="font-medium">自動</span>匯總、週月季一把抓</td>
                        </tr>
                        <tr>
                            <td class="px-3 py-2">弱點識別</td>
                            <td class="px-3 py-2 text-gray-500">今天怪風、明天怪箭</td>
                            <td class="px-3 py-2">用數據打臉藉口，<span class="font-medium">準心回正</span></td>
                        </tr>
                        <tr>
                            <td class="px-3 py-2">成就感</td>
                            <td class="px-3 py-2 text-gray-500">憑感覺爽一下</td>
                            <td class="px-3 py-2">徽章 / 里程碑，<span class="font-medium">持續爽</span></td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- 社群背書 / 數字卡位（可替換為真數字） --}}
            <div class="mt-8 grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="rounded-xl border p-4 text-center">
                    <div class="text-2xl font-bold">10K+</div>
                    <div class="text-xs text-gray-500 mt-1">總場次紀錄</div>
                </div>
                <div class="rounded-xl border p-4 text-center">
                    <div class="text-2xl font-bold">8.2 → 8.7</div>
                    <div class="text-xs text-gray-500 mt-1">平均單箭分成長（90 天）</div>
                </div>
                <div class="rounded-xl border p-4 text-center">
                    <div class="text-2xl font-bold">38%</div>
                    <div class="text-xs text-gray-500 mt-1">X 命中率里程碑</div>
                </div>
                <div class="rounded-xl border p-4 text-center">
                    <div class="text-2xl font-bold">14 天</div>
                    <div class="text-xs text-gray-500 mt-1">連續訓練挑戰</div>
                </div>
            </div>

            {{-- 再次 CTA --}}
            <div class="mt-8 flex flex-col sm:flex-row gap-3">
                <a href="{{ route('login.options') }}" class="inline-flex items-center justify-center rounded-xl bg-gray-900 px-5 py-3 text-sm font-semibold text-white hover:bg-gray-800">
                    我準備好了，帶我登入
                </a>
            </div>

            {{-- 隱私 / 說明 --}}
            <div class="mt-4 text-xs text-gray-500">
                我們只用你的資料產生統計，不賣資料不亂發通知。<br class="hidden sm:block">
                你負責專注把箭射好，我們負責把數字算好。
            </div>

        </div>
    @endguest

    @auth()
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">
        {{-- Page Header --}}
        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    @auth
                        歡迎回來，{{ auth()->user()->display_name ?? '夥伴' }}
                    @else
                        嗨嗨！神射手
                    @endauth
                </h1>
            </div>
            <div class="flex gap-2">
                <a href="{{route('scores.setup')}}" class="inline-flex items-center rounded-xl bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800">＋開始訓練</a>
            </div>
        </div>

        {{-- ===== 月結指標 ===== --}}
        @php
            // 安全換算
            $fmtNum = function($v, $dec=0){ return number_format((float)$v, $dec); };
            $pct    = function($v){ return number_format($v*100, 1) . '%'; };

            /**
             * 回傳：
             * - textMain：主數字（cur）
             * - textDelta：變化字串（↑/↓ + % 或 百分點或 絕對值）
             * - cls：顏色（漲→emerald、跌→rose、持平→gray）
             */
            function month_delta($cur, $prev, $mode='pct', $invert=false, $fmt=0) {
                $cur  = (float)$cur; $prev = (float)$prev;
                $delta = $cur - $prev;
                $dir = $delta == 0 ? 0 : ($delta > 0 ? 1 : -1);
                // 對於 invert（如 σ 越低越好），方向顛倒
                $good = $invert ? -$dir : $dir;

                $cls = $dir === 0 ? 'text-gray-600' : ($good > 0 ? 'text-emerald-700' : 'text-rose-700');
                $arrow = $dir === 0 ? '—' : ($dir > 0 ? '↑' : '↓');

                $main = number_format($cur, $fmt);

                if ($mode === 'pct') {
                    $pct = $prev == 0 ? null : ($delta / max(abs($prev), 1e-9) * 100);
                    $deltaText = is_null($pct) ? '—' : $arrow . number_format(abs($pct), 1) . '%';
                } elseif ($mode === 'pp') { // 百分點（for 率）
                    $pp = ($cur - $prev) * 100;
                    $deltaText = $arrow . number_format(abs($pp), 1) . ' pp';
                } elseif ($mode === 'both') { // 同時顯示絕對與 %
                    $pct = $prev == 0 ? null : ($delta / max(abs($prev), 1e-9) * 100);
                    $deltaText = ($arrow . number_format(abs($delta), $fmt)) . (is_null($pct) ? '' : '｜' . number_format(abs($pct),1) . '%');
                } else { // abs
                    $deltaText = $arrow . number_format(abs($delta), $fmt);
                }
                return compact('main','deltaText','cls');
            }
        @endphp

        @if(!empty($monthlyIndex) && is_array($monthlyIndex))
            <div class="mb-2 flex items-center justify-between">
                <h2 class="text-sm font-semibold">月指標</h2>
                <div class="text-xs text-gray-500">
                    @php
                        $cm = \Carbon\Carbon::now()->format('Y/m');
                        $pm = \Carbon\Carbon::now()->subMonthNoOverflow()->format('Y/m');
                    @endphp
                    比較期間：{{ $cm }} vs {{ $pm }}
                </div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 sm:gap-4 mb-6">
                @foreach($monthlyIndex as $key => $row)
                    @php
                        $res = month_delta($row['cur'] ?? 0, $row['prev'] ?? 0, $row['mode'] ?? 'pct', $row['invert'] ?? false, $row['fmt'] ?? 0);
                        $subtitle = match($row['mode'] ?? 'pct') {
                            'pct'  => '月增率',
                            'pp'   => '變動（百分點）',
                            'both' => '本月｜月增率',
                            default=> '本月變動'
                        };
                        $valueText = ($row['mode'] ?? 'pct') === 'pp'
                                    ? number_format(($row['cur'] ?? 0)*100, 1) . '%'
                                    : number_format($row['cur'] ?? 0, $row['fmt'] ?? 0);
                    @endphp
                    <div class="rounded-2xl border p-4">
                        <div class="text-xs text-gray-500">{{ $row['label'] }}</div>
                        <div class="mt-1 text-xl font-semibold">{{ $valueText }}</div>
                        <div class="mt-1 text-xs">
                            <span class="text-gray-500">{{ $subtitle }}：</span>
                            <span class="{{ $res['cls'] }}">{{ $res['deltaText'] }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
        {{-- ===== /月結指標 ===== --}}

        <div class="mt-6">
            {{-- 每月訓練箭數曲線（6 個月） --}}
            <div class="rounded-2xl border p-4">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-sm font-semibold">每月訓練箭數（近半年）</h2>
                    <div class="text-xs text-gray-500">過去 6 個月</div>
                </div>
                @php
                    $halfYearArrowTrend = $halfYearArrowTrend ?? [];
                    $maxArrows = max(1, collect($halfYearArrowTrend)->max('arrows') ?? 0);
                    $chartWidth = 720;
                    $chartHeight = 220;
                    $paddingX = 24;
                    $paddingY = 18;
                    $pointCount = max(1, count($halfYearArrowTrend));
                    $usableWidth = $chartWidth - ($paddingX * 2);
                    $usableHeight = $chartHeight - ($paddingY * 2);
                    $stepX = $pointCount > 1 ? ($usableWidth / ($pointCount - 1)) : 0;
                    $points = collect($halfYearArrowTrend)->values()->map(function ($point, $idx) use ($paddingX, $paddingY, $usableHeight, $stepX, $maxArrows) {
                        $x = $paddingX + ($stepX * $idx);
                        $y = $paddingY + $usableHeight - (($point['arrows'] / $maxArrows) * $usableHeight);

                        return [
                            'x' => round($x, 1),
                            'y' => round($y, 1),
                            'month' => $point['month'],
                            'month_key' => $point['month_key'],
                            'arrows' => $point['arrows'],
                        ];
                    });
                    $polyline = $points->map(fn ($p) => $p['x'] . ',' . $p['y'])->implode(' ');
                @endphp
                <div class="w-full">
                    <div class="w-full">
                        <svg viewBox="0 0 {{ $chartWidth }} {{ $chartHeight }}" class="w-full h-auto" role="img" aria-label="每月訓練箭數曲線圖">
                            <line x1="{{ $paddingX }}" y1="{{ $chartHeight - $paddingY }}" x2="{{ $chartWidth - $paddingX }}" y2="{{ $chartHeight - $paddingY }}" stroke="#d1d5db" stroke-width="1" />
                            <line x1="{{ $paddingX }}" y1="{{ $paddingY }}" x2="{{ $paddingX }}" y2="{{ $chartHeight - $paddingY }}" stroke="#d1d5db" stroke-width="1" />
                            @foreach([0.25, 0.5, 0.75, 1.0] as $ratio)
                                @php $yGuide = $paddingY + $usableHeight - ($usableHeight * $ratio); @endphp
                                <line x1="{{ $paddingX }}" y1="{{ $yGuide }}" x2="{{ $chartWidth - $paddingX }}" y2="{{ $yGuide }}" stroke="#e5e7eb" stroke-dasharray="3 4" stroke-width="1" />
                            @endforeach

                            @if($polyline !== '')
                                <polyline points="{{ $polyline }}" fill="none" stroke="#111827" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" />
                            @endif

                            @foreach($points as $point)
                                <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="3.5" fill="#111827">
                                    <title>{{ $point['month_key'] }}：{{ $point['arrows'] }} 箭</title>
                                </circle>
                                <text x="{{ $point['x'] }}" y="{{ $chartHeight - 2 }}" text-anchor="middle" font-size="10" fill="#4b5563">{{ $point['month'] }}</text>
                            @endforeach
                        </svg>
                    </div>
                </div>
                <div class="mt-3 text-xs text-gray-500">曲線代表每個月訓練箭數，方便觀察半年內訓練量變化。</div>
            </div>
        <div class="mt-6 rounded-2xl border border-dashed p-6 text-center">
            <h2 class="text-base font-semibold">成就系統已回歸</h2>
            <p class="mt-2 text-sm text-gray-600">已提供完整成就清單，前往成就頁查看各項達成進度。</p>
            <a href="{{ route('achievements.index') }}" class="mt-3 inline-flex items-center rounded-lg bg-gray-900 px-3 py-2 text-xs font-medium text-white hover:bg-gray-800">查看成就</a>
        </div>
    </div>
    @endauth
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

