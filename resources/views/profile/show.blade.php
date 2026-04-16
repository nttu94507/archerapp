@extends('layouts.app')

@section('title', '個人頁')

@section('content')
    <div class="max-w-5xl mx-auto px-4 py-8 space-y-6">
        <section class="rounded-2xl border bg-white p-5 sm:p-6">
            <h1 class="text-2xl font-bold">👤 個人頁面</h1>
            <p class="mt-1 text-sm text-gray-600">檢視選手基本資料與成就達成狀況。</p>

            <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                <div class="rounded-xl bg-gray-50 p-3"><span class="text-gray-500">顯示名稱：</span>{{ $user->display_name }}</div>
                <div class="rounded-xl bg-gray-50 p-3"><span class="text-gray-500">Email：</span>{{ $user->email }}</div>
                <div class="rounded-xl bg-gray-50 p-3"><span class="text-gray-500">慣用手：</span>{{ $profile?->handedness ?? '未填寫' }}</div>
                <div class="rounded-xl bg-gray-50 p-3"><span class="text-gray-500">弓種：</span>{{ $profile?->bow_type ?? '未填寫' }}</div>
                <div class="rounded-xl bg-gray-50 p-3"><span class="text-gray-500">所屬俱樂部：</span>{{ $profile?->club_name ?? '未填寫' }}</div>
                <div class="rounded-xl bg-gray-50 p-3"><span class="text-gray-500">城市：</span>{{ $profile?->city ?? '未填寫' }}</div>
            </div>
        </section>

        <section class="rounded-2xl border bg-white p-5 sm:p-6">
            <h2 class="text-xl font-semibold">🏅 成就達成狀況</h2>
            <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-3 text-center">
                <div class="rounded-xl bg-gray-50 p-4">
                    <div class="text-xs text-gray-500">總成就</div>
                    <div class="text-2xl font-semibold">{{ $achievementSummary['total'] }}</div>
                </div>
                <div class="rounded-xl bg-emerald-50 p-4">
                    <div class="text-xs text-emerald-700">已達成</div>
                    <div class="text-2xl font-semibold text-emerald-800">{{ $achievementSummary['unlocked'] }}</div>
                </div>
                <div class="rounded-xl bg-indigo-50 p-4">
                    <div class="text-xs text-indigo-700">進行中</div>
                    <div class="text-2xl font-semibold text-indigo-800">{{ $achievementSummary['in_progress'] }}</div>
                </div>
            </div>

            <h3 class="mt-6 text-sm font-semibold text-gray-700">最近解鎖</h3>
            <div class="mt-2 space-y-2">
                @forelse($recentUnlocked as $item)
                    <div class="rounded-xl border p-3">
                        <div class="font-medium">{{ $item->definition->name }}</div>
                        <div class="text-xs text-gray-500">{{ optional($item->unlocked_at)->format('Y-m-d H:i') }}</div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">目前尚未解鎖成就。</p>
                @endforelse
            </div>
        </section>
    </div>
@endsection
