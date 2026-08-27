@extends('layouts.app')
@section('title', $event->name.' 個人對抗賽')
@section('content')
<div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 sm:py-8">
    <header class="flex flex-wrap items-end justify-between gap-4"><div><a href="{{ route('events.show', $event) }}" class="inline-flex min-h-10 items-center text-sm font-medium text-indigo-600">← 返回賽事頁</a><h1 class="text-2xl font-bold">{{ $event->name }}</h1><p class="mt-1 text-sm text-gray-500">個人對抗賽即時戰況・頁面每 30 秒自動更新</p></div><span class="inline-flex items-center gap-2 rounded-full bg-red-50 px-3 py-1 text-xs font-semibold text-red-700"><span class="h-2 w-2 rounded-full bg-red-500"></span>LIVE</span></header>

    @foreach($brackets as $bracket)
    @php
        $bronze = $bracket->matches->firstWhere('match_type', 'bronze');
        $final = $bracket->matches->where('match_type', 'main')->sortByDesc('round_number')->first();
        $champion = $final?->winner_registration_id === $final?->participant_one_registration_id ? $final?->participantOneEntry : ($final?->winner_registration_id ? $final?->participantTwoEntry : null);
        $runnerUp = $final?->loser_registration_id === $final?->participant_one_registration_id ? $final?->participantOneEntry : ($final?->loser_registration_id ? $final?->participantTwoEntry : null);
        $bronzeWinner = $bronze?->winner_registration_id === $bronze?->participant_one_registration_id ? $bronze?->participantOneEntry : ($bronze?->winner_registration_id ? $bronze?->participantTwoEntry : null);
        $statusNames = ['pending'=>'等待前場', 'ready'=>'等待比賽', 'in_progress'=>'比賽中', 'awaiting_shoot_off'=>'等待加射', 'awaiting_judge'=>'等待裁判判定', 'walkover'=>'輪空晉級', 'completed'=>'已完成'];
    @endphp
    <section class="overflow-hidden rounded-2xl border bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b bg-gray-50 p-4 sm:p-5"><div><h2 class="text-lg font-bold">{{ $bracket->group->name }}</h2><p class="mt-1 text-xs text-gray-500">{{ $bracket->bracket_size }} 人制・{{ $bracket->scoring_mode === 'set' ? '反曲弓局分制' : '複合弓累計制' }}</p></div>@if($champion)<div class="flex gap-2 text-xs"><span class="rounded-full bg-yellow-100 px-3 py-1 font-semibold text-yellow-800">冠軍 {{ $champion->athlete_name }}</span>@if($runnerUp)<span class="rounded-full bg-gray-200 px-3 py-1 font-semibold text-gray-700">亞軍 {{ $runnerUp->athlete_name }}</span>@endif @if($bronzeWinner)<span class="rounded-full bg-amber-100 px-3 py-1 font-semibold text-amber-800">季軍 {{ $bronzeWinner->athlete_name }}</span>@endif</div>@endif</div>
        @include('events._elimination-bracket-tree', ['bracket'=>$bracket, 'statusNames'=>$statusNames])
    </section>
    @endforeach
</div>
<script>setTimeout(()=>location.reload(),30000)</script>
@endsection
