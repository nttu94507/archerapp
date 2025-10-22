{{-- resources/views/event-groups/index.blade.php --}}
@extends('layouts.app')
@section('title', '組別管理')

@section('content')
    <div class="mx-auto max-w-6xl px-4 py-8">
        <div class="mb-4 flex items-start justify-between">
            <div>
                <h1 class="text-2xl font-bold">組別管理 — {{ $event->name }}</h1>
                <p class="text-sm text-gray-500 mt-1">共 {{ $groupsAll->total() }} 個組別</p>
            </div>
            <a href="{{ route('events.groups.create', $event) }}"
               class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                新增組別
            </a>
        </div>

        @if(session('success'))
            <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                {{ session('success') }}
            </div>
        @endif

        <div class="rounded-2xl border bg-white shadow-sm overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                <tr>
                    <th class="px-3 py-2 text-left">名稱</th>
                    <th class="px-3 py-2 text-left hidden md:table-cell">弓種/性別/年齡</th>
                    <th class="px-3 py-2 text-left hidden lg:table-cell">距離</th>
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
                            {{-- 已報名 / 名額上限 --}}
                            {{ $g->registrations_count ?? 0 }} / {{ $g->quota}}
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
                        <td class="px-3 py-6 text-center text-gray-500" colspan="6">尚無組別</td>
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
