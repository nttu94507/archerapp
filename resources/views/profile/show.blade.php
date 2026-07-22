@extends('layouts.app')

@section('title', $member->display_name . ' 的會員資料')

@section('content')
<div class="mx-auto max-w-xl px-4 py-8 sm:px-6">
    <a href="{{ route('members.scan') }}" class="text-sm text-indigo-600 hover:text-indigo-500">← 繼續掃描</a>
    <div class="mt-4 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
        <p class="text-sm font-medium text-indigo-600">ArrowTrack 會員</p>
        <h1 class="mt-2 text-2xl font-bold">{{ $member->display_name }}</h1>
        <p class="mt-2 break-all font-mono text-xs text-gray-500">{{ $member->uuid }}</p>
        <dl class="mt-6 grid grid-cols-2 gap-5 border-t pt-5 text-sm">
            <div><dt class="text-gray-500">城市</dt><dd class="mt-1 font-medium">{{ $member->profile?->city ?: '未填寫' }}</dd></div>
            <div><dt class="text-gray-500">慣用手</dt><dd class="mt-1 font-medium">{{ ['left'=>'左手','right'=>'右手','both'=>'皆可'][$member->profile?->handedness] ?? '未指定' }}</dd></div>
            <div><dt class="text-gray-500">弓種</dt><dd class="mt-1 font-medium">{{ ['recurve'=>'反曲弓','compound'=>'複合弓','barebow'=>'光弓','traditional'=>'傳統弓'][$member->profile?->bow_type] ?? '未指定' }}</dd></div>
        </dl>
        <p class="mt-6 rounded-xl bg-gray-50 p-3 text-xs text-gray-500">為保護會員隱私，電話、生日與緊急聯絡資訊不會顯示。</p>
    </div>
    <section class="mt-5 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold">賽事 Badge</h2>
        <div class="mt-4 space-y-3">@forelse($member->eventBadges as $award)<article class="flex items-center gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4"><img src="{{ $award->badge->icon_url }}" alt="" class="h-14 w-14 shrink-0 rounded-xl object-cover"><div><p class="text-xs font-medium text-amber-700">主辦方驗證</p><h3 class="mt-1 font-semibold">{{ $award->badge->name }}</h3><p class="text-sm text-gray-600">{{ $award->badge->event->name }}</p></div></article>@empty<p class="text-sm text-gray-500">尚未取得賽事 Badge。</p>@endforelse</div>
    </section>
</div>
@endsection
