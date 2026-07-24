@extends('layouts.app')

@section('title', '靶號 '.$target->target_number.' 計分台')

@section('content')
@php
    $session=$target->session;
    $totalEnds=$session->totalEnds();
    $isComplete=$target->status==='completed';
    $splitEnds=(int) ceil(($session->event->mode==='indoor' ? 30 : 36)/$session->arrows_per_end);
    $roundLabel=$session->total_arrows > ($session->event->mode==='indoor' ? 30 : 36) ? ($endNumber <= $splitEnds ? '上半局' : '下半局') : '全程';
@endphp
<div class="mx-auto max-w-5xl space-y-4 px-3 py-4 sm:px-6 sm:py-6">
    <header class="rounded-2xl bg-gray-900 p-4 text-white sm:p-5">
        <div class="flex items-start justify-between gap-3">
            <div><p class="text-xs text-gray-300">{{ $session->event->name }} · {{ $session->name }}</p><h1 class="mt-1 text-2xl font-bold">靶號 {{ str_pad($target->target_number,2,'0',STR_PAD_LEFT) }}</h1><p class="mt-1 text-sm text-gray-300">{{ $session->group?->name }}</p></div>
            <div class="text-right"><p class="text-xs text-gray-300">{{ $roundLabel }}</p><p class="text-xl font-bold">{{ $isComplete ? '計分完成' : '第 '.$endNumber.' / '.$totalEnds.' 趟' }}</p></div>
        </div>
        <div class="mt-4 h-2 rounded-full bg-white/20"><div class="h-2 rounded-full bg-emerald-400" style="width: {{ $totalEnds ? round(($target->last_completed_end/$totalEnds)*100) : 0 }}%"></div></div>
    </header>

    @if(session('success'))<div class="rounded-xl bg-green-50 p-4 text-sm text-green-700">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="rounded-xl bg-red-50 p-4 text-sm text-red-700">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="rounded-xl bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>@endif

    @if($isComplete)
        <div class="rounded-2xl border bg-white p-8 text-center shadow-sm"><p class="text-4xl">✓</p><h2 class="mt-3 text-xl font-bold">本靶已完成全部計分</h2><p class="mt-2 text-sm text-gray-500">請保留紙本記分卡，並交由主辦方進行最終核對。</p></div>
    @else
        <form method="POST" action="{{ route('scoring-stations.ends.store',$target->access_token) }}" id="station-form" class="space-y-4">
            @csrf
            <input type="hidden" name="end_number" value="{{ $endNumber }}">
            @foreach($target->assignments as $assignment)
                <section class="athlete-card rounded-2xl border bg-white p-4 shadow-sm" data-registration="{{ $assignment->registration->id }}">
                    <div class="flex items-center justify-between gap-3"><div><p class="text-xs font-semibold text-indigo-600">{{ $target->target_number.$assignment->position }}</p><h2 class="text-lg font-bold">{{ $assignment->registration->name }}</h2></div><p class="end-total text-2xl font-bold">0</p></div>
                    <div class="mt-4 grid gap-2" style="grid-template-columns: repeat({{ $session->arrows_per_end }}, minmax(0, 1fr));">
                        @for($arrow=0;$arrow<$session->arrows_per_end;$arrow++)
                            <select name="scores[{{ $assignment->registration->id }}][]" required class="score-input min-h-14 min-w-0 rounded-xl border-gray-300 p-1 text-center text-lg font-bold">
                                <option value="">—</option>@foreach(['X','10','9','8','7','6','5','4','3','2','1','M'] as $value)<option value="{{ $value }}">{{ $value }}</option>@endforeach
                            </select>
                        @endfor
                    </div>
                </section>
            @endforeach

            <div class="sticky bottom-3 rounded-2xl border bg-white/95 p-3 shadow-xl backdrop-blur">
                <p id="draft-status" class="mb-2 text-center text-xs text-gray-500">輸入會暫存在這台設備</p>
                <button class="min-h-14 w-full rounded-xl bg-indigo-600 px-5 text-base font-semibold text-white">核對並送出本靶第 {{ $endNumber }} 趟</button>
            </div>
        </form>
    @endif
</div>

@if(!$isComplete)
<script>
(() => {
    const form=document.getElementById('station-form');
    const storageKey='scoring:{{ $target->access_token }}:end:{{ $endNumber }}';
    const inputs=Array.from(document.querySelectorAll('.score-input'));
    const status=document.getElementById('draft-status');
    const valueOf=value=>value==='X'?10:(value==='M'||!value?0:Number(value));
    const recalc=()=>{
        document.querySelectorAll('.athlete-card').forEach(card=>{
            card.querySelector('.end-total').textContent=Array.from(card.querySelectorAll('.score-input')).reduce((sum,input)=>sum+valueOf(input.value),0);
        });
    };
    const save=()=>{
        localStorage.setItem(storageKey,JSON.stringify(inputs.map(input=>input.value)));
        status.textContent='已暫存在這台設備 · '+new Date().toLocaleTimeString();
        recalc();
    };
    try {
        const saved=JSON.parse(localStorage.getItem(storageKey)||'null');
        if(Array.isArray(saved)) inputs.forEach((input,index)=>input.value=saved[index]||'');
    } catch(e) {}
    inputs.forEach(input=>input.addEventListener('change',save));
    recalc();
    form.addEventListener('submit',event=>{
        if(!confirm('請確認同靶所有選手都已核對本趟箭值。送出後一般計分台不能修改，確定送出？')){event.preventDefault();return}
        localStorage.removeItem(storageKey);
    });
})();
</script>
@endif
@endsection
