@extends('layouts.app')

@section('title', '我的賽事')

@section('content')
<div class="mx-auto max-w-5xl px-4 py-6 sm:px-6 sm:py-8">
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div><h1 class="text-2xl font-bold">我的賽事</h1><p class="mt-1 text-sm text-gray-500">集中查看報名、付款與報到狀態。</p></div>
        <a href="{{ route('events.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-indigo-600 px-5 text-sm font-medium text-white">探索賽事</a>
    </div>

    @if($events->isEmpty())
        <div class="rounded-2xl border border-dashed bg-white p-8 text-center">
            <p class="font-medium text-gray-800">目前沒有賽事報名紀錄</p>
            <p class="mt-1 text-sm text-gray-500">找到適合的組別後，最快兩步即可完成報名。</p>
            <a href="{{ route('events.index') }}" class="mt-5 inline-flex min-h-11 items-center rounded-xl bg-indigo-600 px-5 text-sm font-medium text-white">查看開放報名賽事</a>
        </div>
    @else
        <div class="space-y-4">
            @foreach($events as $row)
                @php
                    $event = $row['event']; $registration = $row['registration'];
                    $statusLabel = ['registered'=>'已報名','checked_in'=>'已報到','withdrawn'=>'已取消','pending'=>'待處理'][$registration->status] ?? $registration->status;
                    $phaseLabel = ['upcoming'=>'即將舉行','ongoing'=>'進行中','finished'=>'已結束','cancelled'=>'已取消'][$row['phase']] ?? $row['phase'];
                    $isFree = (int) optional($registration->event_group)->fee === 0;
                    $paymentLabel = $isFree ? '免費' : (['pending'=>'待付款／確認','paid'=>'已付款','refunded'=>'已退款'][$registration->payment_status] ?? ($registration->paid ? '已付款' : '待付款'));
                @endphp
                <article class="rounded-2xl border bg-white p-4 shadow-sm sm:p-5">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="break-words text-lg font-semibold">{{ $event->name }}</h2>
                                <span class="rounded-full bg-gray-100 px-2 py-1 text-xs text-gray-600">{{ $phaseLabel }}</span>
                            </div>
                            <p class="mt-1 text-sm text-gray-500">{{ $event->start_date->format('Y-m-d') }}～{{ $event->end_date->format('Y-m-d') }} · {{ $event->venue ?: '場地待公布' }}</p>
                            <p class="mt-2 text-sm font-medium">{{ $registration->event_group?->name ?: '未指定組別' }}</p>
                            <div class="mt-3 flex flex-wrap gap-2 text-xs">
                                <span class="rounded-full bg-blue-50 px-3 py-1 text-blue-700">{{ $statusLabel }}</span>
                                <span class="rounded-full {{ $isFree || $registration->paid ? 'bg-emerald-50 text-emerald-700' : 'bg-orange-50 text-orange-700' }} px-3 py-1">{{ $paymentLabel }}</span>
                            </div>
                        </div>
                        <div class="shrink-0">
                            <a href="{{ route('events.show', $event) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border px-4 text-sm font-medium">賽事資訊</a>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</div>
@endsection
