@extends('layouts.app')

@section('title','Admin / 賽事管理')

@section('content')
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs uppercase tracking-widest text-indigo-600 font-semibold">Admin</p>
                <h1 class="text-2xl font-bold text-gray-900">可管理賽事</h1>
                <p class="text-sm text-gray-500">這裡僅顯示你有權限管理的賽事。</p>
            </div>
            <a href="{{ route('admin.events.create') }}"
               class="inline-flex items-center justify-center rounded-xl bg-gray-900 px-5 py-2.5 text-sm font-medium text-white hover:bg-gray-800">
                新增賽事
            </a>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm"
             x-data="{ open: {{ request()->except('page') ? 'true' : 'false' }} }">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs uppercase tracking-widest text-gray-400">搜尋與篩選</p>
                    <p class="text-sm text-gray-600">快速收合篩選條件</p>
                </div>
                <button type="button"
                        class="inline-flex items-center gap-2 rounded-xl border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        @click="open = !open"
                        :aria-expanded="open.toString()">
                    <span x-text="open ? '收起' : '展開'"></span>
                    <svg class="h-4 w-4 transition-transform" :class="{ 'rotate-180': open }" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.292l3.71-4.06a.75.75 0 1 1 1.08 1.04l-4.25 4.65a.75.75 0 0 1-1.08 0l-4.25-4.65a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
                    </svg>
                </button>
            </div>
            <form method="GET" class="mt-4 grid grid-cols-1 md:grid-cols-6 gap-4" x-show="open" x-transition>
                <div class="md:col-span-2">
                    <label for="q" class="text-xs font-medium text-gray-600">關鍵字</label>
                    <input type="text" id="q" name="q" value="{{ request('q') }}"
                           placeholder="賽事、主辦或場地"
                           class="mt-1 block w-full rounded-xl border-gray-200 bg-gray-50 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label for="mode" class="text-xs font-medium text-gray-600">類型</label>
                    <select id="mode" name="mode"
                            class="mt-1 block w-full rounded-xl border-gray-200 bg-gray-50 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">全部</option>
                        <option value="indoor" @selected(request('mode')==='indoor')>室內</option>
                        <option value="outdoor" @selected(request('mode')==='outdoor')>室外</option>
                    </select>
                </div>
                <div>
                    <label for="verified" class="text-xs font-medium text-gray-600">驗證狀態</label>
                    <select id="verified" name="verified"
                            class="mt-1 block w-full rounded-xl border-gray-200 bg-gray-50 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">全部</option>
                        <option value="1" @selected(request('verified')==='1')>已驗證</option>
                        <option value="0" @selected(request('verified')==='0')>草稿</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label for="date_from" class="text-xs font-medium text-gray-600">開始日期</label>
                        <input type="date" id="date_from" name="date_from" value="{{ request('date_from') }}"
                               class="mt-1 block w-full rounded-xl border-gray-200 bg-gray-50 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label for="date_to" class="text-xs font-medium text-gray-600">結束日期</label>
                        <input type="date" id="date_to" name="date_to" value="{{ request('date_to') }}"
                               class="mt-1 block w-full rounded-xl border-gray-200 bg-gray-50 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                </div>
                <div>
                    <label for="sort" class="text-xs font-medium text-gray-600">排序</label>
                    <div class="mt-1 flex gap-2">
                        <select id="sort" name="sort"
                                class="w-full rounded-xl border-gray-200 bg-gray-50 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="start_date" @selected(request('sort','start_date')==='start_date')>開始日</option>
                            <option value="end_date" @selected(request('sort')==='end_date')>結束日</option>
                            <option value="created_at" @selected(request('sort')==='created_at')>建立時間</option>
                        </select>
                        <select id="dir" name="dir"
                                class="w-28 rounded-xl border-gray-200 bg-gray-50 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="desc" @selected(request('dir','desc')==='desc')>新→舊</option>
                            <option value="asc" @selected(request('dir')==='asc')>舊→新</option>
                        </select>
                    </div>
                </div>
                <div class="md:col-span-6 flex justify-end gap-3">
                    <a href="{{ route('admin.events.index') }}" class="text-xs text-gray-500 hover:text-gray-700">清除</a>
                    <button type="submit"
                            class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                        套用
                    </button>
                </div>
            </form>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-gray-500 uppercase tracking-widest text-xs">
                    <tr>
                        <th class="px-6 py-3 text-left font-semibold">賽事</th>
                        <th class="px-6 py-3 text-left font-semibold">日期</th>
                        <th class="px-6 py-3 text-left font-semibold">狀態</th>
                        <th class="px-6 py-3 text-left font-semibold">組別</th>
                        <th class="px-6 py-3 text-left font-semibold">工作人員</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                    @forelse($events as $event)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div class="font-semibold text-gray-900">{{ $event->name }}</div>
                                <div class="text-xs text-gray-500">{{ $event->organizer }}</div>
                            </td>
                            <td class="px-6 py-4 text-gray-700">
                                <div>
                                    {{ $event->start_date ? \Illuminate\Support\Carbon::parse($event->start_date)->format('Y-m-d') : '—' }}
                                    ~
                                    {{ $event->end_date ? \Illuminate\Support\Carbon::parse($event->end_date)->format('Y-m-d') : '—' }}
                                </div>
                                <div class="text-xs text-gray-500">{{ $event->mode === 'indoor' ? '室內' : '室外' }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium {{ $event->verified ? 'bg-emerald-100 text-emerald-700' : 'bg-yellow-100 text-yellow-700' }}">
                                    {{ $event->verified ? '已驗證' : '草稿' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-gray-700">{{ $event->groups_count }}</td>
                            <td class="px-6 py-4 text-gray-700">{{ $event->staff_count }}</td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('events.groups.index', $event) }}"
                                   class="inline-flex items-center rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                                    進入組別
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-10 text-center text-sm text-gray-500">目前沒有可管理的賽事。</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-4 border-t border-gray-100">
                {{ $events->links() }}
            </div>
        </div>
    </div>
@endsection
