@extends('layouts.app')
@section('title','主辦方審核')
@section('content')
@php($statusLabels = ['pending'=>'待審核','legacy_review'=>'既有待審','approved'=>'已核准','changes_requested'=>'待補件','rejected'=>'未通過','suspended'=>'已停權','draft'=>'草稿'])
<div class="mx-auto max-w-7xl space-y-5 px-4 py-6 sm:space-y-6 sm:px-6 sm:py-8">
    <div>
        <p class="text-xs font-semibold uppercase tracking-widest text-indigo-600">Platform Admin</p>
        <h1 class="mt-1 text-2xl font-bold">主辦方資格管理</h1>
    </div>

    <form method="GET" class="grid gap-3 rounded-2xl border bg-white p-4 sm:grid-cols-[minmax(0,1fr)_12rem_auto] sm:items-end">
        <label class="block text-sm font-medium text-gray-700">搜尋
            <input name="q" value="{{ request('q') }}" class="mt-1 min-h-11 w-full rounded-xl border-gray-300" placeholder="主辦單位或 Email">
        </label>
        <label class="block text-sm font-medium text-gray-700">狀態
            <select name="status" class="mt-1 min-h-11 w-full rounded-xl border-gray-300">
                <option value="">全部狀態</option>
                @foreach($statusLabels as $value => $label)<option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>@endforeach
            </select>
        </label>
        <button class="min-h-11 w-full rounded-xl bg-gray-900 px-5 text-sm font-medium text-white sm:w-auto">套用篩選</button>
    </form>

    {{-- 手機使用卡片，重要資訊不需要左右滑動。 --}}
    <div class="space-y-3 sm:hidden">
        @forelse($profiles as $profile)
            <article class="rounded-2xl border bg-white p-4 shadow-sm">
                <div class="flex min-w-0 items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="break-words font-semibold text-gray-900">{{ $profile->organization_name }}</h2>
                        <p class="mt-1 text-sm text-gray-600">{{ $profile->user?->display_name }}</p>
                        <p class="break-all text-xs text-gray-500">{{ $profile->contact_email }}</p>
                    </div>
                    <span class="shrink-0 rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700">{{ $statusLabels[$profile->status] ?? $profile->status }}</span>
                </div>
                <div class="mt-4 flex items-center justify-between border-t pt-3">
                    <p class="text-sm text-gray-500">申請版本 <span class="font-semibold text-gray-800">{{ $profile->applications_count }}</span></p>
                    <a href="{{ route('admin.organizers.show',$profile) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-indigo-600 px-5 text-sm font-medium text-white">查看與審核</a>
                </div>
            </article>
        @empty
            <div class="rounded-2xl border bg-white p-8 text-center text-gray-500">尚無申請</div>
        @endforelse
    </div>

    <div class="hidden overflow-hidden rounded-2xl border bg-white sm:block">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs text-gray-500"><tr><th class="p-4">主辦單位</th><th class="p-4">會員</th><th class="p-4">狀態</th><th class="p-4">申請版本</th><th class="p-4"></th></tr></thead>
            <tbody class="divide-y">
                @forelse($profiles as $profile)
                    <tr><td class="p-4 font-medium">{{ $profile->organization_name }}</td><td class="p-4">{{ $profile->user?->display_name }}<p class="text-xs text-gray-500">{{ $profile->contact_email }}</p></td><td class="p-4">{{ $statusLabels[$profile->status] ?? $profile->status }}</td><td class="p-4">{{ $profile->applications_count }}</td><td class="p-4 text-right"><a href="{{ route('admin.organizers.show',$profile) }}" class="inline-flex min-h-10 items-center rounded-lg border px-4 text-xs font-medium">審核</a></td></tr>
                @empty<tr><td colspan="5" class="p-8 text-center text-gray-500">尚無申請</td></tr>@endforelse
            </tbody>
        </table>
    </div>
    {{ $profiles->links() }}
</div>
@endsection
