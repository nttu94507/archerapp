{{-- resources/views/events/index.blade.php --}}
@extends('layouts.app')

@section('title', '賽事列表')

@section('content')
    <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 py-8">

        {{-- Page Header --}}
        <div class="mb-4 flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-gray-900">賽事列表</h1>
                <p class="text-sm text-gray-500 mt-1">瀏覽、搜尋與管理所有賽事。</p>
            </div>
            <a href="{{ route('events.create') }}"
               class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                新增賽事
            </a>
        </div>

        {{-- Flash Messages --}}
        @if(session('success'))
            <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ session('error') }}
            </div>
        @endif

        {{-- Filters (collapsible) --}}
        <details
            class="mb-4 rounded-2xl border border-gray-200 bg-white shadow-sm" {{ request()->hasAny(['q','mode','verified','date_from','date_to']) ? 'open' : '' }}>
            <summary class="flex cursor-pointer items-center justify-between px-4 py-3">
                <div class="text-sm font-medium text-gray-800">篩選條件</div>
                <span class="text-xs text-gray-500">點擊展開/收合</span>
            </summary>
            <div class="border-t border-gray-100 p-4">
                {{-- 已選條件 chips --}}
                @php
                    $chips = [];
                    if(request('q')) $chips[] = '關鍵字：'.e(request('q'));
                    if(request('mode')) $chips[] = '類型：'.(request('mode')==='indoor'?'室內':'室外');
                    if(request()->filled('verified')) $chips[] = '驗證：'.(request('verified')==='1'?'是':'否');
                    if(request('date_from') || request('date_to')) $chips[] = '期間：'.(request('date_from') ?: '—').' ~ '.(request('date_to') ?: '—');
                @endphp
                @if($chips)
                    <div class="mb-3 flex flex-wrap gap-2">
                        @foreach($chips as $c)
                            <span
                                class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-xs text-gray-700">{{ $c }}</span>
                        @endforeach
                        <a href="{{ route('events.index') }}" class="text-xs text-indigo-600 hover:underline">清除</a>
                    </div>
                @endif

                <form method="GET" action="{{ route('events.index') }}" class="grid grid-cols-1 md:grid-cols-5 gap-3">
                    <div class="md:col-span-2">
                        <label for="q" class="block text-xs font-medium text-gray-600 mb-1">關鍵字</label>
                        <input type="text" id="q" name="q" value="{{ request('q') }}"
                               placeholder="搜尋賽事名稱 / 主辦單位 / 場地"
                               class="block w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label for="mode" class="block text-xs font-medium text-gray-600 mb-1">比賽類型</label>
                        <select id="mode" name="mode"
                                class="block w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">全部</option>
                            <option value="indoor" @selected(request('mode')==='indoor')>室內</option>
                            <option value="outdoor" @selected(request('mode')==='outdoor')>室外</option>
                        </select>
                    </div>

                    <div>
                        <label for="verified" class="block text-xs font-medium text-gray-600 mb-1">是否驗證</label>
                        <select id="verified" name="verified"
                                class="block w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">全部</option>
                            <option value="1" @selected(request('verified')==='1')>是</option>
                            <option value="0" @selected(request('verified')==='0')>否</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label for="date_from" class="block text-xs font-medium text-gray-600 mb-1">日期起</label>
                            <input type="date" id="date_from" name="date_from" value="{{ request('date_from') }}"
                                   class="block w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label for="date_to" class="block text-xs font-medium text-gray-600 mb-1">日期迄</label>
                            <input type="date" id="date_to" name="date_to" value="{{ request('date_to') }}"
                                   class="block w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                    </div>

                    <div class="md:col-span-5 mt-2 flex items-center justify-end gap-2">
                        <a href="{{ route('events.index') }}"
                           class="inline-flex items-center justify-center rounded-xl border px-3 py-2 text-xs sm:text-sm text-gray-700 hover:bg-gray-50">
                            清除條件
                        </a>
                        <button type="submit"
                                class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-3 py-2 text-xs sm:text-sm font-medium text-white hover:bg-indigo-500">
                            套用篩選
                        </button>
                    </div>
                </form>
            </div>
        </details>

        {{-- Table Card --}}
        <div class="rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden">
            @php
                // 預設用 start_date 排序
                $sort = request('sort','start_date');
                $dir  = request('dir','desc');
                $dirToggle = $dir === 'asc' ? 'desc' : 'asc';
                function sortLink($field, $label) {
                    $current = request('sort','start_date');
                    $isActive = $current === $field;
                    $dir = request('dir','desc');
                    $dirToggle = $dir === 'asc' ? 'desc' : 'asc';
                    $params = array_merge(request()->query(), ['sort' => $field, 'dir' => $isActive ? $dirToggle : 'asc']);
                    $url = request()->url() . '?' . http_build_query($params);
                    $arrow = $isActive ? ($dir === 'asc' ? '▲' : '▼') : '';
                    return '<a href="'.$url.'" class="hover:underline">'.$label.' <span class="text-gray-400">'.$arrow.'</span></a>';
                }
            @endphp

            <div class="max-h-[70vh] overflow-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50 text-[11px] uppercase text-gray-500 sticky top-0 z-10">
                    <tr>
                        <th class="px-3 py-2 text-left whitespace-nowrap">{!! sortLink('start_date','期間') !!}</th>
                        <th class="px-3 py-2 text-left">賽事</th>
                        <th class="px-3 py-2 text-left hidden md:table-cell">{!! sortLink('organizer','主辦') !!}</th>
                        {{-- 移除原「報名」日期欄 --}}
                        <th class="px-3 py-2 text-left hidden xl:table-cell">場地</th>
                        <th class="px-3 py-2 text-left hidden xl:table-cell">報名狀態</th>
                        <th class="px-3 py-2 text-left hidden xl:table-cell">操作</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-sm">
                    @forelse($events as $event)
                        @php
                            $start = $event->start_date ? \Illuminate\Support\Carbon::parse($event->start_date) : null;
                            $end   = $event->end_date   ? \Illuminate\Support\Carbon::parse($event->end_date)   : null;
                            $dateRangeText = ($start && $end)
                                ? ($start->equalTo($end) ? $start->format('Y-m-d') : $start->format('Y-m-d').' ~ '.$end->format('Y-m-d'))
                                : '—';

                            // 報名狀態只留在這欄判斷
                            $now = now();
                            $regStartAt = $event->reg_start ? \Illuminate\Support\Carbon::parse($event->reg_start) : null;
                            $regEndAt   = $event->reg_end ? \Illuminate\Support\Carbon::parse($event->reg_end) : null;

                            $isBefore  = $regStartAt && $now->lt($regStartAt);
                            $isBetween = $regStartAt && $regEndAt && $now->between($regStartAt, $regEndAt);
                            $isAfter   = $regEndAt && $now->gt($regEndAt);

                            $regStatus = null;
                            if ($regStartAt && $regEndAt) {
                                $regStatus = $isBefore ? '尚未開始' : ($isBetween ? '報名中' : '已截止');

                            }
                            $badgeClass = match($regStatus) {
                                '報名中'   => 'bg-indigo-50 text-indigo-700',
                                '尚未開始' => 'bg-gray-100 text-gray-700',
                                '已截止'   => 'bg-gray-100 text-gray-500',
                                default    => 'bg-gray-100 text-gray-500'
                            };
                        @endphp
                        <tr class="hover:bg-gray-50/60">
                            {{-- 期間 + 模式/驗證/等級 小徽章 --}}
                            <td class="px-3 py-2 align-top whitespace-nowrap">
                                <div class="font-medium text-gray-900">{{ $dateRangeText }}</div>
                                <div class="mt-1 flex flex-wrap gap-1">
                                    <span class="inline-flex items-center rounded-full px-1.5 py-0.5 text-[10px] font-medium
                                        {{ $event->mode==='indoor' ? 'bg-blue-50 text-blue-700' : 'bg-emerald-50 text-emerald-700' }}">
                                        {{ $event->mode==='indoor'?'室內':'室外' }}
                                    </span>
                                    <span class="inline-flex items-center rounded-full px-1.5 py-0.5 text-[10px] font-medium
                                        {{ $event->verified ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-700' }}">
                                        {{ $event->verified ? '已驗證' : '未驗證' }}
                                    </span>
                                    @if($event->level)
                                        <span
                                            class="inline-flex items-center rounded-full bg-gray-100 px-1.5 py-0.5 text-[10px] font-medium text-gray-700">
                                            {{ strtoupper($event->level) }}
                                        </span>
                                    @endif
                                </div>
                            </td>

                            {{-- 賽事名稱（兩行限制） --}}
                            <td class="px-3 py-2 align-top">
                                <a href="{{ route('events.show', $event) }}"
                                   class="text-indigo-600 hover:underline line-clamp-2">
                                    {{ $event->name }}
                                </a>
                            </td>

                            {{-- 主辦（隱藏於小螢幕） --}}
                            <td class="px-3 py-2 align-top hidden md:table-cell">
                                <div class="truncate max-w-[16rem]"
                                     title="{{ $event->organizer }}">{{ $event->organizer }}</div>
                            </td>

                            {{-- 場地（隱藏於較小螢幕） --}}
                            <td class="px-3 py-2 align-top hidden xl:table-cell">
                                @if($event->venue || $event->map_link)
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="text-gray-900 truncate max-w-[14rem]">{{ $event->venue ?? '—' }}</span>
                                        @if($event->map_link)
                                            <a href="{{ $event->map_link }}" target="_blank" rel="noopener"
                                               class="text-indigo-600 hover:text-indigo-800" title="在 Google 地圖開啟">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4"
                                                     viewBox="0 0 24 24" fill="currentColor">
                                                    <path
                                                        d="M14 3h7v7h-2V6.414l-9.293 9.293-1.414-1.414L17.586 5H14V3Z"/>
                                                    <path d="M5 5h6v2H7v10h10v-4h2v6H5V5Z"/>
                                                </svg>
                                            </a>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>

                            {{-- 報名狀態（僅顯示狀態，不列日期） --}}
                            <td class="px-3 py-2 align-top hidden xl:table-cell">
                                @if($regStatus)
                                    <span
                                        class="inline-flex items-center rounded-full px-1.5 py-0.5 text-[10px] font-medium {{ $badgeClass }}">
                                        {{ $regStatus }}
                                    </span>
                                @else
                                    <span class="text-gray-400 text-xs">—</span>
                                @endif
                            </td>

                            {{-- 操作 --}}
                            {{-- 操作欄（替換原本報名按鈕區塊） --}}
                            <td class="px-3 py-2 align-top hidden xl:table-cell">
                                @php
                                    // $isBefore / $isBetween / $isAfter 已在同一 <tr> 的上方 @php 算好，可直接使用
                                    // 若你的賽事詳情頁有組別區塊，建議加上 #groups 錨點讓使用者直達
                                    $groupsPageUrl = route('events.show', $event) . '#groups';
                                @endphp

                                @if($isBetween)
                                    <a href="{{ route('events.show', $event) }}#groups"
                                       class="inline-flex items-center rounded-xl bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-500">
                                        我要報名
                                    </a>
                                @else
                                    <span class="text-gray-300 text-xs">—</span>
                                @endif

                                {{-- 管理權限者仍可看到「管理」按鈕（不受報名期間限制） --}}
                                @php
                                    $canManage = auth()->check() && \App\Models\EventStaff::where([
                                        'event_id' => $event->id,
                                        'user_id'  => auth()->id(),
                                        'status'   => 'active',
                                    ])->exists();
                                @endphp
                                @if($canManage)
                                    <a href="{{ route('events.groups.index', $event) }}"
                                       class="ml-2 inline-flex items-center rounded-xl bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-emerald-500">
                                        管理
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12">
                                <div class="flex flex-col items-center justify-center text中心">
                                    <div class="mb-3 rounded-2xl bg-gray-100 p-3">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-400"
                                             viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M3 5a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v2H3V5Z"/>
                                            <path d="M3 9h18v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V9Zm4 2v6h10v-6H7Z"/>
                                        </svg>
                                    </div>
                                    <p class="text-gray-900 font-medium">目前沒有符合條件的賽事</p>
                                    <p class="text-gray-500 text-sm mt-1">試著調整篩選條件，或建立一個新賽事。</p>
                                    <a href="{{ route('events.create') }}"
                                       class="mt-4 inline-flex items-center justify-center rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text白 hover:bg-indigo-500">
                                        我要辦賽事
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Bottom Pagination --}}
        <div class="mt-4 flex items-center justify-between">
            <p class="text-xs text-gray-500">
                第 {{ $events->firstItem() }} - {{ $events->lastItem() }} 筆，共 {{ $events->total() }} 筆
            </p>
            <div class="hidden sm:block">
                {{ $events->withQueryString()->onEachSide(1)->links() }}
            </div>
            <div class="sm:hidden">
                {{ $events->withQueryString()->onEachSide(0)->links() }}
            </div>
        </div>
    </div>
@endsection
