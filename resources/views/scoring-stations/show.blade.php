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
                            <input name="scores[{{ $assignment->registration->id }}][]" required readonly inputmode="none" maxlength="2"
                                   placeholder="＿"
                                   aria-label="{{ $assignment->registration->name }} 第 {{ $arrow+1 }} 箭"
                                   class="score-input min-h-14 min-w-0 touch-manipulation cursor-pointer select-none rounded-xl border-gray-300 p-1 text-center text-xl font-bold placeholder:text-gray-300 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                        @endfor
                    </div>
                </section>
            @endforeach

            <section id="keypad-sheet" class="fixed inset-0 z-50 hidden items-end justify-center bg-black/30 p-3 sm:items-center">
              <div class="w-full max-w-md rounded-2xl border bg-white p-3 shadow-2xl sm:p-4">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <div><p class="text-xs text-gray-500">目前輸入</p><p id="active-arrow-label" class="text-sm font-semibold">請點選一個箭位</p></div>
                    <div class="flex items-center gap-2"><span id="active-arrow-value" class="inline-flex h-12 min-w-12 items-center justify-center rounded-xl bg-indigo-50 px-3 text-xl font-bold text-indigo-700">—</span><button id="close-keypad" type="button" class="inline-flex h-12 w-12 items-center justify-center rounded-xl border text-gray-500" aria-label="關閉鍵盤">✕</button></div>
                </div>
                <div class="grid grid-cols-4 gap-2">
                    @foreach(['X','10','9','BKSP','8','7','6','PREV','5','4','3','NEXT','2','1','M','CLR'] as $key)
                        <button type="button" data-key="{{ $key }}"
                                class="score-key min-h-14 touch-manipulation select-none rounded-xl border px-2 text-lg font-semibold text-gray-900 active:bg-indigo-100"
                                style="-webkit-tap-highlight-color: transparent; -webkit-user-select: none;">
                            {{ $key === 'BKSP' ? '⌫' : ($key === 'PREV' ? '←' : ($key === 'NEXT' ? '→' : ($key === 'CLR' ? '清除' : $key))) }}
                        </button>
                    @endforeach
                </div>
              </div>
            </section>

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
    const activeLabel=document.getElementById('active-arrow-label');
    const activeValue=document.getElementById('active-arrow-value');
    const keypad=document.getElementById('keypad-sheet');
    const closeKeypad=document.getElementById('close-keypad');
    let activeIndex=0;
    const valueOf=value=>value==='X'?10:(value==='M'||!value?0:Number(value));
    const selectInput=index=>{
        activeIndex=Math.max(0,Math.min(inputs.length-1,index));
        inputs.forEach(input=>input.classList.remove('ring-2','ring-indigo-500','bg-indigo-50'));
        const input=inputs[activeIndex];
        input.classList.add('ring-2','ring-indigo-500','bg-indigo-50');
        input.focus();
        const card=input.closest('.athlete-card');
        const athlete=card?.querySelector('h2')?.textContent?.trim()||'選手';
        const arrowIndex=Array.from(card.querySelectorAll('.score-input')).indexOf(input)+1;
        activeLabel.textContent=`${athlete} · 第 ${arrowIndex} 箭`;
        activeValue.textContent=input.value||'—';
        keypad.classList.remove('hidden');
        keypad.classList.add('flex');
    };
    const recalc=()=>{
        document.querySelectorAll('.athlete-card').forEach(card=>{
            card.querySelector('.end-total').textContent=Array.from(card.querySelectorAll('.score-input')).reduce((sum,input)=>sum+valueOf(input.value),0);
        });
        activeValue.textContent=inputs[activeIndex]?.value||'—';
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
    inputs.forEach((input,index)=>input.addEventListener('click',()=>selectInput(index)));
    closeKeypad.addEventListener('click',()=>{keypad.classList.add('hidden');keypad.classList.remove('flex')});
    keypad.addEventListener('click',event=>{if(event.target===keypad){keypad.classList.add('hidden');keypad.classList.remove('flex')}});
    document.querySelectorAll('.score-key').forEach(key=>key.addEventListener('click',()=>{
        const action=key.dataset.key;
        const input=inputs[activeIndex];
        if(!input) return;
        if(action==='PREV'){selectInput(activeIndex-1);return}
        if(action==='NEXT'){selectInput(activeIndex+1);return}
        if(action==='BKSP'||action==='CLR'){
            input.value='';
            save();
            selectInput(activeIndex-1);
            return;
        }
        input.value=action;
        save();
        if(activeIndex<inputs.length-1) selectInput(activeIndex+1);
    }));
    recalc();
    activeIndex=inputs.findIndex(input=>!input.value) === -1 ? 0 : inputs.findIndex(input=>!input.value);
    form.addEventListener('submit',event=>{
        if(!confirm('請確認同靶所有選手都已核對本趟箭值。送出後一般計分台不能修改，確定送出？')){event.preventDefault();return}
        localStorage.removeItem(storageKey);
    });
})();
</script>
@endif
@endsection
