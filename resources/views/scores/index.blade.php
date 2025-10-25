@extends('layouts.app')

@section('title','訓練紀錄')

@section('content')
    <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 py-8">

        {{-- Header --}}
        <div class="mb-4 flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-gray-900">訓練紀錄</h1>
                <p class="text-sm text-gray-500 mt-1">瀏覽、搜尋與管理你的訓練場次。</p>
            </div>
            <a href="{{ route('scores.setup') }}"
               class="inline-flex items-center justify-center rounded-xl bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-600">
                開始訓練
            </a>
        </div>

        {{-- Filters --}}
        <details class="mb-4 rounded-2xl border border-gray-200 bg-white shadow-sm" {{ request()->hasAny(['q','bow_type','venue','date_from','date_to']) ? 'open' : '' }}>
            <summary class="flex cursor-pointer items-center justify-between px-4 py-3">
                <div class="text-sm font-medium text-gray-800">篩選條件</div>
                <span class="text-xs text-gray-500">點擊展開/收合</span>
            </summary>
            <div class="border-t border-gray-100 p-4">
                {{-- chips --}}
                @php
                    $chips = [];
                    if(request('q'))         $chips[] = '關鍵字：'.e(request('q'));
                    if(request('bow_type'))  $chips[] = '弓種：'.e(request('bow_type'));
                    if(request('venue'))     $chips[] = '場地：'.(request('venue')==='indoor'?'室內':'室外');
                    if(request('date_from') || request('date_to'))
                        $chips[] = '期間：'.(request('date_from') ?: '—').' ~ '.(request('date_to') ?: '—');
                @endphp
                @if($chips)
                    <div class="mb-3 flex flex-wrap gap-2">
                        @foreach($chips as $c)
                            <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-xs text-gray-700">{{ $c }}</span>
                        @endforeach
                        <a href="{{ route('scores.index') }}" class="text-xs text-indigo-600 hover:underline">清除</a>
                    </div>
                @endif

                <form method="GET" action="{{ route('scores.index') }}" class="grid grid-cols-1 md:grid-cols-6 gap-3">
                    <div class="md:col-span-2">
                        <label for="q" class="block text-xs font-medium text-gray-600 mb-1">關鍵字（備註）</label>
                        <input type="text" id="q" name="q" value="{{ request('q') }}"
                               placeholder="搜尋備註內容"
                               class="block w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label for="bow_type" class="block text-xs font-medium text-gray-600 mb-1">弓種</label>
                        <select id="bow_type" name="bow_type"
                                class="block w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">全部</option>
                            @foreach(['recurve'=>'Recurve','compound'=>'Compound','barebow'=>'Barebow','yumi'=>'Yumi','longbow'=>'Longbow'] as $k=>$v)
                                <option value="{{ $k }}" @selected(request('bow_type')===$k)>{{ $v }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="venue" class="block text-xs font-medium text-gray-600 mb-1">場地</label>
                        <select id="venue" name="venue"
                                class="block w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">全部</option>
                            <option value="indoor"  @selected(request('venue')==='indoor')>室內</option>
                            <option value="outdoor" @selected(request('venue')==='outdoor')>室外</option>
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

                    <div>
                        <label for="sort" class="block text-xs font-medium text-gray-600 mb-1">排序</label>
                        @php
                            $sort = request('sort','created_at'); $dir = request('dir','desc');
                            $options = ['created_at'=>'建立時間','score_total'=>'總分','distance_m'=>'距離'];
                        @endphp
                        <div class="flex gap-2">
                            <select id="sort" name="sort"
                                    class="w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach($options as $k=>$v)
                                    <option value="{{ $k }}" @selected($sort===$k)>{{ $v }}</option>
                                @endforeach
                            </select>
                            <select id="dir" name="dir"
                                    class="w-28 rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="desc" @selected($dir==='desc')>新→舊</option>
                                <option value="asc"  @selected($dir==='asc')>舊→新</option>
                            </select>
                        </div>
                    </div>

                    <div class="md:col-span-6 mt-2 flex items-center justify-end gap-2">
                        <a href="{{ route('scores.index') }}"
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

        {{-- Table --}}
        <div class="rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden">
            <div class="max-h-[70vh] overflow-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50 text-[11px] uppercase text-gray-500 sticky top-0 z-10">
                    <tr>
                        <th class="px-3 py-2 text-left">日期</th>
                        <th class="px-3 py-2 text-left">設定</th>
                        <th class="px-3 py-2 text-right">總分</th>
                        <th class="px-3 py-2 text-right hidden sm:table-cell">X</th>
                        <th class="px-3 py-2 text-right hidden sm:table-cell">M</th>
                        <th class="px-3 py-2 text-right hidden md:table-cell">箭數</th>
                        <th class="px-3 py-2 text-left hidden xl:table-cell">備註</th>
                        <th class="px-3 py-2 text-left hidden xl:table-cell">操作</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-sm">
                    @forelse($sessions as $s)
                        @php
                            $mobileUrl = route('scores.show', $s);
                        @endphp
                        <tr class="hover:bg-gray-50/60 md:hover:bg-gray-50/60 cursor-pointer md:cursor-default"
                            data-mobile-link="{{ $mobileUrl }}"
                            role="link" tabindex="0">
                            <td class="px-3 py-2 align-top whitespace-nowrap">
                                <a href="{{ route('scores.show', $s) }}" class="text-indigo-600 hover:underline md:pointer-events-auto pointer-events-none">
                                    {{ $s->created_at->format('Y-m-d H:i') }}
                                </a>
                            </td>
                            <td class="px-3 py-2 align-top">
                                <div class="flex flex-wrap gap-1">
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-1.5 py-0.5 text-[10px] font-medium text-gray-700">
                                    {{ ucfirst($s->bow_type) }}
                                </span>
                                    <span class="inline-flex items-center rounded-full {{ $s->venue==='indoor' ? 'bg-blue-50 text-blue-700' : 'bg-emerald-50 text-emerald-700' }} px-1.5 py-0.5 text-[10px] font-medium">
                                    {{ $s->venue==='indoor'?'室內':'室外' }}
                                </span>
                                    <span class="inline-flex items-center rounded-full bg-gray-100 px-1.5 py-0.5 text-[10px] font-medium text-gray-700">
                                    {{ $s->distance_m }}m
                                </span>
                                    <span class="inline-flex items-center rounded-full bg-gray-100 px-1.5 py-0.5 text-[10px] font-medium text-gray-700">
                                    {{ $s->arrows_per_end }}/End ・ {{ $s->arrows_total }} Arrows
                                </span>
                                </div>
                            </td>
                            <td class="px-3 py-2 align-top text-right font-semibold font-mono tabular-nums">{{ $s->score_total }}</td>
                            <td class="px-3 py-2 align-top text-right hidden sm:table-cell font-mono tabular-nums">{{ $s->x_count }}</td>
                            <td class="px-3 py-2 align-top text-right hidden sm:table-cell font-mono tabular-nums">{{ $s->m_count }}</td>
                            <td class="px-3 py-2 align-top text-right hidden md:table-cell font-mono tabular-nums">{{ $s->arrows_total }}</td>
                            <td class="px-3 py-2 align-top hidden xl:table-cell">
                                <div class="truncate max-w-[18rem]" title="{{ $s->note }}">{{ $s->note }}</div>
                            </td>
                            <td class="px-3 py-2 align-top hidden xl:table-cell">
                                <a href="{{ route('scores.show', $s) }}"
                                   class="inline-flex items-center rounded-xl bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-500">
                                    檢視
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-12">
                                <div class="flex flex-col items-center justify-center text-center">
                                    <div class="mb-3 rounded-2xl bg-gray-100 p-3">🏹</div>
                                    <p class="text-gray-900 font-medium">還沒有訓練紀錄</p>
                                    <p class="text-gray-500 text-sm mt-1">按右上角「開始新的訓練」試試看。</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Pagination --}}
        <div class="mt-4 flex items-center justify-between">
            <p class="text-xs text-gray-500">
                第 {{ $sessions->firstItem() }} - {{ $sessions->lastItem() }} 筆，共 {{ $sessions->total() }} 筆
            </p>
            <div class="hidden sm:block">
                {{ $sessions->onEachSide(1)->links() }}
            </div>
            <div class="sm:hidden">
                {{ $sessions->onEachSide(0)->links() }}
            </div>
        </div>
    </div>

    {{-- 手機整列可點導頁（與 events/index 類似） --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const isMobile = () => window.matchMedia('(max-width: 767.98px)').matches;

            document.querySelectorAll('tr[data-mobile-link]').forEach(function (row) {
                const go = () => { if (isMobile()) window.location.href = row.dataset.mobileLink; };

                row.addEventListener('click', function (e) {
                    if (!isMobile()) return;
                    const tag = e.target.tagName.toLowerCase();
                    if (tag === 'a' || tag === 'button' || e.target.closest('a,button,[role="button"]')) return;
                    go();
                });

                row.addEventListener('keydown', function (e) {
                    if (!isMobile()) return;
                    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); go(); }
                });
            });
        });
    </script>
@endsection
