@extends('layouts.app')
@section('title', $event->name.' 對抗賽')
@section('content')
<div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 sm:py-8">
    <header class="flex flex-wrap items-end justify-between gap-4"><div><a href="{{ route('events.show', $event) }}" class="inline-flex min-h-10 items-center text-sm font-medium text-indigo-600">← 返回賽事頁</a><h1 class="text-2xl font-bold">{{ $event->name }}</h1><p class="mt-1 text-sm text-gray-500">個人對抗賽即時戰況・亦包含團體對抗・頁面每 30 秒自動更新</p></div><span class="inline-flex items-center gap-2 rounded-full bg-red-50 px-3 py-1 text-xs font-semibold text-red-700"><span class="h-2 w-2 rounded-full bg-red-500"></span>LIVE</span></header>

    <nav class="space-y-3 rounded-2xl border bg-white p-3 shadow-sm sm:p-4" aria-label="公開戰況篩選">
        <div class="flex gap-2 overflow-x-auto pb-1">
            <a href="{{ route('events.elimination', ['event'=>$event, 'status'=>$selectedStatus]) }}" class="inline-flex min-h-11 shrink-0 items-center rounded-xl px-4 text-sm font-semibold {{ $selectedGroup === '' ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-700' }}">全部組別 <span class="ml-2 opacity-70">{{ $allBrackets->count() }}</span></a>
            @foreach($allBrackets as $tabBracket)
                @php
                    $tabStats = $bracketStats[$tabBracket->id];
                @endphp
                <a href="{{ route('events.elimination', ['event'=>$event, 'group'=>$tabBracket->uuid, 'status'=>$selectedStatus]) }}" class="inline-flex min-h-11 shrink-0 items-center gap-2 rounded-xl px-4 text-sm font-semibold {{ $selectedGroup === $tabBracket->uuid ? 'bg-indigo-600 text-white' : 'bg-indigo-50 text-indigo-800' }}">
                    {{ $tabBracket->group->name }}
                    <span class="rounded-full px-2 py-0.5 text-[10px] {{ $selectedGroup === $tabBracket->uuid ? 'bg-white/20' : ($tabStats['completed'] ? 'bg-emerald-100 text-emerald-700' : 'bg-white text-indigo-600') }}">{{ $tabStats['completed'] ? '已完成' : '進行中 '.$tabStats['active'] }}{{ $tabStats['waitingJudge'] ? '・待判 '.$tabStats['waitingJudge'] : '' }}</span>
                </a>
            @endforeach
        </div>
        <div class="flex gap-2">
            @foreach(['all'=>'全部狀態', 'live'=>'進行中', 'completed'=>'已完成'] as $statusValue => $statusLabel)
                <a href="{{ route('events.elimination', array_filter(['event'=>$event, 'group'=>$selectedGroup, 'status'=>$statusValue])) }}" class="inline-flex min-h-10 items-center rounded-lg px-3 text-sm font-medium {{ $selectedStatus === $statusValue ? 'bg-gray-800 text-white' : 'border bg-white text-gray-600' }}">{{ $statusLabel }}</a>
            @endforeach
        </div>
    </nav>

    @forelse($brackets as $bracket)
    @php
        $teamBracket = in_array($bracket->category, ['team', 'mixed_team'], true);
        $bronze = $bracket->matches->firstWhere('match_type', 'bronze');
        $final = $bracket->matches->where('match_type', 'main')->sortByDesc('round_number')->first();
        $winnerId = $teamBracket ? $final?->winner_team_id : $final?->winner_registration_id;
        $loserId = $teamBracket ? $final?->loser_team_id : $final?->loser_registration_id;
        $oneId = $teamBracket ? $final?->participant_one_team_id : $final?->participant_one_registration_id;
        $bronzeWinnerId = $teamBracket ? $bronze?->winner_team_id : $bronze?->winner_registration_id;
        $bronzeOneId = $teamBracket ? $bronze?->participant_one_team_id : $bronze?->participant_one_registration_id;
        $champion = $winnerId === $oneId ? ($teamBracket ? $final?->participantOneTeam : $final?->participantOneEntry) : ($winnerId ? ($teamBracket ? $final?->participantTwoTeam : $final?->participantTwoEntry) : null);
        $runnerUp = $loserId === $oneId ? ($teamBracket ? $final?->participantOneTeam : $final?->participantOneEntry) : ($loserId ? ($teamBracket ? $final?->participantTwoTeam : $final?->participantTwoEntry) : null);
        $bronzeWinner = $bronzeWinnerId === $bronzeOneId ? ($teamBracket ? $bronze?->participantOneTeam : $bronze?->participantOneEntry) : ($bronzeWinnerId ? ($teamBracket ? $bronze?->participantTwoTeam : $bronze?->participantTwoEntry) : null);
        $statusNames = ['pending'=>'等待前場', 'ready'=>'等待比賽', 'in_progress'=>'比賽中', 'awaiting_shoot_off'=>'等待加射', 'awaiting_judge'=>'等待裁判判定', 'walkover'=>'輪空晉級', 'completed'=>'已完成'];
    @endphp
    <section class="overflow-hidden rounded-2xl border bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b bg-gray-50 p-4 sm:p-5"><div><h2 class="text-lg font-bold">{{ $bracket->group->name }}・{{ $teamBracket ? ($bracket->category === 'mixed_team' ? '混雙團體' : '團體') : '個人' }}</h2><p class="mt-1 text-xs text-gray-500">{{ $bracket->bracket_size }} {{ $teamBracket ? '隊制' : '人制' }}・{{ $bracket->scoring_mode === 'set' ? '反曲弓局分制' : '複合弓累計制' }}</p></div>@if($champion)<div class="flex gap-2 text-xs"><span class="rounded-full bg-yellow-100 px-3 py-1 font-semibold text-yellow-800">冠軍 {{ $teamBracket ? $champion->name : $champion->athlete_name }}</span>@if($runnerUp)<span class="rounded-full bg-gray-200 px-3 py-1 font-semibold text-gray-700">亞軍 {{ $teamBracket ? $runnerUp->name : $runnerUp->athlete_name }}</span>@endif @if($bronzeWinner)<span class="rounded-full bg-amber-100 px-3 py-1 font-semibold text-amber-800">季軍 {{ $teamBracket ? $bronzeWinner->name : $bronzeWinner->athlete_name }}</span>@endif</div>@endif</div>
        @include('events._elimination-bracket-tree', ['bracket'=>$bracket, 'statusNames'=>$statusNames])
    </section>
    @empty
        <section class="rounded-2xl border border-dashed bg-white p-10 text-center"><h2 class="font-semibold">目前沒有符合條件的組別</h2><p class="mt-1 text-sm text-gray-500">可以切換「全部狀態」或選擇其他組別查看。</p></section>
    @endforelse
</div>
<script>setTimeout(()=>location.reload(),30000)</script>
@endsection
