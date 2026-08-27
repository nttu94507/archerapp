@extends('layouts.app')
@section('title', $match->label.' 複合弓累計計分')
@section('content')
@php
    $deviceMode = $deviceMode ?? false;
    $canEnter = in_array($match->status, ['ready', 'in_progress'], true) && $match->ends->count() < 5;
    $statusNames = ['ready'=>'等待比賽', 'in_progress'=>'比賽中', 'awaiting_shoot_off'=>'等待加射', 'awaiting_judge'=>'等待主裁判', 'completed'=>'比賽完成'];
@endphp
<div class="mx-auto flex min-h-[calc(100dvh-4rem)] max-w-5xl flex-col gap-4 bg-gray-50 px-3 py-4 sm:px-5">
    <header class="rounded-2xl border bg-white p-4 shadow-sm">
        @unless($deviceMode)<a href="{{ route('organizer.events.elimination.index', $event) }}" class="inline-flex min-h-10 items-center text-sm font-medium text-indigo-600">← 返回個人對抗表</a>@endunless
        <div class="flex flex-wrap items-end justify-between gap-3"><div><h1 class="text-xl font-bold">{{ $event->name }}</h1><p class="mt-1 text-sm text-gray-500">{{ $match->bracket->group->name }} / {{ $match->label }} #{{ $match->position }}・複合弓累計制</p></div><span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700">{{ $statusNames[$match->status] ?? $match->status }}</span></div>
    </header>
    @if(session('success'))<div class="rounded-xl border border-green-200 bg-green-50 p-3 text-sm text-green-700">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif

    <section class="grid grid-cols-[minmax(0,1fr)_4rem_minmax(0,1fr)] overflow-hidden rounded-2xl border bg-white shadow-sm">
        <div class="p-4 text-center"><span class="text-xs font-bold text-gray-400">種子 {{ $match->participant_one_seed }}</span><h2 class="mt-1 truncate font-bold sm:text-lg">{{ $match->participantOneEntry->athlete_name }}</h2><p class="mt-3 text-4xl font-black text-indigo-700">{{ $match->participant_one_total }}</p></div>
        <div class="flex items-center justify-center border-x bg-gray-50 text-xs font-bold text-gray-400">累計</div>
        <div class="p-4 text-center"><span class="text-xs font-bold text-gray-400">種子 {{ $match->participant_two_seed }}</span><h2 class="mt-1 truncate font-bold sm:text-lg">{{ $match->participantTwoEntry->athlete_name }}</h2><p class="mt-3 text-4xl font-black text-indigo-700">{{ $match->participant_two_total }}</p></div>
    </section>

    @if($match->ends->isNotEmpty())
    <section class="overflow-hidden rounded-2xl border bg-white shadow-sm">
        <div class="grid grid-cols-[3rem_minmax(0,1fr)_3.5rem_minmax(0,1fr)_3.5rem] bg-gray-50 px-3 py-2 text-center text-xs font-semibold text-gray-500"><span>趟</span><span>{{ $match->participantOneEntry->athlete_name }}</span><span>小計</span><span>{{ $match->participantTwoEntry->athlete_name }}</span><span>小計</span></div>
        @foreach($match->ends as $end)<div class="grid min-h-12 grid-cols-[3rem_minmax(0,1fr)_3.5rem_minmax(0,1fr)_3.5rem] items-center border-t px-3 text-center text-sm"><strong>{{ $end->end_number }}</strong><span>{{ implode('・', $end->participant_one_arrows) }}</span><strong>{{ $end->participant_one_end_total }}</strong><span>{{ implode('・', $end->participant_two_arrows) }}</span><strong>{{ $end->participant_two_end_total }}</strong></div>@endforeach
    </section>
    @endif

    @if(in_array($match->status, ['awaiting_shoot_off', 'awaiting_judge'], true))
        @include('organizer.elimination._shoot-off', ['reason'=>'十五箭累計同分'])
    @elseif($match->status === 'completed')
        <section class="rounded-2xl border border-emerald-200 bg-emerald-50 p-6 text-center"><h2 class="font-bold text-emerald-950">比賽完成</h2><p class="mt-1 text-sm text-emerald-800">勝者已自動帶入下一輪；請保留紙本記分卡供核對。</p></section>
    @elseif($canEnter)
    @if($deviceMode)
    <form method="POST" action="{{ route('elimination-stations.ends.store', $stationToken) }}" id="end-form" class="grid gap-3">@csrf
        <section class="rounded-2xl border bg-white p-3 shadow-sm"><div class="mb-3 text-center text-sm font-bold text-indigo-700">第 {{ $match->ends->count() + 1 }} 趟 / 5 趟</div><div class="divide-y rounded-xl border">
            @foreach([['participant_one_arrows',$match->participantOneEntry->athlete_name],['participant_two_arrows',$match->participantTwoEntry->athlete_name]] as [$field,$name])
            <div class="athlete-row grid grid-cols-[minmax(6rem,1fr)_minmax(10rem,1.2fr)_3rem] items-center gap-2 p-3"><strong class="truncate text-sm">{{ $name }}</strong><div class="grid grid-cols-3 gap-2">@for($arrow=0;$arrow<3;$arrow++)<input readonly inputmode="none" name="{{ $field }}[]" placeholder="＿" class="score-input h-11 min-w-0 cursor-pointer select-none rounded-lg border-gray-300 p-1 text-center text-lg font-bold placeholder:text-gray-300 focus:ring-2 focus:ring-indigo-500">@endfor</div><strong class="row-total text-right text-xl">0</strong></div>
            @endforeach
        </div></section>
        <section class="rounded-2xl border bg-white p-3 shadow-sm"><div class="grid grid-cols-4 gap-2">
            @foreach([['X','X','bg-yellow-50 border-yellow-200'],['10','10','bg-yellow-50 border-yellow-200'],['9','9','bg-yellow-50 border-yellow-200'],['BKSP','⌫','bg-white border-gray-300'],['8','8','bg-red-50 border-red-200'],['7','7','bg-red-50 border-red-200'],['6','6','bg-blue-50 border-blue-200'],['5','5','bg-blue-50 border-blue-200'],['4','4','bg-gray-100 border-gray-300'],['3','3','bg-gray-100 border-gray-300'],['2','2','bg-white border-gray-300'],['1','1','bg-white border-gray-300']] as [$key,$label,$color])<button type="button" data-key="{{ $key }}" class="score-key min-h-14 touch-manipulation rounded-xl border text-lg font-bold {{ $color }}">{{ $label }}</button>@endforeach
            <button type="button" data-key="M" class="score-key col-span-2 min-h-14 rounded-xl border border-green-200 bg-green-50 text-lg font-bold text-green-800">M</button><button type="button" data-key="SUBMIT" class="score-key col-span-2 min-h-14 rounded-xl bg-indigo-600 font-semibold text-white">送出本趟</button>
        </div></section>
    </form>
    @endif
    @endif
</div>
@if($canEnter)
<script>(()=>{const form=document.getElementById('end-form');if(!form)return;const inputs=[...document.querySelectorAll('.score-input')];let active=0;const value=v=>v==='X'?10:(v==='M'||!v?0:Number(v));const select=i=>{active=Math.max(0,Math.min(inputs.length-1,i));inputs.forEach(x=>x.classList.remove('ring-2','ring-indigo-500','bg-indigo-50'));inputs[active].classList.add('ring-2','ring-indigo-500','bg-indigo-50')};const totals=()=>document.querySelectorAll('.athlete-row').forEach(row=>row.querySelector('.row-total').textContent=[...row.querySelectorAll('.score-input')].reduce((sum,x)=>sum+value(x.value),0));inputs.forEach((x,i)=>x.addEventListener('pointerdown',e=>{e.preventDefault();select(i)}));document.querySelectorAll('.score-key').forEach(key=>key.addEventListener('click',()=>{const action=key.dataset.key;if(action==='SUBMIT'){inputs.forEach(x=>{if(!x.value)x.value='M'});totals();if(confirm('確認雙方本趟 3 箭都已核對？送出後即列入正式累計分。'))form.requestSubmit();return}if(action==='BKSP'){const i=inputs[active].value?active:Math.max(0,active-1);inputs[i].value='';select(i);totals();return}inputs[active].value=action;totals();if(active<inputs.length-1)select(active+1)}));select(0);totals()})();</script>
@endif
@endsection
