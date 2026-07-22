@extends('layouts.app')

@section('title', $event->name.' Badge 管理')

@section('content')
<div class="mx-auto max-w-6xl space-y-7 px-4 py-8 sm:px-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <a href="{{ route('organizer.events.show', $event) }}" class="text-sm text-indigo-600">← 返回賽事工作台</a>
            <h1 class="mt-2 text-2xl font-bold">Badge 發放管理</h1>
            <p class="mt-1 text-sm text-gray-500">{{ $event->name }}・由會員掃碼申請，主辦方確認後正式派發。</p>
        </div>
    </div>

    @if(session('success'))<div class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-700">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>@endif

    <section class="rounded-2xl border bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold">建立 Badge</h2>
        <form method="POST" action="{{ route('organizer.events.badges.store', $event) }}" class="mt-5 grid gap-4 md:grid-cols-2">
            @csrf
            <div><label class="text-sm font-medium">名稱 *</label><input name="name" required value="{{ old('name') }}" class="mt-1 w-full rounded-xl border-gray-300" placeholder="例：2026 台北公開賽參賽者"></div>
            <div><label class="text-sm font-medium">類型 *</label><select name="type" class="mt-1 w-full rounded-xl border-gray-300"><option value="participant">參賽</option><option value="finisher">完賽</option><option value="staff">工作人員</option><option value="volunteer">志工</option><option value="special">特別 Badge</option></select></div>
            <div><label class="text-sm font-medium">申請資格 *</label><select name="eligibility" class="mt-1 w-full rounded-xl border-gray-300"><option value="registered">已有有效報名</option><option value="checked_in">已完成報到</option><option value="scored">已有有效成績</option><option value="any">不限資格</option></select></div>
            <div class="flex items-end"><label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="claim_enabled" value="1" class="rounded border-gray-300"> 建立後立即開放 QR 申請</label></div>
            <div><label class="text-sm font-medium">開始時間</label><input type="datetime-local" name="claim_starts_at" class="mt-1 w-full rounded-xl border-gray-300"></div>
            <div><label class="text-sm font-medium">截止時間</label><input type="datetime-local" name="claim_ends_at" class="mt-1 w-full rounded-xl border-gray-300"></div>
            <div class="md:col-span-2"><label class="text-sm font-medium">說明</label><textarea name="description" rows="2" class="mt-1 w-full rounded-xl border-gray-300" placeholder="取得條件與 Badge 說明">{{ old('description') }}</textarea></div>
            <div class="md:col-span-2 text-right"><button class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-indigo-500">建立 Badge</button></div>
        </form>
    </section>

    <section>
        <h2 class="mb-4 text-lg font-semibold">目前的 Badge</h2>
        <div class="grid gap-4 md:grid-cols-2">
            @forelse($badges as $badge)
                <a href="{{ route('organizer.events.badges.show', [$event, $badge]) }}" class="rounded-2xl border bg-white p-5 shadow-sm hover:border-indigo-300">
                    <div class="flex items-start justify-between gap-3">
                        <div><p class="font-semibold">{{ $badge->name }}</p><p class="mt-1 text-sm text-gray-500">{{ $badge->description ?: '尚無說明' }}</p></div>
                        <span class="rounded-full px-2 py-1 text-xs {{ $badge->isClaimOpen() ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">{{ $badge->isClaimOpen() ? '申請中' : '未開放' }}</span>
                    </div>
                    <div class="mt-4 flex gap-4 border-t pt-3 text-sm text-gray-600"><span>待審 {{ $badge->pending_claims_count }}</span><span>申請 {{ $badge->claims_count }}</span><span>已授予 {{ $badge->active_awards_count }}</span></div>
                </a>
            @empty
                <p class="text-sm text-gray-500">尚未建立 Badge。</p>
            @endforelse
        </div>
    </section>
</div>
@endsection
