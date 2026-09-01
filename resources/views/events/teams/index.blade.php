@extends('layouts.app')
@section('title', $group->name.' 團體組隊')
@section('content')
<main class="mx-auto max-w-6xl space-y-6 px-4 py-6 sm:px-6 sm:py-8">
    <header>
        <a href="{{ route('events.show',$event) }}" class="inline-flex min-h-11 items-center text-sm font-medium text-indigo-600">← 返回賽事</a>
        <p class="text-xs font-semibold uppercase tracking-widest text-indigo-600">Team formation</p>
        <h1 class="mt-1 text-2xl font-bold">{{ $group->name }}・團體組隊</h1>
        <p class="mt-1 text-sm text-gray-500">{{ $group->team_type==='mixed' ? '混雙2人隊' : '一般3人隊' }}{{ $group->team_substitute_limit ? '＋1位候補' : '' }}・{{ $group->teamFormationIsOpen() ? '目前開放組隊' : '組隊已截止，名單已鎖定' }}</p>
    </header>
    @if(session('success'))<div class="rounded-xl bg-green-50 p-4 text-sm text-green-700">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="rounded-xl bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>@endif

    @if($myRegistration && !$myMembership && $group->teamFormationIsOpen())
        <section class="rounded-2xl border border-indigo-200 bg-indigo-50 p-4 sm:p-5">
            <h2 class="font-semibold text-indigo-950">還沒有隊伍？</h2>
            <p class="mt-1 text-sm text-indigo-700">建立新隊伍成為隊長，或從下方招募列表申請加入。</p>
            <form method="POST" action="{{ route('events.teams.store',[$event,$group]) }}" class="mt-4 grid gap-3 sm:grid-cols-[minmax(12rem,.8fr)_minmax(16rem,1.2fr)_auto]">@csrf
                <input name="name" required maxlength="100" value="{{ old('name') }}" class="min-h-12 min-w-0 rounded-xl border-indigo-200" placeholder="輸入隊伍名稱">
                <input name="recruitment_note" maxlength="300" value="{{ old('recruitment_note') }}" class="min-h-12 min-w-0 rounded-xl border-indigo-200" placeholder="招募說明（選填，例如：尋找同校隊友）">
                <button class="min-h-12 rounded-xl bg-indigo-600 px-5 text-sm font-semibold text-white">建立並公開招募</button>
            </form>
        </section>
    @elseif(!$myRegistration)
        <div class="rounded-xl bg-amber-50 p-4 text-sm text-amber-800">完成此組別的個人報名後，才能建立或加入隊伍。</div>
    @endif
    @if($canManage && $group->teamFormationIsOpen())<form method="POST" action="{{ route('events.teams.auto-match',[$event,$group]) }}" onsubmit="return confirm('系統只會使用尚未組隊的選手建立完整隊伍，確定自動配對？')" class="flex justify-end">@csrf<button class="min-h-11 rounded-xl border border-violet-200 bg-white px-4 text-sm font-medium text-violet-700">自動配對未組隊選手</button></form>@endif

    @if($myMembership)
        <section class="rounded-2xl border bg-white p-4 shadow-sm sm:p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div><p class="text-xs text-gray-500">我的組隊狀態</p><h2 class="mt-1 text-lg font-bold">{{ $myMembership->team->name }}</h2><p class="mt-1 text-sm text-gray-500">{{ ['active'=>'正式成員','pending'=>'等待隊長審核','invited'=>'等待你接受邀請'][$myMembership->status] ?? $myMembership->status }}</p></div>
                @if($group->teamFormationIsOpen())
                    <form method="POST" action="{{ route('events.teams.leave',[$event,$group,$myMembership]) }}" onsubmit="return confirm('{{ $myMembership->role==='captain' ? '隊長退出會解散整支隊伍，確定繼續？' : '確定退出或取消申請？' }}')">@csrf @method('DELETE')<button class="min-h-11 rounded-xl border border-red-200 px-4 text-sm text-red-600">{{ $myMembership->role==='captain' ? '解散隊伍' : '退出／取消' }}</button></form>
                @endif
            </div>
            @if($myMembership->status==='invited' && $group->teamFormationIsOpen())
                <div class="mt-4 flex gap-2"><form method="POST" action="{{ route('events.teams.respond',[$event,$group,$myMembership]) }}">@csrf @method('PATCH')<input type="hidden" name="decision" value="accept"><button class="min-h-11 rounded-xl bg-indigo-600 px-4 text-sm font-medium text-white">接受邀請</button></form><form method="POST" action="{{ route('events.teams.respond',[$event,$group,$myMembership]) }}">@csrf @method('PATCH')<input type="hidden" name="decision" value="reject"><button class="min-h-11 rounded-xl border px-4 text-sm">拒絕</button></form></div>
            @endif
        </section>
    @endif

    <section>
        <div class="mb-3 flex items-end justify-between"><div><h2 class="text-lg font-semibold">隊伍招募列表</h2><p class="mt-1 text-sm text-gray-500">查看隊員、尚缺人數與待處理申請。</p></div><span class="text-xs text-gray-400">{{ $teams->count() }} 隊</span></div>
        <div class="grid gap-4 md:grid-cols-2">
            @forelse($teams as $team)
                @php
                    $active = $team->memberships->where('status','active');
                    $competingCount = $active->whereIn('role',['captain','member'])->count();
                    $pendingCount = $team->memberships->where('status','pending')->count();
                    $activeGenders = $active->whereIn('role',['captain','member'])->map(fn($member) => $member->registration?->athlete_gender)->filter();
                    $mixedEligible = $group->team_type !== 'mixed' || ($myRegistration?->athlete_gender && ! $activeGenders->contains($myRegistration->athlete_gender));
                    $wantedGender = $group->team_type === 'mixed' ? ($activeGenders->contains('male') ? '女性' : ($activeGenders->contains('female') ? '男性' : '一男一女')) : null;
                @endphp
                <article class="rounded-2xl border bg-white p-4 shadow-sm sm:p-5">
                    <div class="flex items-start justify-between gap-3"><div><div class="flex flex-wrap items-center gap-2"><h3 class="font-bold">{{ $team->name }}</h3>@if($team->status==='recruiting' && $team->is_open)<span class="rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-semibold text-indigo-700">公開招募</span>@endif</div><p class="mt-1 text-xs text-gray-500">隊長：{{ $team->captainRegistration?->name }}</p></div><span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $competingCount >= $group->team_size ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">{{ $competingCount }} / {{ $group->team_size }}</span></div>
                    @if($team->recruitment_note)<p class="mt-3 rounded-xl bg-slate-50 px-3 py-2 text-sm text-slate-600">{{ $team->recruitment_note }}</p>@endif
                    @if($wantedGender && $team->status==='recruiting')<p class="mt-2 text-xs font-medium text-violet-700">目前正在招募：{{ $wantedGender }}選手</p>@endif
                    <div class="mt-3 flex flex-wrap gap-2">@foreach($active as $member)<span class="rounded-full bg-gray-100 px-3 py-1 text-xs">{{ $member->registration?->name }}{{ $member->role==='captain' ? '・隊長' : ($member->role==='substitute' ? '・候補' : '') }}</span>@endforeach</div>

                    @if($myRegistration && !$myMembership && $team->status==='recruiting' && $team->is_open && $group->teamFormationIsOpen() && $mixedEligible)
                        <form method="POST" action="{{ route('events.teams.apply',[$event,$group,$team]) }}" class="mt-4">@csrf<button class="min-h-11 w-full rounded-xl border border-indigo-200 text-sm font-medium text-indigo-700">申請加入</button></form>
                    @elseif($myRegistration && !$myMembership && $team->status==='recruiting' && $team->is_open && !$mixedEligible)
                        <div class="mt-4 rounded-xl bg-gray-50 px-3 py-3 text-center text-xs font-medium text-gray-500">此隊目前正在招募{{ $wantedGender }}選手</div>
                    @endif
                    @if($myTeam?->is($team) && $team->captain_registration_id===$myRegistration?->id && $group->teamFormationIsOpen())
                        @php($pending=$team->memberships->where('status','pending'))
                        @if($pending->isNotEmpty())<div class="mt-4 space-y-2 border-t pt-3"><p class="text-xs font-semibold text-gray-500">待審核申請（{{ $pendingCount }}）</p>@foreach($pending as $member)<div class="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-indigo-50 p-3"><span class="text-sm"><strong>{{ $member->registration?->name }}</strong><small class="ml-1 text-gray-500">{{ $member->registration?->athlete_gender === 'male' ? '男子' : ($member->registration?->athlete_gender === 'female' ? '女子' : '') }}・{{ $member->created_at?->format('m/d H:i') }}</small></span><div class="flex gap-1"><form method="POST" action="{{ route('events.teams.review',[$event,$group,$team,$member]) }}">@csrf @method('PATCH')<input type="hidden" name="decision" value="approve"><button class="min-h-10 rounded-lg bg-indigo-600 px-3 text-xs font-semibold text-white">同意加入</button></form><form method="POST" action="{{ route('events.teams.review',[$event,$group,$team,$member]) }}">@csrf @method('PATCH')<input type="hidden" name="decision" value="reject"><button class="min-h-10 rounded-lg border bg-white px-3 text-xs">拒絕</button></form></div></div>@endforeach</div>@endif
                        @if($eligibleInvitees->isNotEmpty() && ($team->competingMemberships()->count() < $group->team_size || $group->team_substitute_limit > $active->where('role','substitute')->count()))<form method="POST" action="{{ route('events.teams.invite',[$event,$group,$team]) }}" class="mt-4 grid gap-2 border-t pt-3 sm:grid-cols-[1fr_8rem_auto]">@csrf<select name="registration_id" required class="min-h-11 min-w-0 rounded-xl border-gray-300 text-sm"><option value="">邀請已報名選手</option>@foreach($eligibleInvitees as $registration)<option value="{{ $registration->id }}">{{ $registration->name }}{{ $registration->athlete_gender ? '・'.($registration->athlete_gender==='male'?'男':'女') : '' }}</option>@endforeach</select><select name="member_role" class="min-h-11 rounded-xl border-gray-300 text-sm"><option value="member">正式隊員</option>@if($group->team_substitute_limit)<option value="substitute">候補</option>@endif</select><button class="min-h-11 rounded-xl bg-gray-900 px-4 text-sm text-white">邀請</button></form>@endif
                    @endif
                </article>
            @empty<div class="rounded-2xl border border-dashed bg-white p-8 text-center text-sm text-gray-500 md:col-span-2">目前還沒有隊伍，成為第一位隊長吧。</div>@endforelse
        </div>
    </section>

    @if($rankings->isNotEmpty())
        <section class="overflow-hidden rounded-2xl border bg-white shadow-sm"><div class="border-b p-4 sm:p-5"><h2 class="text-lg font-semibold">團體正式排名</h2><p class="mt-1 text-sm text-gray-500">僅計算全隊成員皆已正式發布的個人成績。</p></div><div class="overflow-x-auto"><table class="min-w-full text-sm"><thead class="bg-gray-50 text-left text-xs text-gray-500"><tr><th class="p-3">排名</th><th class="p-3">隊伍</th><th class="p-3 text-right">總分</th><th class="p-3 text-right">10</th><th class="p-3 text-right">X</th></tr></thead><tbody class="divide-y">@foreach($rankings as $index=>$row)<tr><td class="p-3 font-bold">{{ $index+1 }}</td><td class="p-3"><strong>{{ $row['team']->name }}</strong><p class="mt-1 text-xs text-gray-500">{{ $row['team']->memberships->where('status','active')->map(fn($m)=>$m->registration?->name)->filter()->join('、') }}</p></td><td class="p-3 text-right text-lg font-bold">{{ $row['total'] }}</td><td class="p-3 text-right">{{ $row['ten_count'] }}</td><td class="p-3 text-right">{{ $row['x_count'] }}</td></tr>@endforeach</tbody></table></div></section>
    @endif
</main>
@endsection
