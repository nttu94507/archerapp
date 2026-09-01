@extends('layouts.app')

@section('title', '快速建立賽事')

@section('content')
<style>
    #quick-event-form input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]),
    #quick-event-form select {
        border: 1px solid #cbd5e1;
        background-color: #fff;
        padding-left: .875rem;
        padding-right: .875rem;
        color: #0f172a;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .05);
        transition: border-color .15s ease, box-shadow .15s ease, background-color .15s ease;
    }
    #quick-event-form input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]):hover,
    #quick-event-form select:hover {
        border-color: #94a3b8;
    }
    #quick-event-form input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]):focus,
    #quick-event-form select:focus {
        border-color: #6366f1;
        outline: none;
        box-shadow: 0 0 0 3px rgba(99, 102, 241, .16);
    }
    #quick-event-form input::placeholder {
        color: #94a3b8;
    }
    #quick-event-form #group-preset {
        border-color: #a5b4fc;
        background-color: #eef2ff;
    }
</style>
<div class="mx-auto max-w-4xl px-4 py-6 sm:px-6 sm:py-8">
    <div class="mb-6">
        <a href="{{ route('organizer.events.index') }}" class="inline-flex min-h-11 items-center text-sm font-medium text-indigo-600">← 我的賽事</a>
        <p class="text-xs font-semibold uppercase tracking-widest text-indigo-600">Organizer</p>
        <h1 class="mt-1 text-2xl font-bold">快速建立賽事</h1>
        <p class="mt-1 text-sm text-gray-500">填寫基本資料與第一個組別，就能直接發布並開始收件。</p>
    </div>

    @if($errors->any())
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
            <p class="font-semibold">還有資料需要確認</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('organizer.events.store') }}" class="space-y-5" id="quick-event-form">
        @csrf

        <section class="rounded-2xl border bg-white p-4 shadow-sm sm:p-6">
            <div class="mb-5"><p class="text-xs font-semibold text-indigo-600">步驟 1</p><h2 class="text-lg font-semibold">賽事基本資料</h2></div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="text-sm font-medium">賽事名稱 *</label>
                    <input name="name" required value="{{ old('name') }}" placeholder="例如：2026 台北夏季射箭賽" class="mt-1 min-h-12 w-full rounded-xl border-gray-300">
                </div>
                <div><label class="text-sm font-medium">開始日期 *</label><input id="event-start" type="date" name="start_date" required value="{{ old('start_date') }}" class="mt-1 min-h-12 w-full rounded-xl border-gray-300"></div>
                <div><label class="text-sm font-medium">結束日期 *</label><input id="event-end" type="date" name="end_date" required value="{{ old('end_date') }}" class="mt-1 min-h-12 w-full rounded-xl border-gray-300"><p class="mt-1 text-xs text-gray-500">預設為單日賽，可自行修改。</p></div>
                <div><label class="text-sm font-medium">賽事類型 *</label><select id="event-mode" name="mode" required class="mt-1 min-h-12 w-full rounded-xl border-gray-300"><option value="outdoor" @selected(old('mode','outdoor')==='outdoor')>室外</option><option value="indoor" @selected(old('mode')==='indoor')>室內</option></select></div>
                <div><label class="text-sm font-medium">場地</label><input name="venue" value="{{ old('venue') }}" placeholder="例如：台北市立射箭場" class="mt-1 min-h-12 w-full rounded-xl border-gray-300"></div>
                <div><label class="text-sm font-medium">報名開始 *</label><input id="reg-start" type="datetime-local" name="reg_start" required value="{{ old('reg_start', now()->format('Y-m-d\TH:i')) }}" class="mt-1 min-h-12 w-full rounded-xl border-gray-300"></div>
                <div><label class="text-sm font-medium">報名截止 *</label><input id="reg-end" type="datetime-local" name="reg_end" required value="{{ old('reg_end') }}" class="mt-1 min-h-12 w-full rounded-xl border-gray-300"></div>
                <div class="sm:col-span-2"><label class="text-sm font-medium">主辦單位 *</label><input name="organizer" required value="{{ old('organizer', $organizerName) }}" class="mt-1 min-h-12 w-full rounded-xl border-gray-300"></div>
                <div class="sm:col-span-2">
                    <p class="text-sm font-medium">賽事可見度</p>
                    <input type="hidden" name="visibility" value="public">
                    @if($canUseUnlisted)
                        <label for="event-unlisted" class="mt-2 flex min-h-14 cursor-pointer items-center gap-3 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 transition hover:border-indigo-300 hover:bg-indigo-50">
                            <input id="event-unlisted" type="checkbox" name="visibility" value="unlisted" @checked(old('visibility') === 'unlisted') class="h-6 w-6 shrink-0 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span><span class="block text-sm font-semibold text-gray-800">不顯示於公開賽事列表</span><span class="mt-0.5 block text-xs text-gray-500">僅持 UUID 網址或 QR Code 的人可以進入、報名及查看戰況。</span></span>
                        </label>
                    @else
                        <div class="mt-2 rounded-xl bg-gray-50 px-4 py-3 text-sm text-gray-600">免費賽事會顯示於公開列表；升級後可設定為不公開。</div>
                    @endif
                </div>
                <div class="sm:col-span-2">
                    <p class="text-sm font-medium">現場報到</p>
                    <input type="hidden" name="check_in_enabled" value="0">
                    @if($maxArrows > 36)
                        <label for="check-in-enabled" class="mt-2 flex min-h-14 cursor-pointer items-center gap-3 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                            <input id="check-in-enabled" type="checkbox" name="check_in_enabled" value="1" @checked(old('check_in_enabled', '1') === '1') class="h-6 w-6 shrink-0 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span><span class="block text-sm font-semibold text-gray-800">使用選手報到流程</span><span class="mt-0.5 block text-xs text-gray-500">若不勾選，排靶時直接確認出賽名單並取消未到場選手。</span></span>
                        </label>
                    @else
                        <div class="mt-2 rounded-xl bg-gray-50 px-4 py-3 text-sm text-gray-600">免費賽事略過報到，排靶時直接確認出賽名單。</div>
                    @endif
                </div>
            </div>
        </section>

        <section class="rounded-2xl border bg-white p-4 shadow-sm sm:p-6">
            <div class="mb-5"><p class="text-xs font-semibold text-indigo-600">步驟 2</p><h2 class="text-lg font-semibold">第一個報名組別</h2><p class="mt-1 text-xs text-gray-500">先建立主要組別，發布後仍可新增更多組別。</p></div>
            @if($maxArrows === 36)<div class="mb-4 flex items-center justify-between gap-3 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800"><span>免費方案僅支援單局最多 36 箭。</span><a href="{{ route('store.index') }}" class="shrink-0 font-semibold underline">查看方案</a></div>@endif
            <div class="mb-5 rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div><h3 class="font-semibold text-slate-950">賽事內容</h3><p class="mt-1 text-xs text-slate-600">選擇這個組別除了個人排名賽，還要開放哪一種團體賽。</p></div>
                    @if($maxArrows === 36)<a href="{{ route('store.index') }}" class="text-xs font-semibold text-indigo-600">團體賽需升級 →</a>@endif
                </div>
                <div class="mt-4 grid gap-3 md:grid-cols-3">
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4"><span class="inline-flex rounded-full bg-emerald-600 px-2 py-0.5 text-[10px] font-bold text-white">固定包含</span><p class="mt-2 font-semibold text-emerald-950">個人排名賽</p><p class="mt-1 text-xs text-emerald-700">所有選手先完成個人報名與排名計分。</p></div>
                    <label class="team-format-card cursor-pointer rounded-xl border bg-white p-4 transition has-[:checked]:border-violet-500 has-[:checked]:bg-violet-50 {{ $maxArrows === 36 ? 'opacity-60' : '' }}"><span class="flex items-start gap-3"><input type="radio" name="team_format_selector" value="standard" @checked(old('groups.0.is_team') && old('groups.0.team_type','standard') === 'standard') @disabled($maxArrows === 36) class="mt-0.5 h-5 w-5 text-violet-600"><span><strong class="block text-sm text-slate-950">3 人團體賽</strong><span class="mt-1 block text-xs text-slate-600">依組別性別招募 3 名正式選手，每局／趟共 6 箭。</span></span></span></label>
                    <label class="team-format-card cursor-pointer rounded-xl border bg-white p-4 transition has-[:checked]:border-violet-500 has-[:checked]:bg-violet-50 {{ $maxArrows === 36 ? 'opacity-60' : '' }}"><span class="flex items-start gap-3"><input type="radio" name="team_format_selector" value="mixed" @checked(old('groups.0.is_team') && old('groups.0.team_type') === 'mixed') @disabled($maxArrows === 36) class="mt-0.5 h-5 w-5 text-violet-600"><span><strong class="block text-sm text-slate-950">男女混雙</strong><span class="mt-1 block text-xs text-slate-600">固定一男一女，每局／趟共 4 箭。</span></span></span></label>
                </div>
                @if($maxArrows > 36)<button id="clear-team-format" type="button" class="mt-3 min-h-9 text-xs font-semibold text-slate-500 underline">此組別不開放團體賽</button>@endif
                <div class="mt-4 rounded-xl border border-indigo-100 bg-white px-4 py-3" aria-live="polite"><p class="text-xs font-semibold text-indigo-600">建立架構預覽</p><div id="competition-summary" class="mt-2 flex flex-wrap gap-2"></div><p id="competition-note" class="mt-2 text-xs text-slate-500"></p></div>
            </div>
            <div class="mb-4">
                <label class="text-sm font-medium">快速套用賽制</label>
                <select id="group-preset" class="mt-1 min-h-12 w-full rounded-xl border-indigo-200 bg-indigo-50">
                    <option value="outdoor70">室外 70m／{{ $maxArrows > 36 ? 72 : 36 }} 箭</option>
                    @if($maxArrows > 36)<option value="outdoor50">室外 50m／72 箭</option>@endif
                    <option value="outdoor30">室外 30m／36 箭</option>
                    <option value="indoor18">室內 18m／{{ $maxArrows > 36 ? 60 : 30 }} 箭</option>
                    <option value="custom">自訂</option>
                </select>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div class="sm:col-span-2 lg:col-span-1"><label class="text-sm font-medium">組別名稱 *</label><input id="group-name" name="groups[0][name]" required value="{{ old('groups.0.name','反曲弓公開組') }}" class="mt-1 min-h-12 w-full rounded-xl border-gray-300"></div>
                <div><label class="text-sm font-medium">弓種</label><select name="groups[0][bow_type]" class="mt-1 min-h-12 w-full rounded-xl border-gray-300"><option value="recurve">反曲弓</option><option value="compound">複合弓</option><option value="barebow">光弓</option><option value="">不限</option></select></div>
                <div><label class="text-sm font-medium">性別</label><select name="groups[0][gender]" class="mt-1 min-h-12 w-full rounded-xl border-gray-300"><option value="open">公開／不限</option><option value="male">男子</option><option value="female">女子</option></select></div>
                <div><label class="text-sm font-medium">距離</label><input id="group-distance" name="groups[0][distance]" value="{{ old('groups.0.distance','70m') }}" class="mt-1 min-h-12 w-full rounded-xl border-gray-300"></div>
                <div><label class="text-sm font-medium">總箭數 *</label><input id="group-arrows" type="number" min="6" max="{{ $maxArrows }}" step="6" name="groups[0][arrow_count]" required value="{{ old('groups.0.arrow_count', $maxArrows > 36 ? 72 : 36) }}" class="mt-1 min-h-12 w-full rounded-xl border-gray-300"></div>
                <div><label class="text-sm font-medium">每趟箭數 *</label><select name="groups[0][arrows_per_end]" class="mt-1 min-h-12 w-full rounded-xl border-gray-300"><option value="6">6 箭</option><option value="3">3 箭</option></select></div>
                <div><label class="text-sm font-medium">名額</label><input type="number" min="1" name="groups[0][quota]" value="{{ old('groups.0.quota') }}" placeholder="不填表示不限" class="mt-1 min-h-12 w-full rounded-xl border-gray-300"></div>
                <div><label class="text-sm font-medium">報名費</label><input type="number" min="0" name="groups[0][fee]" value="{{ old('groups.0.fee',0) }}" class="mt-1 min-h-12 w-full rounded-xl border-gray-300"></div>
                <input id="group-is-team" type="hidden" name="groups[0][is_team]" value="{{ old('groups.0.is_team', 0) ? 1 : 0 }}">
                <input id="group-team-type" type="hidden" name="groups[0][team_type]" value="{{ old('groups.0.team_type', 'standard') }}">
                <input id="group-team-size" type="hidden" name="groups[0][team_size]" value="{{ old('groups.0.team_size', 3) }}">
                @if($maxArrows > 36)
                    <div id="team-settings" class="sm:col-span-2 lg:col-span-3 hidden rounded-xl border border-violet-200 bg-violet-50 p-4"><div class="grid gap-4 sm:grid-cols-2"><label class="block text-sm text-violet-900">組隊截止<input type="datetime-local" name="groups[0][team_formation_end]" value="{{ old('groups.0.team_formation_end') }}" class="mt-1 min-h-12 w-full rounded-xl border-violet-200 bg-white"></label><label class="block text-sm text-violet-900">候補名額<select name="groups[0][team_substitute_limit]" class="mt-1 min-h-12 w-full rounded-xl border-violet-200 bg-white"><option value="0">不設候補</option><option value="1" @selected(old('groups.0.team_substitute_limit') == 1)>每隊 1 名候補</option></select></label></div><p class="mt-2 text-xs text-violet-700">未設定組隊截止時沿用報名截止；選手仍須先完成個人報名。</p></div>
                @endif
            </div>
        </section>

        <div class="sticky bottom-3 z-10 grid grid-cols-2 gap-3 rounded-2xl border bg-white/95 p-3 shadow-lg backdrop-blur sm:static sm:flex sm:justify-end sm:border-0 sm:bg-transparent sm:p-0 sm:shadow-none">
            <button name="submit_mode" value="draft" class="min-h-12 rounded-xl border px-4 text-sm font-medium text-gray-700">儲存草稿</button>
            <button name="submit_mode" value="publish" class="min-h-12 rounded-xl bg-indigo-600 px-5 text-sm font-semibold text-white hover:bg-indigo-500">建立並發布</button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const start = document.getElementById('event-start');
    const end = document.getElementById('event-end');
    const regEnd = document.getElementById('reg-end');
    const preset = document.getElementById('group-preset');
    const distance = document.getElementById('group-distance');
    const arrows = document.getElementById('group-arrows');
    const mode = document.getElementById('event-mode');
    const teamFormatInputs = [...document.querySelectorAll('input[name="team_format_selector"]')];
    const isTeam = document.getElementById('group-is-team');
    const teamType = document.getElementById('group-team-type');
    const teamSize = document.getElementById('group-team-size');
    const teamSettings = document.getElementById('team-settings');
    const summary = document.getElementById('competition-summary');
    const summaryNote = document.getElementById('competition-note');

    start.addEventListener('change', () => {
        if (!end.value) end.value = start.value;
        if (!regEnd.value && start.value) regEnd.value = `${start.value}T23:59`;
    });

    const presets = {
        outdoor70: { mode: 'outdoor', distance: '70m', arrows: {{ $maxArrows > 36 ? 72 : 36 }} },
        outdoor50: { mode: 'outdoor', distance: '50m', arrows: 72 },
        outdoor30: { mode: 'outdoor', distance: '30m', arrows: 36 },
        indoor18: { mode: 'indoor', distance: '18m', arrows: {{ $maxArrows > 36 ? 60 : 30 }} },
    };
    preset.addEventListener('change', () => {
        const selected = presets[preset.value];
        if (!selected) return;
        mode.value = selected.mode;
        distance.value = selected.distance;
        arrows.value = selected.arrows;
    });

    const renderCompetition = () => {
        const selected = teamFormatInputs.find(input => input.checked)?.value || null;
        isTeam.value = selected ? '1' : '0';
        teamType.value = selected === 'mixed' ? 'mixed' : 'standard';
        teamSize.value = selected === 'mixed' ? '2' : '3';
        teamSettings?.classList.toggle('hidden', !selected);
        const badges = ['<span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800">✓ 個人排名賽</span>'];
        if (selected === 'standard') badges.push('<span class="rounded-full bg-violet-100 px-3 py-1 text-xs font-semibold text-violet-800">✓ 3 人團體賽</span>');
        if (selected === 'mixed') badges.push('<span class="rounded-full bg-violet-100 px-3 py-1 text-xs font-semibold text-violet-800">✓ 男女混雙</span>');
        summary.innerHTML = badges.join('');
        summaryNote.textContent = selected ? '選手只需先報名這個個人組別，之後再進行組隊。' : '目前為純個人排名賽；建立後仍可編輯組別開啟團體功能。';
    };
    teamFormatInputs.forEach(input => input.addEventListener('change', renderCompetition));
    document.getElementById('clear-team-format')?.addEventListener('click', () => { teamFormatInputs.forEach(input => input.checked = false); renderCompetition(); });
    renderCompetition();
});
</script>
@endsection
