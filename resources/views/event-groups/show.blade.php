@extends('layouts.app')
@section('title', '組別詳情')

@section('content')
    @php
        $genderLabels = ['male' => '男子', 'female' => '女子', 'open' => '不限'];
    @endphp
    <div class="mx-auto max-w-6xl px-4 py-8 space-y-6">
        <div class="space-y-2">
            <a href="{{ route('events.groups.index', $event) }}" class="text-xs font-medium text-gray-500 hover:text-gray-700">
                ← 返回組別列表
            </a>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs uppercase tracking-widest text-indigo-600 font-semibold">Group</p>
                    <h1 class="text-2xl font-bold text-gray-900">{{ $group->name }}</h1>
                    <p class="text-sm text-gray-500">賽事：{{ $event->name }}</p>
                </div>
                <form method="POST" action="{{ route('events.groups.destroy', [$event, $group]) }}"
                      class="w-full sm:w-auto"
                      onsubmit="return confirm('確定刪除這個組別？')">
                    @csrf
                    @method('DELETE')
                    <button class="inline-flex w-full items-center justify-center rounded-xl bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-500 sm:w-auto">
                        刪除組別
                    </button>
                </form>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="grid grid-cols-3 gap-3 text-center">
                <div>
                    <p class="text-xs text-gray-500">報名人數</p>
                    <p class="text-2xl font-semibold text-gray-900">{{ $participants->count() }}</p>
                </div>
                <div class="border-l border-gray-100 pl-3">
                    <p class="text-xs text-gray-500">已繳費</p>
                    <p class="text-2xl font-semibold text-emerald-600">{{ $participants->where('paid', true)->count() }}</p>
                </div>
                <div class="border-l border-gray-100 pl-3">
                    <p class="text-xs text-gray-500">未繳費</p>
                    <p class="text-2xl font-semibold text-amber-600">{{ $participants->where('paid', false)->count() }}</p>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-900">組別資訊</h2>
            <div class="mt-4 grid grid-cols-2 gap-4 text-sm text-gray-600 sm:grid-cols-3 lg:grid-cols-4">
                <div>
                    <p class="text-xs text-gray-400">弓種</p>
                    <p class="font-medium text-gray-800">{{ $group->bow_type ?: '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400">性別</p>
                    <p class="font-medium text-gray-800">{{ $genderLabels[$group->gender] ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400">年齡組</p>
                    <p class="font-medium text-gray-800">{{ $group->age_class ?: '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400">距離</p>
                    <p class="font-medium text-gray-800">{{ $group->distance ?: '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400">箭數</p>
                    <p class="font-medium text-gray-800">{{ $group->arrow_count ? ($group->arrow_count . ' 支') : '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400">名額</p>
                    <p class="font-medium text-gray-800">{{ $group->quota ? $group->quota : '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400">報名費</p>
                    <p class="font-medium text-gray-800">{{ $group->fee ? number_format($group->fee) : '—' }}</p>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-5 py-4">
                <h2 class="text-sm font-semibold text-gray-900">參賽名單</h2>
            </div>
            <div class="block sm:hidden">
                <ul class="divide-y divide-gray-100 text-sm">
                    @forelse ($participants as $participant)
                        <li class="px-5 py-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="font-medium text-gray-900">{{ $participant->name }}</p>
                                    <p class="text-xs text-gray-500">{{ $genderLabels[$group->gender] ?? '—' }}</p>
                                </div>
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium {{ $participant->paid ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                                    {{ $participant->paid ? '已繳費' : '未繳費' }}
                                </span>
                            </div>
                        </li>
                    @empty
                        <li class="px-5 py-8 text-center text-sm text-gray-500">目前尚無報名資料。</li>
                    @endforelse
                </ul>
            </div>
            <div class="hidden sm:block overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-widest text-gray-500">
                    <tr>
                        <th class="px-5 py-3 text-left font-semibold">姓名</th>
                        <th class="px-5 py-3 text-left font-semibold">性別</th>
                        <th class="px-5 py-3 text-left font-semibold">繳費狀態</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                    @forelse ($participants as $participant)
                        <tr>
                            <td class="px-5 py-3 font-medium text-gray-900">{{ $participant->name }}</td>
                            <td class="px-5 py-3 text-gray-600">
                                {{ $genderLabels[$group->gender] ?? '—' }}
                            </td>
                            <td class="px-5 py-3">
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium {{ $participant->paid ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                                    {{ $participant->paid ? '已繳費' : '未繳費' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-5 py-8 text-center text-sm text-gray-500">
                                目前尚無報名資料。
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
