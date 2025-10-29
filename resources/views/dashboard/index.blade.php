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
                        歡迎回來，{{ auth()->user()->name ?? '夥伴' }}
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

