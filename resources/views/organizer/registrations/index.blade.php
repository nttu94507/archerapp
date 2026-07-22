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

    <form method="GET" class="grid gap-2 rounded-2xl border bg-white p-4 sm:grid-cols-2 lg:grid-cols-4">
        <input name="q" value="{{ request('q') }}" class="rounded-xl border-gray-300 text-sm" placeholder="姓名、Email、會員編號">
        <select name="event_group_id" class="rounded-xl border-gray-300 text-sm"><option value="">全部組別</option>@foreach($groups as $group)<option value="{{ $group->id }}" @selected(request('event_group_id')==$group->id)>{{ $group->name }}</option>@endforeach</select>
        <select name="payment_status" class="rounded-xl border-gray-300 text-sm"><option value="">全部繳費狀態</option>@foreach($paymentLabels as $value=>$label)<option value="{{ $value }}" @selected(request('payment_status')===$value)>{{ $label }}</option>@endforeach</select>
        <button class="min-h-11 rounded-xl bg-gray-900 px-4 text-sm text-white">篩選</button>
    </form>

    <form id="bulk-payment" method="POST" action="{{ route('organizer.events.registrations.payment',$event) }}" class="rounded-2xl border bg-white p-4">@csrf @method('PATCH')
        <div class="flex items-center justify-between"><h2 class="font-semibold">批次對帳</h2><label class="text-sm"><input type="checkbox" class="rounded" onclick="document.querySelectorAll('.reg-check').forEach(el=>el.checked=this.checked)"> 全選本頁</label></div>
        <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-5">
            <select name="payment_status" required class="rounded-xl border-gray-300 text-sm">@foreach($paymentLabels as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>
            <input name="payment_amount" type="number" min="0" step="0.01" class="rounded-xl border-gray-300 text-sm" placeholder="金額（選填）">
            <select name="payment_method" class="rounded-xl border-gray-300 text-sm"><option value="">付款方式</option><option value="transfer">轉帳</option><option value="cash">現金</option><option value="other">其他</option></select>
            <input name="payment_reference" class="rounded-xl border-gray-300 text-sm" placeholder="帳號末五碼／交易編號">
            <button class="min-h-11 rounded-xl bg-indigo-600 px-4 text-sm font-medium text-white">套用至選取項目</button>
        </div>
        <input name="payment_note" class="mt-2 w-full rounded-xl border-gray-300 text-sm" placeholder="備註（選填）">
    </form>

    <div class="grid gap-3 md:grid-cols-2">
        @forelse($registrations as $registration)
        @php($payStatus=$registration->payment_status ?? ($registration->paid?'paid':'pending'))
        <article class="rounded-2xl border bg-white p-4 shadow-sm">
            <div class="flex items-start gap-3"><input form="bulk-payment" type="checkbox" name="registration_ids[]" value="{{ $registration->id }}" class="reg-check mt-1 rounded"><div class="min-w-0 flex-1"><div class="flex flex-wrap items-start justify-between gap-2"><div><h3 class="font-semibold">{{ $registration->name }}</h3><p class="text-xs text-gray-500">{{ $registration->event_group?->name }}・{{ $registration->email }}</p></div><span class="rounded-full px-2 py-1 text-xs {{ $paymentColors[$payStatus] ?? 'bg-gray-100 text-gray-600' }}">{{ $paymentLabels[$payStatus] ?? $payStatus }}</span></div>
                <div class="mt-3 grid grid-cols-2 gap-2 text-sm"><div class="rounded-xl bg-gray-50 p-2"><span class="text-xs text-gray-500">報名</span><p>{{ $statusLabels[$registration->status] ?? $registration->status }}</p></div><div class="rounded-xl bg-gray-50 p-2"><span class="text-xs text-gray-500">對帳</span><p>{{ $registration->payment_confirmed_at?->format('m/d H:i') ?? '尚未確認' }}</p></div></div>
            </div></div>
        </article>
        @empty <p class="text-sm text-gray-500">找不到符合條件的報名。</p> @endforelse
    </div>

    <aside class="rounded-2xl border bg-white p-5 shadow-sm"><h2 class="font-semibold">掃會員 QR Code 報到</h2><video id="checkin-video" playsinline muted class="mt-3 hidden aspect-square w-full max-w-sm rounded-xl bg-gray-900 object-cover"></video><p id="checkin-status" class="mt-2 text-sm text-gray-500">掃描會員 QR Code，或輸入會員編號。</p><button id="start-camera" type="button" class="mt-3 w-full rounded-xl border px-4 py-3 text-sm sm:w-auto">開啟相機</button><form id="checkin-form" method="POST" action="{{ route('organizer.events.registrations.check-in',$event) }}" class="mt-3 flex flex-col gap-2 sm:flex-row">@csrf<input id="checkin-uuid" name="uuid" required class="min-h-11 flex-1 rounded-xl border-gray-300 text-sm" placeholder="會員 UUID"><button class="min-h-11 rounded-xl bg-green-600 px-5 text-sm text-white">確認報到</button></form></aside>
    {{ $registrations->links() }}
</div>
<script>(()=>{const button=document.getElementById('start-camera'),video=document.getElementById('checkin-video'),status=document.getElementById('checkin-status'),input=document.getElementById('checkin-uuid');button.addEventListener('click',async()=>{if(!('BarcodeDetector'in window)){status.textContent='瀏覽器不支援相機掃描，請手動輸入。';return}try{const stream=await navigator.mediaDevices.getUserMedia({video:{facingMode:{ideal:'environment'}}});video.srcObject=stream;video.classList.remove('hidden');await video.play();const detector=new BarcodeDetector({formats:['qr_code']});button.classList.add('hidden');const scan=async()=>{const codes=await detector.detect(video);if(codes[0]){let value=codes[0].rawValue;try{value=new URL(value).pathname.split('/').filter(Boolean).pop()}catch(e){}input.value=value;stream.getTracks().forEach(t=>t.stop());document.getElementById('checkin-form').submit();return}requestAnimationFrame(scan)};scan()}catch(e){status.textContent='無法開啟相機，請確認權限或手動輸入。'}})})();</script>
@endsection
