@extends('layouts.app')

@section('title', '確認報名')

@section('content')
<div class="mx-auto max-w-2xl px-4 py-6 sm:px-6 sm:py-10">
    <a href="{{ route('events.show', $event) }}" class="inline-flex min-h-11 items-center text-sm font-medium text-indigo-600">← 返回賽事</a>

    <div class="mt-2 rounded-2xl border bg-white p-5 shadow-sm sm:p-7">
        <p class="text-xs font-semibold uppercase tracking-widest text-indigo-600">確認報名</p>
        <h1 class="mt-2 text-2xl font-bold text-gray-900">{{ $event->name }}</h1>
        <p class="mt-1 text-sm text-gray-500">{{ $event->start_date->format('Y-m-d') }}～{{ $event->end_date->format('Y-m-d') }} · {{ $event->venue ?: '場地待主辦方公布' }}</p>

        <dl class="mt-6 divide-y rounded-xl bg-gray-50 px-4">
            <div class="flex justify-between gap-4 py-3"><dt class="text-sm text-gray-500">報名組別</dt><dd class="text-right text-sm font-semibold">{{ $group->name }}</dd></div>
            <div class="flex justify-between gap-4 py-3"><dt class="text-sm text-gray-500">賽制</dt><dd class="text-right text-sm font-semibold">{{ $group->distance ?: '距離未指定' }} · {{ $group->arrow_count }} 箭</dd></div>
            <div class="flex justify-between gap-4 py-3"><dt class="text-sm text-gray-500">報名費</dt><dd class="text-right text-sm font-semibold">{{ (int) $group->fee > 0 ? 'NT$ '.number_format($group->fee) : '免費' }}</dd></div>
            <div class="flex justify-between gap-4 py-3"><dt class="text-sm text-gray-500">目前名額</dt><dd class="text-right text-sm font-semibold">{{ $registered }}{{ $group->quota ? ' / '.$group->quota : ' 人' }}</dd></div>
            <div class="flex justify-between gap-4 py-3"><dt class="text-sm text-gray-500">選手</dt><dd class="text-right text-sm font-semibold">{{ auth()->user()->display_name }}<br><span class="font-normal text-gray-500">{{ auth()->user()->email }}</span></dd></div>
        </dl>

        <p class="mt-4 text-xs leading-5 text-gray-500">送出後將立即保留名額；若有費用，付款方式與確認狀態由主辦單位管理。</p>

        <form method="POST" action="{{ route('events.quick_register', [$event, $group]) }}" class="mt-6">
            @csrf
            @if($group->is_team && $group->team_type === 'mixed')<div class="mb-5 rounded-xl bg-violet-50 p-4 text-sm text-violet-800">混雙競賽性別：<strong>{{ auth()->user()->profile?->gender === 'male' ? '男子' : '女子' }}</strong>（依會員資料帶入）</div>@endif
            <button class="min-h-12 w-full rounded-xl bg-indigo-600 px-5 text-sm font-semibold text-white hover:bg-indigo-500">確認並完成報名</button>
        </form>
    </div>
</div>
@endsection
