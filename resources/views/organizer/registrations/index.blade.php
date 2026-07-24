@extends('layouts.app')
@section('title', $event->name.' 報名與繳費')
@section('content')
@php
    $paymentLabels=['pending'=>'待繳費','paid'=>'已繳費','exempt'=>'免繳費','refunded'=>'已退款','issue'=>'對帳異常'];
    $paymentColors=['pending'=>'bg-yellow-100 text-yellow-800','paid'=>'bg-green-100 text-green-700','exempt'=>'bg-green-100 text-green-700','refunded'=>'bg-red-100 text-red-700','issue'=>'bg-red-100 text-red-700'];
    $statusLabels=['registered'=>'已報名','checked_in'=>'已報到','withdrawn'=>'已退出','refunded'=>'已退款','no_show'=>'未到'];
@endphp
<div class="mx-auto max-w-6xl space-y-5 px-4 py-6 sm:px-6">
    <div><a href="{{ route('organizer.events.show',$event) }}" class="text-sm text-indigo-600">← 返回賽事工作台</a><h1 class="mt-2 text-2xl font-bold">報名與繳費</h1></div>
    @if(session('success'))<div class="rounded-xl bg-green-50 p-4 text-sm text-green-700">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="rounded-xl bg-red-50 p-4 text-sm text-red-700">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="rounded-xl bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>@endif

    <form method="GET" class="rounded-2xl border bg-white p-4 shadow-sm">
        <label for="registration-search" class="text-sm font-semibold">搜尋已報名選手</label>
        <div class="mt-2 flex flex-col gap-2 sm:flex-row">
            <input id="registration-search" name="q" value="{{ request('q') }}" class="min-h-12 min-w-0 flex-1 rounded-xl border-gray-300 text-base sm:text-sm" placeholder="輸入姓名、暱稱、Email、會員編號、隊伍或組別">
            <select name="event_group_id" class="min-h-12 rounded-xl border-gray-300 text-base sm:text-sm"><option value="">全部組別</option>@foreach($groups as $group)<option value="{{ $group->id }}" @selected(request('event_group_id')==$group->id)>{{ $group->name }}</option>@endforeach</select>
            <select name="payment_status" class="min-h-12 rounded-xl border-gray-300 text-base sm:text-sm"><option value="">全部繳費狀態</option>@foreach($paymentLabels as $value=>$label)<option value="{{ $value }}" @selected(request('payment_status')===$value)>{{ $label }}</option>@endforeach</select>
            <button class="min-h-12 rounded-xl bg-gray-900 px-5 text-sm font-medium text-white">搜尋</button>
            @if(request()->hasAny(['q','event_group_id','payment_status']))<a href="{{ route('organizer.events.registrations.index',$event) }}" class="inline-flex min-h-12 items-center justify-center rounded-xl border px-4 text-sm">清除</a>@endif
        </div>
    </form>

    <div class="grid gap-3 md:grid-cols-2">
        @forelse($registrations as $registration)
        @php($payStatus=$registration->payment_status ?? ($registration->paid?'paid':'pending'))
        <article class="rounded-2xl border bg-white p-4 shadow-sm">
            <div class="flex items-start gap-3">
                <input form="bulk-payment" type="checkbox" name="registration_ids[]" value="{{ $registration->id }}" class="reg-check mt-1 h-5 w-5 rounded" aria-label="選取 {{ $registration->name }}">
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div><h3 class="text-lg font-semibold">{{ $registration->name }}</h3><p class="break-all text-xs text-gray-500">{{ $registration->email }}</p></div>
                        <span class="rounded-full px-3 py-1 text-xs font-medium {{ $paymentColors[$payStatus] ?? 'bg-gray-100 text-gray-600' }}">{{ $paymentLabels[$payStatus] ?? $payStatus }}</span>
                    </div>
                    <p class="mt-3 text-sm font-medium">{{ $registration->event_group?->name ?: '未指定組別' }}</p>
                    <div class="mt-3 grid grid-cols-2 gap-2 text-sm">
                        <div class="rounded-xl bg-gray-50 p-3"><span class="text-xs text-gray-500">報名狀態</span><p class="mt-1 font-medium">{{ $statusLabels[$registration->status] ?? $registration->status }}</p></div>
                        <div class="rounded-xl bg-gray-50 p-3"><span class="text-xs text-gray-500">報名費</span><p class="mt-1 font-medium">{{ (int) $registration->event_group?->fee > 0 ? 'NT$ '.number_format($registration->event_group->fee) : '免費' }}</p></div>
                    </div>
                    @if($payStatus === 'paid')
                        <div class="mt-4 flex items-center justify-between gap-3 rounded-xl bg-green-50 p-3 text-sm text-green-800">
                            <span>已完成繳費{{ $registration->payment_confirmed_at ? ' · '.$registration->payment_confirmed_at->format('m/d H:i') : '' }}</span>
                            <form method="POST" action="{{ route('organizer.events.registrations.payment',$event) }}">@csrf @method('PATCH')<input type="hidden" name="registration_ids[]" value="{{ $registration->id }}"><input type="hidden" name="payment_status" value="pending"><button class="min-h-10 px-2 text-xs font-medium text-gray-600">改回待繳費</button></form>
                        </div>
                    @elseif((int) $registration->event_group?->fee === 0)
                        <form method="POST" action="{{ route('organizer.events.registrations.payment',$event) }}" class="mt-4">@csrf @method('PATCH')<input type="hidden" name="registration_ids[]" value="{{ $registration->id }}"><input type="hidden" name="payment_status" value="exempt"><button class="min-h-11 w-full rounded-xl bg-emerald-600 px-4 text-sm font-medium text-white">確認為免費／免繳</button></form>
                    @else
                        <form method="POST" action="{{ route('organizer.events.registrations.payment',$event) }}" class="mt-4">@csrf @method('PATCH')<input type="hidden" name="registration_ids[]" value="{{ $registration->id }}"><input type="hidden" name="payment_status" value="paid"><input type="hidden" name="payment_amount" value="{{ $registration->event_group?->fee }}"><button class="min-h-11 w-full rounded-xl bg-indigo-600 px-4 text-sm font-medium text-white">標記為繳費完成</button></form>
                    @endif
                </div>
            </div>
        </article>
        @empty <p class="text-sm text-gray-500">找不到符合條件的報名。</p> @endforelse
    </div>

    <details class="rounded-2xl border bg-white p-4">
        <summary class="flex min-h-11 cursor-pointer items-center justify-between gap-3 font-semibold"><span>批次更新繳費狀態</span><span class="text-xs font-normal text-gray-500">選取多位選手時使用</span></summary>
        <form id="bulk-payment" method="POST" action="{{ route('organizer.events.registrations.payment',$event) }}" class="mt-4 border-t pt-4">@csrf @method('PATCH')
            <div class="flex justify-end"><label class="inline-flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" class="rounded" onclick="document.querySelectorAll('.reg-check').forEach(el=>el.checked=this.checked)"> 全選本頁</label></div>
            <div class="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-5">
                <select name="payment_status" required class="min-h-11 rounded-xl border-gray-300 text-sm">@foreach($paymentLabels as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>
                <input name="payment_amount" type="number" min="0" step="0.01" class="min-h-11 rounded-xl border-gray-300 text-sm" placeholder="金額（選填）">
                <select name="payment_method" class="min-h-11 rounded-xl border-gray-300 text-sm"><option value="">付款方式</option><option value="transfer">轉帳</option><option value="cash">現金</option><option value="other">其他</option></select>
                <input name="payment_reference" class="min-h-11 rounded-xl border-gray-300 text-sm" placeholder="帳號末五碼／交易編號">
                <button class="min-h-11 rounded-xl bg-indigo-600 px-4 text-sm font-medium text-white">套用至選取選手</button>
            </div>
            <input name="payment_note" class="mt-2 min-h-11 w-full rounded-xl border-gray-300 text-sm" placeholder="備註（選填）">
        </form>
    </details>

    <aside class="rounded-2xl border bg-white p-5 shadow-sm"><h2 class="font-semibold">掃會員 QR Code 報到</h2><video id="checkin-video" playsinline muted class="mt-3 hidden aspect-square w-full max-w-sm rounded-xl bg-gray-900 object-cover"></video><p id="checkin-status" class="mt-2 text-sm text-gray-500">掃描會員 QR Code，或輸入會員編號。</p><button id="start-camera" type="button" class="mt-3 w-full rounded-xl border px-4 py-3 text-sm sm:w-auto">開啟相機</button><form id="checkin-form" method="POST" action="{{ route('organizer.events.registrations.check-in',$event) }}" class="mt-3 flex flex-col gap-2 sm:flex-row">@csrf<input id="checkin-uuid" name="uuid" required class="min-h-11 flex-1 rounded-xl border-gray-300 text-sm" placeholder="會員 UUID"><button class="min-h-11 rounded-xl bg-green-600 px-5 text-sm text-white">確認報到</button></form></aside>
    {{ $registrations->links() }}
</div>
<script>(()=>{const button=document.getElementById('start-camera'),video=document.getElementById('checkin-video'),status=document.getElementById('checkin-status'),input=document.getElementById('checkin-uuid');button.addEventListener('click',async()=>{if(!('BarcodeDetector'in window)){status.textContent='瀏覽器不支援相機掃描，請手動輸入。';return}try{const stream=await navigator.mediaDevices.getUserMedia({video:{facingMode:{ideal:'environment'}}});video.srcObject=stream;video.classList.remove('hidden');await video.play();const detector=new BarcodeDetector({formats:['qr_code']});button.classList.add('hidden');const scan=async()=>{const codes=await detector.detect(video);if(codes[0]){let value=codes[0].rawValue;try{value=new URL(value).pathname.split('/').filter(Boolean).pop()}catch(e){}input.value=value;stream.getTracks().forEach(t=>t.stop());document.getElementById('checkin-form').submit();return}requestAnimationFrame(scan)};scan()}catch(e){status.textContent='無法開啟相機，請確認權限或手動輸入。'}})})();</script>
@endsection
