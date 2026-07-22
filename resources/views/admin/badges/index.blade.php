@extends('layouts.app')
@section('title', 'Badge 監控')
@section('content')
<div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6">
    <div><p class="text-xs font-semibold uppercase tracking-widest text-indigo-600">Platform Admin</p><h1 class="mt-1 text-2xl font-bold">Badge 監控</h1></div>
    @if(session('success'))<div class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-700">{{ session('success') }}</div>@endif
    <div class="overflow-hidden rounded-2xl border bg-white shadow-sm"><div class="overflow-x-auto"><table class="min-w-full text-sm"><thead class="bg-gray-50 text-left text-xs text-gray-500"><tr><th class="p-4">Badge／賽事</th><th class="p-4">狀態</th><th class="p-4">申請</th><th class="p-4">有效授予</th><th class="p-4 text-right">管理</th></tr></thead><tbody class="divide-y">
        @forelse($badges as $badge)<tr><td class="p-4"><div class="flex items-center gap-3"><img src="{{ $badge->icon_url }}" alt="" class="h-11 w-11 shrink-0 rounded-lg object-cover"><div><p class="font-semibold">{{ $badge->name }}</p><p class="text-xs text-gray-500">{{ $badge->event->name }}・{{ $badge->event->organizer }}</p></div></div></td><td class="p-4"><span class="rounded-full px-2 py-1 text-xs {{ $badge->is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">{{ $badge->is_active ? '正常' : '已停用' }}</span></td><td class="p-4">{{ $badge->claims_count }}</td><td class="p-4">{{ $badge->active_awards_count }}</td><td class="p-4 text-right"><div class="flex justify-end gap-2"><a href="{{ route('organizer.events.badges.show', [$badge->event, $badge]) }}" class="rounded-lg border px-3 py-1.5 text-xs">查看紀錄</a><form method="POST" action="{{ route('admin.badges.toggle', $badge) }}">@csrf @method('PATCH')<button class="rounded-lg border px-3 py-1.5 text-xs {{ $badge->is_active ? 'text-red-600' : 'text-green-700' }}">{{ $badge->is_active ? '停用' : '重新啟用' }}</button></form></div></td></tr>
        @empty<tr><td colspan="5" class="p-8 text-center text-gray-500">尚無 Badge。</td></tr>@endforelse
    </tbody></table></div></div>
    {{ $badges->links() }}
</div>
@endsection
