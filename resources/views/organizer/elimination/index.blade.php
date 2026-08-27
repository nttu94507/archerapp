@extends('layouts.app')
@section('title', $event->name.' 個人對抗表')
@section('content')
<div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 sm:py-8">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div><a href="{{ route('organizer.events.show', $event) }}" class="inline-flex min-h-11 items-center text-sm font-medium text-indigo-600">← 返回賽事管理</a><h1 class="text-2xl font-bold">個人對抗表</h1><p class="mt-1 text-sm text-gray-500">{{ $event->name }}・由已鎖定的排名種子建立</p></div>
    </header>

    @if(session('success'))<div class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-700">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>@endif

    @php
        $brackets = $event->groups->flatMap->eliminationBrackets;
        $statusNames = ['pending'=>'等待前場', 'ready'=>'等待比賽', 'in_progress'=>'比賽中', 'awaiting_shoot_off'=>'等待加射', 'awaiting_judge'=>'等待主裁判', 'walkover'=>'輪空晉級', 'completed'=>'已完成'];
    @endphp

    @unless($event->hasPlanFeature('individual_elimination'))
        <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5"><h2 class="font-semibold text-amber-950">免費方案僅提供排名賽</h2><p class="mt-1 text-sm text-amber-800">個人對抗表、對抗計分與公開對戰戰況屬於付費賽事功能。現有排名成績不受影響。</p></section>
    @endunless

    @can('manageScoreCorrections', $event)
    @if($event->hasPlanFeature('individual_elimination'))
    <section class="rounded-2xl border bg-white p-4 shadow-sm sm:p-5">
        <div><h2 class="font-semibold">建立對抗表</h2><p class="mt-1 text-sm text-gray-500">建立後會鎖定本次籤表；如有同分待判定，系統會先阻止生成。</p></div>
        <form method="POST" action="{{ route('organizer.events.elimination.store', $event) }}" class="mt-4 grid gap-4 md:grid-cols-[minmax(0,1fr)_12rem_auto] md:items-end">@csrf
            <label class="text-sm font-medium">組別
                <select name="event_group_id" required class="mt-1 min-h-12 w-full rounded-xl border-gray-300 text-base">
                    <option value="">請選擇</option>
                    @foreach($event->groups as $group)
                        @php
                            $snapshot = $snapshots->get($group->id);
                        @endphp
                        <option value="{{ $group->id }}" @selected(old('event_group_id') == $group->id) @disabled($group->eliminationBrackets->isNotEmpty())>{{ $group->name }}（{{ $group->eliminationBrackets->isNotEmpty() ? '已建立' : ($snapshot ? $snapshot->entries->where('is_eligible', true)->count().' 名有效種子' : '尚無排名快照') }}）</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm font-medium">籤表規模
                <select name="bracket_size" class="mt-1 min-h-12 w-full rounded-xl border-gray-300 text-base">@foreach($sizes as $size)<option value="{{ $size }}" @selected(old('bracket_size', 8) == $size)>{{ $size }} 人制</option>@endforeach</select>
            </label>
            <div class="grid gap-2"><label class="inline-flex min-h-8 items-center gap-2 text-sm"><input type="hidden" name="bronze_match_enabled" value="0"><input type="checkbox" name="bronze_match_enabled" value="1" checked class="rounded border-gray-300 text-indigo-600">建立季軍賽</label><button class="min-h-12 rounded-xl bg-indigo-600 px-5 font-medium text-white">建立並鎖定</button></div>
        </form>
    </section>
    @endif
    @endcan

    @forelse($brackets as $bracket)
        @php
            $mainRounds = $bracket->matches->where('match_type', 'main')->groupBy('round_number');
            $bronze = $bracket->matches->firstWhere('match_type', 'bronze');
        @endphp
        <section class="overflow-hidden rounded-2xl border bg-white shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b bg-gray-50 p-4 sm:p-5"><div><h2 class="font-semibold">{{ $bracket->name }}</h2><p class="mt-1 text-xs text-gray-500">{{ $bracket->bracket_size }} 人制・{{ $bracket->scoring_mode === 'set' ? '局分制' : '累計制' }}・種子快照 v{{ $bracket->rankingSnapshot->version }}</p></div><div class="flex items-center gap-2"><span class="rounded-full px-3 py-1 text-xs font-medium {{ $bracket->visibility === 'public' ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-200 text-gray-600' }}">{{ $bracket->visibility === 'public' ? '公開戰況' : '僅工作人員' }}</span>@can('manageScoreCorrections',$event)<form method="POST" action="{{ route('organizer.events.elimination.visibility', [$event, $bracket]) }}">@csrf @method('PATCH')<input type="hidden" name="visibility" value="{{ $bracket->visibility === 'public' ? 'internal' : 'public' }}"><button class="min-h-9 rounded-lg border bg-white px-3 text-xs font-medium" onclick="return confirm('{{ $bracket->visibility === 'public' ? '確定關閉公開戰況？' : '公開後任何人都能查看籤表與即時分數，確定公開？' }}')">{{ $bracket->visibility === 'public' ? '停止公開' : '公開戰況' }}</button></form>@endcan</div></div>
            <div class="overflow-x-auto p-4 sm:p-5">
                <div class="grid min-w-max auto-cols-[17rem] grid-flow-col gap-5">
                    @foreach($mainRounds as $round => $matches)
                    <div><h3 class="mb-3 text-center text-sm font-semibold text-gray-600">{{ $matches->first()->label }}</h3><div class="flex h-full flex-col justify-around gap-4">
                        @foreach($matches as $match)
                        @include('events._elimination-match-card', ['match'=>$match, 'bracket'=>$bracket, 'statusNames'=>$statusNames, 'management'=>true])
                        @endforeach
                    </div>
                    @endforeach
                    @if($bronze)<div><h3 class="mb-3 text-center text-sm font-semibold text-gray-600">季軍賽</h3><div class="flex h-full items-center">@include('events._elimination-match-card', ['match'=>$bronze, 'bracket'=>$bracket, 'statusNames'=>$statusNames, 'management'=>true])</div></div>@endif
                </div>
            </div>
        </section>
    @empty
        <section class="rounded-2xl border border-dashed bg-white p-8 text-center"><h2 class="font-semibold">尚未建立個人對抗表</h2><p class="mt-1 text-sm text-gray-500">先完成排名賽成績發布，再依鎖定的種子快照建立籤表。</p></section>
    @endforelse

    @can('manageScores', $event)
    @if($brackets->isNotEmpty())
    <section class="space-y-4">
        <div><h2 class="text-xl font-bold">對戰列表</h2><p class="mt-1 text-sm text-gray-500">每場對戰使用獨立 QR Code 與 PIN 綁定唯一計分設備。</p></div>
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse($brackets->flatMap->matches->filter(fn ($item) => $item->participant_one_registration_id && $item->participant_two_registration_id) as $match)
            <article class="overflow-hidden rounded-2xl border bg-white shadow-sm">
                <div class="flex items-center justify-between bg-gray-50 px-4 py-3 text-xs"><span class="font-medium text-gray-600">{{ $match->bracket->group->name }}・{{ $match->match_type === 'bronze' ? '季軍賽' : $match->label }} #{{ $match->position }}</span><span class="rounded-full px-2 py-1 {{ $match->device_token_hash ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-200 text-gray-600' }}">{{ $match->device_token_hash ? '設備已綁定' : '等待綁定' }}</span></div>
                <div class="grid grid-cols-[minmax(0,1fr)_6.5rem] gap-3 p-4">
                    <div class="space-y-2"><div class="flex min-h-11 items-center gap-2 rounded-xl border px-3"><span class="text-xs font-bold text-gray-400">{{ $match->participant_one_seed }}</span><strong class="truncate">{{ $match->participantOneEntry->athlete_name }}</strong></div><div class="flex min-h-11 items-center gap-2 rounded-xl border px-3"><span class="text-xs font-bold text-gray-400">{{ $match->participant_two_seed }}</span><strong class="truncate">{{ $match->participantTwoEntry->athlete_name }}</strong></div><div><p class="text-xs text-gray-500">設備 PIN</p><p class="font-mono text-xl font-bold tracking-[.2em]">{{ $match->device_pin }}</p></div></div>
                    <img src="{{ route('organizer.events.elimination.matches.qrcode', [$event, $match]) }}" class="h-24 w-24 self-start rounded-lg border bg-white p-1" alt="{{ $match->participantOneEntry->athlete_name }} 對 {{ $match->participantTwoEntry->athlete_name }} 計分 QR Code">
                </div>
                <div class="border-t p-3"><a href="{{ route('elimination-stations.show', $match->access_token) }}" class="inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-indigo-600 px-3 text-sm font-semibold text-white">開啟計分網址</a>@if($match->device_token_hash)<form method="POST" action="{{ route('organizer.events.elimination.matches.device.destroy', [$event, $match]) }}" class="mt-2">@csrf @method('DELETE')<button class="min-h-11 w-full rounded-xl border border-red-200 text-sm text-red-600" onclick="return confirm('解除後舊設備與網址會立即失效，確定？')">解除設備</button></form>@endif</div>
            </article>
            @empty<p class="col-span-full rounded-2xl border border-dashed bg-white p-6 text-center text-sm text-gray-500">目前尚無雙方選手都已確定的對戰。</p>@endforelse
        </div>
    </section>
    @endif
    @endcan
</div>
@endsection
