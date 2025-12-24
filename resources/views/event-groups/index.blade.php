{{-- resources/views/event-groups/index.blade.php --}}
@extends('layouts.app')
@section('title', '組別管理')

@section('content')
    <div class="mx-auto max-w-6xl px-4 py-8">
        <div class="mb-4 space-y-2">
            <a href="{{ route('admin.events.index') }}" class="text-xs font-medium text-gray-500 hover:text-gray-700">
                ← 返回可管理賽事
            </a>
            <div class="flex items-start justify-between">
                <div>
                    <h1 class="text-2xl font-bold">組別管理 — {{ $event->name }}</h1>
                    <p class="text-sm text-gray-500 mt-1">共 {{ $groupsAll->total() }} 個組別</p>
                </div>
                <a href="{{ route('events.groups.create', $event) }}"
                   class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                    新增組別
                </a>
            </div>
        </div>

        @if(session('success'))
            <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                {{ session('success') }}
            </div>
        @endif

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($groupsAll as $g)
                <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">
                                <a href="{{ route('events.groups.show', [$event, $g]) }}" class="hover:text-indigo-600">
                                    {{ $g->name }}
                                </a>
                            </h2>
                            <p class="text-xs text-gray-500 mt-1">
                                {{ $g->bow_type ?: '—' }} /
                                {{ $g->gender ?: '—' }} /
                                {{ $g->age_class ?: '—' }}
                            </p>
                        </div>
                        <span class="inline-flex items-center rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700">
                            {{ $g->registrations_count ?? 0 }} / {{ $g->quota ?: '—' }}
                        </span>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-3 text-sm text-gray-600">
                        <div>
                            <p class="text-xs text-gray-400">距離</p>
                            <p class="font-medium text-gray-800">{{ $g->distance ?: '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400">箭數</p>
                            <p class="font-medium text-gray-800">{{ $g->arrow_count ? ($g->arrow_count . ' 支') : '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400">報名費</p>
                            <p class="font-medium text-gray-800">{{ $g->fee ? number_format($g->fee) : '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400">名額</p>
                            <p class="font-medium text-gray-800">{{ $g->quota ?: '—' }}</p>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-3 text-sm">
                        <a href="{{ route('events.groups.show', [$event, $g]) }}" class="text-gray-700 hover:underline">查看</a>
                        <form method="POST" action="{{ route('events.groups.destroy', [$event, $g]) }}"
                              onsubmit="return confirm('確定刪除？')">
                            @csrf @method('DELETE')
                            <button class="text-red-600 hover:underline">刪除</button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="rounded-2xl border border-dashed border-gray-200 bg-white p-8 text-center text-sm text-gray-500">
                    尚無組別
                </div>
            @endforelse
        </div>

        <div class="mt-6">
            {{-- 分頁 --}}
            {{ $groupsAll->withQueryString()->links() }}
        </div>

{{--        <div class="mt-4">{{ $groupsAll->links() }}</div>--}}
    </div>
@endsection
