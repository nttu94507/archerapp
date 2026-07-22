@extends('layouts.app')

@section('title', '會員資料')

@section('content')
<div class="mx-auto max-w-3xl px-4 py-8 sm:px-6">
    <div class="mb-6 flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">會員資料</h1>
            <p class="mt-1 text-sm text-gray-500">出示 QR Code，讓其他會員快速找到你。</p>
        </div>
        <a href="{{ route('member-profile.edit') }}" class="rounded-xl border border-gray-300 bg-white px-4 py-2 text-sm font-medium hover:bg-gray-50">修改資料</a>
    </div>

    @if(session('status'))
        <div class="mb-5 rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-700">{{ session('status') }}</div>
    @endif

    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
        <div class="space-y-5">
            <div>
                <p class="text-sm text-gray-500">顯示名稱</p>
                <p class="mt-1 text-xl font-semibold">{{ $user->display_name }}</p>
            </div>
            <div>
                <p class="text-sm text-gray-500">會員 QR Code</p>
                <div class="mt-2 w-full max-w-64 rounded-2xl border bg-white p-3">
                    <img src="{{ route('member-profile.qrcode') }}" alt="會員 QR Code" class="aspect-square w-full">
                </div>
                <p class="mt-2 text-xs text-gray-500">讓其他會員掃描即可查看你的會員資料</p>
            </div>
            <dl class="grid grid-cols-2 gap-4 border-t pt-5 text-sm">
                <div><dt class="text-gray-500">城市</dt><dd class="mt-1 font-medium">{{ $user->profile?->city ?: '未填寫' }}</dd></div>
                <div><dt class="text-gray-500">慣用手</dt><dd class="mt-1 font-medium">{{ ['left'=>'左手','right'=>'右手','both'=>'皆可'][$user->profile?->handedness] ?? '未指定' }}</dd></div>
                <div><dt class="text-gray-500">弓種</dt><dd class="mt-1 font-medium">{{ ['recurve'=>'反曲弓','compound'=>'複合弓','barebow'=>'光弓','traditional'=>'傳統弓'][$user->profile?->bow_type] ?? '未指定' }}</dd></div>
            </dl>
            <a href="{{ route('members.scan') }}" class="inline-flex rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-indigo-500">掃描會員 QR Code</a>
        </div>
    </div>

    <section class="mt-6 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
        <div class="flex items-center justify-between gap-4"><div><h2 class="text-lg font-semibold">賽事 Badge</h2><p class="mt-1 text-sm text-gray-500">由賽事主辦方驗證並授予。</p></div><span class="text-sm text-gray-500">{{ $user->eventBadges->count() }} 枚</span></div>
        <div class="mt-5 grid gap-4 sm:grid-cols-2">
            @forelse($user->eventBadges as $award)
                <article class="rounded-xl border border-amber-200 bg-amber-50 p-4"><p class="text-xs font-medium text-amber-700">主辦方驗證</p><h3 class="mt-1 font-semibold">{{ $award->badge->name }}</h3><p class="mt-1 text-sm text-gray-600">{{ $award->badge->event->name }}</p><p class="mt-2 text-xs text-gray-500">授予日期：{{ $award->awarded_at->format('Y-m-d') }}</p></article>
            @empty
                <p class="text-sm text-gray-500">尚未取得賽事 Badge。</p>
            @endforelse
        </div>
    </section>
</div>
@endsection
