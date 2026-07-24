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
<div class="mx-auto min-h-screen max-w-7xl space-y-3 px-3 py-3 sm:px-5 sm:py-4 lg:flex lg:h-[calc(100dvh-4rem)] lg:min-h-0 lg:flex-col lg:overflow-hidden">
    <header class="shrink-0 rounded-2xl bg-gray-900 p-4 text-white">
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
        <form method="POST" action="{{ route('scoring-stations.ends.store',$target->access_token) }}" id="station-form" class="space-y-3 lg:flex lg:min-h-0 lg:flex-1 lg:flex-col">
            @csrf
            <input type="hidden" name="end_number" value="{{ $endNumber }}">
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4 lg:min-h-0 lg:flex-1">
            @foreach($target->assignments as $assignment)
                @php
                    $historyEntries=$assignment->registration->scoreEntries;
                    $historyTotal=$historyEntries->sum('end_total');
                    $historyScores=$historyEntries->flatMap(fn($entry)=>$entry->scores ?? []);
                    $historyX=$historyScores->filter(fn($score)=>strtoupper((string)$score)==='X')->count();
                    $historyTenPlus=$historyScores->filter(fn($score)=>strtoupper((string)$score)==='X' || (string)$score==='10')->count();
                    $runningTotal=0;
                @endphp
                <section class="athlete-card rounded-2xl border bg-white p-3 shadow-sm lg:min-h-0 lg:overflow-y-auto" data-registration="{{ $assignment->registration->id }}">
                    <div class="flex items-center justify-between gap-3"><div><p class="text-xs font-semibold text-indigo-600">{{ $target->target_number.$assignment->position }}</p><h2 class="text-lg font-bold">{{ $assignment->registration->name }}</h2></div><p class="end-total text-2xl font-bold">0</p></div>
                    <div class="mt-2 grid grid-cols-4 gap-1.5 text-center">
                        <div class="rounded-lg bg-gray-50 p-1.5"><p class="text-sm font-semibold">{{ $historyEntries->count() }}</p><p class="text-[10px] text-gray-500">趟數</p></div>
                        <div class="rounded-lg bg-gray-50 p-1.5"><p class="text-sm font-semibold">{{ $historyTotal }}</p><p class="text-[10px] text-gray-500">累計</p></div>
                        <div class="rounded-lg bg-indigo-50 p-1.5"><p class="text-sm font-semibold text-indigo-700">{{ $historyTenPlus }}</p><p class="text-[10px] text-indigo-600">10+X</p></div>
                        <div class="rounded-lg bg-purple-50 p-1.5"><p class="text-sm font-semibold text-purple-700">{{ $historyX }}</p><p class="text-[10px] text-purple-600">X</p></div>
                    </div>
                    <div class="mt-3 grid gap-1.5" style="grid-template-columns: repeat({{ $session->arrows_per_end }}, minmax(0, 1fr));">
                        @for($arrow=0;$arrow<$session->arrows_per_end;$arrow++)
                            <input name="scores[{{ $assignment->registration->id }}][]" required readonly inputmode="none" maxlength="2"
                                   placeholder="＿"
                                   aria-label="{{ $assignment->registration->name }} 第 {{ $arrow+1 }} 箭"
                                   class="score-input min-h-12 min-w-0 touch-manipulation cursor-pointer select-none rounded-lg border-gray-300 p-1 text-center text-lg font-bold placeholder:text-gray-300 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                        @endfor
                    </div>
                    <details class="mt-3 rounded-xl border bg-gray-50">
                        <summary class="flex min-h-10 cursor-pointer items-center justify-between gap-3 px-3 text-xs font-medium"><span>各趟成績</span><span class="font-normal text-gray-500">{{ $historyEntries->count() }} 趟</span></summary>
                        <div class="overflow-x-auto border-t bg-white">
                            @if($historyEntries->isEmpty())
                                <p class="p-4 text-center text-sm text-gray-500">尚無已送出的成績。</p>
                            @else
                                <table class="min-w-full text-sm">
                                    <thead class="bg-gray-50 text-xs text-gray-500"><tr><th class="px-3 py-2 text-left">趟次</th><th class="px-3 py-2 text-left">箭值</th><th class="px-3 py-2 text-right">小計</th><th class="px-3 py-2 text-right">累計</th></tr></thead>
                                    <tbody class="divide-y">
                                    @foreach($historyEntries as $historyEntry)
                                        @php($runningTotal += $historyEntry->end_total)
                                        <tr>
                                            <td class="whitespace-nowrap px-3 py-2 font-medium">第 {{ $historyEntry->end_number }} 趟</td>
                                            <td class="whitespace-nowrap px-3 py-2 font-mono">{{ implode(' · ', $historyEntry->scores ?? []) }}</td>
                                            <td class="px-3 py-2 text-right font-semibold">{{ $historyEntry->end_total }}</td>
                                            <td class="px-3 py-2 text-right">{{ $runningTotal }}</td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            @endif
                        </div>
                    </details>
                </section>
            @endforeach
            </div>

            <section id="keypad-panel" class="shrink-0 rounded-2xl border bg-white p-3 shadow-sm">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <div><p class="text-xs text-gray-500">連續計分</p><p id="active-arrow-label" class="text-sm font-semibold">從第一個空格開始</p></div>
                    <span id="active-arrow-value" class="inline-flex h-11 min-w-12 items-center justify-center rounded-xl bg-indigo-50 px-3 text-xl font-bold text-indigo-700">—</span>
                </div>
                <div class="grid grid-cols-4 gap-2 sm:grid-cols-8">
                    @foreach(['X','10','9','BKSP','8','7','6','PREV','5','4','3','NEXT','2','1','M','CLR'] as $key)
                        <button type="button" data-key="{{ $key }}"
                                class="score-key min-h-12 touch-manipulation select-none rounded-xl border px-2 text-lg font-semibold text-gray-900 active:bg-indigo-100"
                                style="-webkit-tap-highlight-color: transparent; -webkit-user-select: none;">
                            {{ $key === 'BKSP' ? '⌫' : ($key === 'PREV' ? '←' : ($key === 'NEXT' ? '→' : ($key === 'CLR' ? '清除' : $key))) }}
                        </button>
                    @endforeach
                </div>
                <p class="mt-2 text-center text-xs text-gray-500">按下分值會依選手與箭序自動前進；刪除會退回上一格。</p>
            </section>

            <div class="grid shrink-0 gap-2 rounded-2xl border bg-white p-3 shadow-sm sm:grid-cols-[1fr_auto] sm:items-center">
                <p id="draft-status" class="text-center text-xs text-gray-500 sm:text-left">輸入會暫存在這台設備</p>
                <button class="min-h-12 rounded-xl bg-indigo-600 px-6 text-base font-semibold text-white">核對並送出本靶第 {{ $endNumber }} 趟</button>
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
    let activeIndex=0;
    const valueOf=value=>value==='X'?10:(value==='M'||!value?0:Number(value));
    const selectInput=index=>{
        activeIndex=Math.max(0,Math.min(inputs.length-1,index));
        inputs.forEach(input=>input.classList.remove('ring-2','ring-indigo-500','bg-indigo-50'));
        const input=inputs[activeIndex];
        input.classList.add('ring-2','ring-indigo-500','bg-indigo-50');
        const card=input.closest('.athlete-card');
        const athlete=card?.querySelector('h2')?.textContent?.trim()||'選手';
        const arrowIndex=Array.from(card.querySelectorAll('.score-input')).indexOf(input)+1;
        activeLabel.textContent=`${athlete} · 第 ${arrowIndex} 箭`;
        activeValue.textContent=input.value||'—';
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
    inputs.forEach((input,index)=>{
        input.addEventListener('pointerdown',event=>{event.preventDefault();selectInput(index)});
        input.addEventListener('click',event=>event.preventDefault());
    });
    document.querySelectorAll('.score-key').forEach(key=>key.addEventListener('click',()=>{
        const action=key.dataset.key;
        const input=inputs[activeIndex];
        if(!input) return;
        if(action==='PREV'){selectInput(activeIndex-1);return}
        if(action==='NEXT'){selectInput(activeIndex+1);return}
        if(action==='BKSP'){
            const previousIndex=Math.max(0,activeIndex-1);
            inputs[previousIndex].value='';
            selectInput(previousIndex);
            save();
            return;
        }
        if(action==='CLR'){
            input.value='';
            save();
            return;
        }
        input.value=action;
        save();
        if(activeIndex<inputs.length-1) {
            selectInput(activeIndex+1);
        }
    }));
    recalc();
    activeIndex=inputs.findIndex(input=>!input.value) === -1 ? 0 : inputs.findIndex(input=>!input.value);
    selectInput(activeIndex);
    form.addEventListener('submit',event=>{
        if(!confirm('請確認同靶所有選手都已核對本趟箭值。送出後一般計分台不能修改，確定送出？')){event.preventDefault();return}
        localStorage.removeItem(storageKey);
    });
})();
</script>
@endif
@endsection
