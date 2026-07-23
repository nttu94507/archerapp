@extends('layouts.app')
@section('title', 'Badge 管理')
@section('content')
<div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 sm:py-8">
    <div><p class="text-xs font-semibold uppercase tracking-widest text-indigo-600">Platform Admin</p><h1 class="mt-1 text-2xl font-bold">Badge 管理</h1></div>
    @if(session('success'))<div class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-700">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>@endif

    <section class="rounded-2xl border bg-white p-5 shadow-sm sm:p-6">
        <div><h2 class="text-lg font-semibold">建立官方限量 Badge</h2><p class="mt-1 text-sm text-gray-500">由 ArrowTrack 官方認證並發放。</p></div>
        <form method="POST" action="{{ route('admin.badges.store') }}" enctype="multipart/form-data" class="mt-5 grid gap-4 lg:grid-cols-2">
            @csrf
            <div><label class="text-sm font-medium">Badge 名稱</label><input name="name" required value="{{ old('name') }}" class="mt-1 min-h-11 w-full rounded-xl border-gray-300" placeholder="例：ArrowTrack 週年紀念"></div>
            <div><label class="text-sm font-medium">限量數量</label><input type="number" min="1" name="max_supply" value="{{ old('max_supply') }}" class="mt-1 min-h-11 w-full rounded-xl border-gray-300" placeholder="未填寫則不限制"></div>
            <div class="lg:col-span-2" x-data="{ fileName: '尚未選擇圖片', preview: null }">
                <label class="text-sm font-medium">Badge 圖示</label>
                <div class="mt-2 flex flex-col gap-3 rounded-2xl border border-dashed border-indigo-200 bg-indigo-50/60 p-4 sm:flex-row sm:items-center">
                    <div class="flex items-center gap-3"><div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-white ring-1 ring-indigo-100"><img x-show="preview" :src="preview" alt="圖示預覽" class="h-full w-full object-cover"><svg x-show="!preview" class="h-7 w-7 text-indigo-300" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4-4 4 4 3-3 5 5M14 8h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg></div><div class="min-w-0"><p class="truncate text-sm font-medium text-gray-700" x-text="fileName"></p><p class="mt-1 text-xs text-gray-500">JPG、PNG、WebP，最大 10MB</p></div></div>
                    <label class="inline-flex min-h-11 cursor-pointer items-center justify-center rounded-xl bg-indigo-600 px-5 text-sm font-medium text-white hover:bg-indigo-500 sm:ml-auto">選擇圖片<input type="file" name="icon" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="const file=$event.target.files[0]; fileName=file?file.name:'尚未選擇圖片'; preview=file?URL.createObjectURL(file):null"></label>
                </div>
            </div>
            <div class="lg:col-span-2"><label class="text-sm font-medium">說明</label><textarea name="description" rows="2" class="mt-1 w-full rounded-xl border-gray-300" placeholder="Badge 的紀念意義或取得方式">{{ old('description') }}</textarea></div>
            <div class="lg:col-span-2"><button class="min-h-11 w-full rounded-xl bg-indigo-600 px-5 text-sm font-medium text-white hover:bg-indigo-500 sm:w-auto">建立官方 Badge</button></div>
        </form>
    </section>

    <section><div class="mb-4 flex items-center justify-between"><h2 class="text-lg font-semibold">Badge 列表</h2><span class="text-sm text-gray-500">{{ $badges->total() }} 個</span></div>
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse($badges as $badge)
                <article class="flex flex-col rounded-2xl border bg-white p-5 shadow-sm">
                    <div class="flex items-start gap-3"><img src="{{ $badge->icon_url }}" alt="{{ $badge->name }}" class="h-16 w-16 shrink-0 rounded-2xl object-cover ring-1 ring-gray-200"><div class="min-w-0 flex-1"><div class="flex items-start justify-between gap-2"><h3 class="break-words font-semibold">{{ $badge->name }}</h3><span class="shrink-0 rounded-full px-2 py-1 text-xs {{ $badge->is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">{{ $badge->is_active ? '正常' : '已停用' }}</span></div><p class="mt-1 text-xs text-gray-500">{{ $badge->issuer_name }}{{ $badge->display_activity_name ? '・'.$badge->display_activity_name : '' }}</p></div></div>
                    @if($badge->description)<p class="mt-4 line-clamp-2 text-sm text-gray-600">{{ $badge->description }}</p>@endif
                    <div class="mt-4 grid grid-cols-3 gap-2 text-center"><div class="rounded-xl bg-gray-50 p-2"><p class="font-semibold">{{ $badge->claims_count }}</p><p class="text-xs text-gray-500">申請</p></div><div class="rounded-xl bg-gray-50 p-2"><p class="font-semibold">{{ $badge->active_awards_count }}</p><p class="text-xs text-gray-500">已發放</p></div><div class="rounded-xl {{ $badge->isAtCapacity() ? 'bg-yellow-50' : 'bg-gray-50' }} p-2"><p class="font-semibold {{ $badge->isAtCapacity() ? 'text-yellow-700' : '' }}">{{ $badge->max_supply ?? '∞' }}</p><p class="text-xs text-gray-500">上限</p></div></div>
                    @if($badge->isAtCapacity())<p class="mt-3 rounded-xl bg-yellow-50 p-3 text-sm font-medium text-yellow-800">徽章數量已達到最大值</p>@endif

                    <div class="mt-auto space-y-3 border-t pt-4">
                        @if($badge->issuer_type === 'platform' && !$badge->isAtCapacity() && $badge->is_active)
                            <form method="POST" action="{{ route('admin.badges.award', $badge) }}" class="grid gap-2">@csrf<input name="member" required class="min-h-11 w-full rounded-xl border-gray-300 text-sm" placeholder="會員 UUID 或 Email"><input name="note" class="min-h-11 w-full rounded-xl border-gray-300 text-sm" placeholder="授予原因（選填）"><button class="min-h-11 rounded-xl bg-gray-900 text-sm font-medium text-white">發放給會員</button></form>
                        @endif
                        <div class="grid grid-cols-2 gap-2">@if($badge->event)<a href="{{ route('organizer.events.badges.show', [$badge->event, $badge]) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border text-sm">查看紀錄</a>@else<span></span>@endif<form method="POST" action="{{ route('admin.badges.toggle', $badge) }}">@csrf @method('PATCH')<button class="min-h-11 w-full rounded-xl border text-sm {{ $badge->is_active ? 'border-red-200 text-red-600' : 'border-green-200 text-green-700' }}">{{ $badge->is_active ? '停用' : '重新啟用' }}</button></form></div>
                    </div>
                </article>
            @empty <p class="text-sm text-gray-500">尚無 Badge。</p> @endforelse
        </div>
        <div class="mt-6">{{ $badges->links() }}</div>
    </section>
</div>
@endsection
