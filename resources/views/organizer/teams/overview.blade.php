@extends('layouts.app')
@section('title', $event->name.' 團體與混雙名單')
@section('content')
<main class="mx-auto max-w-6xl space-y-6 px-4 py-6 sm:px-6 sm:py-8">
    <header><a href="{{ route('organizer.events.show',$event) }}#execute" class="inline-flex min-h-11 items-center text-sm font-medium text-indigo-600">← 返回賽事工作台</a><h1 class="text-2xl font-bold">團體與混雙名單</h1><p class="mt-1 text-sm text-gray-500">{{ $event->name }}</p></header>

    @forelse($event->groups as $group)
        @php
            $standard = $group->eventTeams->where('team_format', 'standard');
            $mixed = $group->eventTeams->where('team_format', 'mixed');
            $isComplete = fn ($team) => $team->memberships->where('status', 'active')->count() === $team->requiredSize();
            $standardComplete = $standard->filter($isComplete)->count();
            $mixedComplete = $mixed->filter($isComplete)->count();
        @endphp
        <section class="overflow-hidden rounded-2xl border bg-white shadow-sm">
            <header class="flex flex-wrap items-center justify-between gap-3 border-b bg-gray-50 p-4 sm:p-5"><div><h2 class="font-bold">{{ $group->name }}</h2><div class="mt-2 flex flex-wrap gap-2 text-xs">@if($group->hasTeamFormat('standard'))<span class="rounded-full bg-violet-100 px-3 py-1 font-semibold text-violet-700">團體 {{ $standard->count() }} 隊・完整 {{ $standardComplete }}</span>@endif @if($group->hasTeamFormat('mixed'))<span class="rounded-full bg-amber-100 px-3 py-1 font-semibold text-amber-700">混雙 {{ $mixed->count() }} 隊・完整 {{ $mixedComplete }}</span>@endif</div></div><a href="{{ route('events.teams.index',[$event,$group]) }}" class="inline-flex min-h-11 items-center rounded-xl bg-indigo-600 px-4 text-sm font-semibold text-white">查看組隊頁</a></header>
            <div class="grid gap-4 p-4 md:grid-cols-2 sm:p-5">
                @forelse($group->eventTeams as $team)
                    @php
                        $active = $team->memberships->where('status','active');
                    @endphp
                    <article class="rounded-xl border p-4"><div class="flex items-start justify-between gap-3"><div><div class="flex flex-wrap items-center gap-2"><h3 class="font-semibold">{{ $team->name }}</h3><span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-semibold">{{ $team->team_format === 'mixed' ? '混雙' : '團體' }}</span></div><p class="mt-1 text-xs text-gray-500">隊長：{{ $team->captainRegistration?->name }}</p></div><span class="rounded-full px-2 py-1 text-xs font-semibold {{ $active->count() === $team->requiredSize() ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">{{ $active->count() }} / {{ $team->requiredSize() }}</span></div><div class="mt-3 flex flex-wrap gap-2">@foreach($active as $member)<span class="rounded-full bg-gray-100 px-3 py-1 text-xs">{{ $member->registration?->name }}{{ $member->role === 'captain' ? '・隊長' : '' }}</span>@endforeach</div>@if($team->memberships->where('status','pending')->isNotEmpty())<p class="mt-3 text-xs font-semibold text-amber-700">待隊長審核 {{ $team->memberships->where('status','pending')->count() }} 人</p>@endif</article>
                @empty
                    <p class="text-sm text-gray-500 md:col-span-2">此組別尚未建立隊伍。</p>
                @endforelse
            </div>
        </section>
    @empty
        <div class="rounded-2xl border border-dashed bg-white p-8 text-center text-sm text-gray-500">目前沒有開放團體或混雙的組別。</div>
    @endforelse
</main>
@endsection
