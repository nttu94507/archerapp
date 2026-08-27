@extends('layouts.app')

@section('title', $event->name.' 報名與繳費')

@section('content')
@php
    $paymentLabels=['pending'=>'待繳費','paid'=>'已繳費','exempt'=>'免費／免繳','refunded'=>'已退款','issue'=>'對帳異常'];
    $paymentColors=['pending'=>'bg-amber-100 text-amber-800','paid'=>'bg-emerald-100 text-emerald-700','exempt'=>'bg-emerald-100 text-emerald-700','refunded'=>'bg-gray-200 text-gray-700','issue'=>'bg-red-100 text-red-700'];
    $statusLabels=['registered'=>'已報名','checked_in'=>'已報到','withdrawn'=>'已退出','refunded'=>'已退款','no_show'=>'未報到（DNS）'];
@endphp

<div class="mx-auto max-w-6xl space-y-5 px-4 py-6 sm:px-6 sm:py-8">
    <div>
        <a href="{{ $selectedGroup ? route('organizer.events.registrations.index',$event) : route('organizer.events.show',$event) }}" class="inline-flex min-h-11 items-center text-sm font-medium text-indigo-600">← {{ $selectedGroup ? '全部組別' : '返回賽事工作台' }}</a>
        <p class="text-xs font-semibold uppercase tracking-widest text-indigo-600">Registration</p>
        <h1 class="mt-1 text-2xl font-bold">{{ $selectedGroup ? $selectedGroup->name : '報名與繳費' }}</h1>
        <p class="mt-1 text-sm text-gray-500">{{ $selectedGroup ? '管理此組別的選手報名與繳費狀態。' : '先選擇組別，再處理該組選手。' }}</p>
    </div>

    @if(session('success'))<div class="rounded-xl bg-green-50 p-4 text-sm text-green-700">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="rounded-xl bg-red-50 p-4 text-sm text-red-700">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="rounded-xl bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>@endif

    @if(!$selectedGroup)
        <section class="grid grid-cols-2 gap-3 sm:grid-cols-5">
            @foreach([
                ['組別',$totals['groups']],
                ['有效報名',$totals['registrations']],
                ['已處理繳費',$totals['paid']],
                ['待繳費',$totals['pending']],
                ['對帳異常',$totals['issues']],
            ] as [$label,$value])
                <div class="rounded-2xl border bg-white p-4 text-center shadow-sm"><p class="text-2xl font-bold">{{ $value }}</p><p class="mt-1 text-xs text-gray-500">{{ $label }}</p></div>
            @endforeach
        </section>

        <section>
            <div class="mb-3"><h2 class="text-lg font-semibold">選擇組別</h2><p class="text-xs text-gray-500">卡片會顯示各組目前的報名及繳費進度。</p></div>
            <div class="grid gap-4 md:grid-cols-2">
                @forelse($groups as $group)
                    @php
                        $active=(int)$group->active_registrations_count;
                        $fee=(int)$group->fee;
                        $settled=$fee === 0 ? $active : (int)$group->paid_registrations_count+(int)$group->exempt_registrations_count;
                        $pending=$fee === 0 ? 0 : (int)$group->pending_payment_count;
                        $paymentProgress=$active > 0 ? (int) round(($settled/$active)*100) : 0;
                    @endphp
                    <a href="{{ route('organizer.events.registrations.index',[$event,'event_group_id'=>$group->id]) }}" class="group rounded-2xl border bg-white p-5 shadow-sm transition hover:border-indigo-300 hover:shadow-md">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0"><h3 class="break-words text-lg font-semibold group-hover:text-indigo-700">{{ $group->name }}</h3><p class="mt-1 text-sm text-gray-500">{{ $group->distance ?: '距離未定' }} · {{ $group->arrow_count }} 箭 · {{ $fee > 0 ? 'NT$ '.number_format($fee) : '免費' }}</p></div>
                            <span class="shrink-0 rounded-full bg-gray-100 px-3 py-1 text-xs font-medium">{{ $active }}{{ $group->quota ? ' / '.$group->quota : ' 人' }}</span>
                        </div>
                        <div class="mt-5 grid grid-cols-3 gap-2 text-center">
                            <div class="rounded-xl bg-emerald-50 p-3"><p class="text-lg font-semibold text-emerald-700">{{ $settled }}</p><p class="text-xs text-emerald-700">已處理</p></div>
                            <div class="rounded-xl bg-amber-50 p-3"><p class="text-lg font-semibold text-amber-700">{{ $pending }}</p><p class="text-xs text-amber-700">待繳費</p></div>
                            <div class="rounded-xl bg-red-50 p-3"><p class="text-lg font-semibold text-red-700">{{ $group->payment_issue_count }}</p><p class="text-xs text-red-700">異常</p></div>
                        </div>
                        <div class="mt-4">
                            <div class="flex justify-between text-xs text-gray-500"><span>繳費處理進度</span><span>{{ $paymentProgress }}%</span></div>
                            <div class="mt-2 h-2 rounded-full bg-gray-100"><div class="h-2 rounded-full bg-indigo-600" style="width: {{ $paymentProgress }}%"></div></div>
                        </div>
                        @if($fee > 0)<div class="mt-4 flex justify-between border-t pt-3 text-sm"><span class="text-gray-500">預計應收</span><span class="font-semibold">NT$ {{ number_format($active*$fee) }}</span></div>@endif
                    </a>
                @empty
                    <div class="rounded-2xl border border-dashed bg-white p-8 text-center text-sm text-gray-500 md:col-span-2">此賽事尚未建立組別。</div>
                @endforelse
            </div>
        </section>
    @else
        @php
            $active=(int)$selectedGroup->active_registrations_count;
            $fee=(int)$selectedGroup->fee;
            $settled=$fee === 0 ? $active : (int)$selectedGroup->paid_registrations_count+(int)$selectedGroup->exempt_registrations_count;
            $pending=$fee === 0 ? 0 : (int)$selectedGroup->pending_payment_count;
        @endphp

        <form method="GET" class="rounded-2xl border border-indigo-100 bg-white p-4 shadow-sm">
            <input type="hidden" name="event_group_id" value="{{ $selectedGroup->id }}">
            <label for="registration-search" class="text-sm font-semibold">搜尋此組選手</label>
            <div class="mt-2 flex flex-col gap-2 sm:flex-row">
                <input id="registration-search" name="q" value="{{ request('q') }}" class="min-h-12 min-w-0 flex-1 rounded-xl border-gray-300 text-base sm:text-sm" placeholder="姓名、暱稱、Email、會員編號或隊伍">
                <select name="payment_status" class="min-h-12 rounded-xl border-gray-300 text-base sm:text-sm"><option value="">全部繳費狀態</option>@foreach($paymentLabels as $value=>$label)<option value="{{ $value }}" @selected(request('payment_status')===$value)>{{ $label }}</option>@endforeach</select>
                <button class="min-h-12 rounded-xl bg-gray-900 px-5 text-sm font-medium text-white">搜尋</button>
                @if(request()->filled('q') || request()->filled('payment_status'))<a href="{{ route('organizer.events.registrations.index',[$event,'event_group_id'=>$selectedGroup->id]) }}" class="inline-flex min-h-12 items-center justify-center rounded-xl border px-4 text-sm">清除</a>@endif
            </div>
        </form>

        <section class="rounded-2xl border bg-white p-4 shadow-sm sm:p-5">
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
                <div><p class="text-xs text-gray-500">名額</p><p class="mt-1 text-xl font-semibold">{{ $active }}{{ $selectedGroup->quota ? ' / '.$selectedGroup->quota : ' 人' }}</p></div>
                <div><p class="text-xs text-gray-500">報名費</p><p class="mt-1 text-xl font-semibold">{{ $fee > 0 ? 'NT$ '.number_format($fee) : '免費' }}</p></div>
                <div><p class="text-xs text-gray-500">已處理</p><p class="mt-1 text-xl font-semibold text-emerald-700">{{ $settled }}</p></div>
                <div><p class="text-xs text-gray-500">待繳費</p><p class="mt-1 text-xl font-semibold text-amber-700">{{ $pending }}</p></div>
                <div><p class="text-xs text-gray-500">對帳異常</p><p class="mt-1 text-xl font-semibold text-red-700">{{ $selectedGroup->payment_issue_count }}</p></div>
            </div>
        </section>

        <div class="grid gap-3 md:grid-cols-2">
            @forelse($registrations as $registration)
                @php
                    $payStatus=$fee === 0 ? 'exempt' : ($registration->payment_status ?? ($registration->paid?'paid':'pending'));
                @endphp
                <article class="rounded-2xl border bg-white p-4 shadow-sm">
                    <div class="flex items-start gap-3">
                        <input form="bulk-payment" type="checkbox" name="registration_ids[]" value="{{ $registration->id }}" class="reg-check mt-1 h-5 w-5 rounded" aria-label="選取 {{ $registration->name }}">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div><h3 class="text-lg font-semibold">{{ $registration->name }}</h3><p class="break-all text-xs text-gray-500">{{ $registration->email }}</p></div>
                                <span class="rounded-full px-3 py-1 text-xs font-medium {{ $paymentColors[$payStatus] ?? 'bg-gray-100 text-gray-600' }}">{{ $paymentLabels[$payStatus] ?? $payStatus }}</span>
                            </div>
                            @if($registration->team_name)<p class="mt-2 text-sm text-gray-600">隊伍：{{ $registration->team_name }}</p>@endif
                            <div class="mt-3 grid grid-cols-2 gap-2 text-sm">
                                <div class="rounded-xl bg-gray-50 p-3"><span class="text-xs text-gray-500">報名狀態</span><p class="mt-1 font-medium">{{ $statusLabels[$registration->status] ?? $registration->status }}</p></div>
                                <div class="rounded-xl bg-gray-50 p-3"><span class="text-xs text-gray-500">報名時間</span><p class="mt-1 font-medium">{{ $registration->created_at?->format('m/d H:i') }}</p></div>
                            </div>

                            @if($fee === 0)
                                <div class="mt-4 rounded-xl bg-emerald-50 p-3 text-sm font-medium text-emerald-700">免費組別，不需要對帳</div>
                            @elseif($payStatus === 'paid')
                                <div class="mt-4 flex items-center justify-between gap-3 rounded-xl bg-emerald-50 p-3 text-sm text-emerald-800">
                                    <span>已完成繳費{{ $registration->payment_confirmed_at ? ' · '.$registration->payment_confirmed_at->format('m/d H:i') : '' }}</span>
                                    <form method="POST" action="{{ route('organizer.events.registrations.payment',$event) }}">@csrf @method('PATCH')<input type="hidden" name="registration_ids[]" value="{{ $registration->id }}"><input type="hidden" name="payment_status" value="pending"><button class="min-h-10 px-2 text-xs font-medium text-gray-600">改回待繳費</button></form>
                                </div>
                            @else
                                <form method="POST" action="{{ route('organizer.events.registrations.payment',$event) }}" class="mt-4">@csrf @method('PATCH')<input type="hidden" name="registration_ids[]" value="{{ $registration->id }}"><input type="hidden" name="payment_status" value="paid"><input type="hidden" name="payment_amount" value="{{ $fee }}"><button class="min-h-11 w-full rounded-xl bg-indigo-600 px-4 text-sm font-medium text-white">標記為繳費完成</button></form>
                            @endif
                        </div>
                    </div>
                </article>
            @empty
                <div class="rounded-2xl border border-dashed bg-white p-8 text-center text-sm text-gray-500 md:col-span-2">此組目前沒有符合條件的選手。</div>
            @endforelse
        </div>

        @if($registrations->hasPages()){{ $registrations->links() }}@endif

        <form id="bulk-payment" method="POST" action="{{ route('organizer.events.registrations.payment',$event) }}" class="sticky bottom-3 z-20 hidden rounded-2xl border border-indigo-200 bg-white/95 p-3 shadow-xl backdrop-blur sm:p-4">@csrf @method('PATCH')
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <p class="shrink-0 text-sm font-semibold"><span id="selected-count">0</span> 位選手已選取</p>
                <select name="payment_status" required class="min-h-11 flex-1 rounded-xl border-gray-300 text-sm">
                    <option value="paid">標記已繳費</option><option value="pending">改為待繳費</option><option value="issue">標記對帳異常</option><option value="exempt">設為免繳</option><option value="refunded">標記已退款</option>
                </select>
                <button class="min-h-11 rounded-xl bg-indigo-600 px-5 text-sm font-medium text-white">套用至選取選手</button>
                <button id="clear-selection" type="button" class="min-h-11 rounded-xl border px-4 text-sm">取消選取</button>
            </div>
        </form>
    @endif

</div>

<script>
(() => {
    const checks = Array.from(document.querySelectorAll('.reg-check'));
    const bar = document.getElementById('bulk-payment');
    const count = document.getElementById('selected-count');
    const clear = document.getElementById('clear-selection');
    const sync = () => {
        const selected = checks.filter(check => check.checked).length;
        if (count) count.textContent = selected;
        bar?.classList.toggle('hidden', selected === 0);
    };
    checks.forEach(check => check.addEventListener('change', sync));
    clear?.addEventListener('click', () => { checks.forEach(check => check.checked = false); sync(); });

})();
</script>
@endsection
