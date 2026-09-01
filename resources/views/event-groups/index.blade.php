{{-- resources/views/event-groups/index.blade.php --}}
@extends('layouts.app')
@section('title', '組別管理')

@section('content')
    <div class="mx-auto max-w-6xl px-4 py-8">
        <a href="{{ route('organizer.events.show', $event) }}"
           class="mb-3 inline-flex min-h-11 items-center text-sm font-medium text-indigo-600 hover:text-indigo-500">
            ← 返回賽事管理
        </a>

        <div class="mb-4 flex items-start justify-between">
            <div>
                <h1 class="text-2xl font-bold">組別管理 — {{ $event->name }}</h1>
                <p class="text-sm text-gray-500 mt-1">共 {{ $groupsAll->total() }} 個組別</p>
            </div>
            @if($groupCreationLocked)
                <button type="button" disabled title="已完成排靶，無法新增組別"
                        class="cursor-not-allowed rounded-xl bg-gray-200 px-4 py-2 text-sm font-medium text-gray-400">
                    新增組別
                </button>
            @elseif($groupLimitReached && $event->canUpgradeToEventPass())
                <a href="{{ route('store.index', ['event' => $event->uuid]) }}" title="免費方案最多 1 個組別"
                   class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-800 hover:bg-amber-100">
                    升級以新增組別
                </a>
            @elseif($groupLimitReached)
                <button type="button" disabled title="{{ $event->eventPassUpgradeBlockReason() }}" class="cursor-not-allowed rounded-xl bg-gray-200 px-4 py-2 text-sm font-medium text-gray-400">無法新增組別</button>
            @else
                <a href="{{ route('events.groups.create', $event) }}"
                   class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                    新增組別
                </a>
            @endif
        </div>

        @if(session('success'))
            <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <span>{{ session('error') }}</span>
                @if($groupLimitReached && !$groupCreationLocked && $event->canUpgradeToEventPass())<a href="{{ route('store.index', ['event' => $event->uuid]) }}" class="font-semibold underline">前往商店</a>@endif
            </div>
        @endif

        <div class="rounded-2xl border bg-white shadow-sm overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                <tr>
                    <th class="px-3 py-2 text-left">名稱</th>
                    <th class="px-3 py-2 text-left hidden md:table-cell">弓種/性別/年齡</th>
                    <th class="px-3 py-2 text-left hidden lg:table-cell">距離</th>
                    <th class="px-3 py-2 text-left hidden lg:table-cell">箭數</th>
                    <th class="px-3 py-2 text-left hidden lg:table-cell">名額</th>
                    <th class="px-3 py-2 text-left hidden xl:table-cell">報名費</th>
                    <th class="px-3 py-2 text-left">操作</th>
                </tr>
                </thead>

                <tbody class="divide-y divide-gray-100 text-sm">
                @forelse ($groupsAll as $g)
                    <tr >
                        <td class="px-3 py-2 font-medium">{{ $g->name }}</td>

                        <td class="px-3 py-2 hidden md:table-cell">
                            {{ $g->bow_type ?: '—' }} /
                            {{ $g->gender ?: '—' }} /
                            {{ $g->age_class ?: '—' }}
                        </td>

                        <td class="px-3 py-2 hidden lg:table-cell">
                            {{ $g->distance ?: '—' }}
                        </td>

                        <td class="px-3 py-2 hidden lg:table-cell">
                            {{ $g->arrow_count ? ($g->arrow_count . ' 支') : '—' }}
                        </td>

                        <td class="px-3 py-2 hidden lg:table-cell">
                            {{-- 已報名 / 名額上限 --}}
                            {{ $g->registrations_count ?? 0 }} / {{ $g->quota ?: '無上限' }}
                        </td>

                        <td class="px-3 py-2 hidden xl:table-cell">
                            {{ $g->fee ? number_format($g->fee) : '—' }}
                        </td>

                        <td class="px-3 py-2">
                            <div class="flex gap-2">
                                <a href="{{ route('events.groups.edit', [$event, $g]) }}" class="text-indigo-600 hover:underline">編輯</a>
                                <form method="POST" action="{{ route('events.groups.destroy', [$event, $g]) }}"
                                      onsubmit="return confirm('確定刪除？')">
                                    @csrf @method('DELETE')
                                    <button class="text-red-600 hover:underline">刪除</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td class="px-3 py-6 text-center text-gray-500" colspan="7">尚無組別</td>
                    </tr>
                @endforelse
                </tbody>
            </table>

            {{-- 分頁 --}}
            {{ $groupsAll->withQueryString()->links() }}

        </div>

{{--        <div class="mt-4">{{ $groupsAll->links() }}</div>--}}
    </div>
@endsection
