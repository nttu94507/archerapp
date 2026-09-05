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

    @php($startAtStepTwo = collect($errors->keys())->contains(fn ($key) => str_starts_with($key, 'groups.')))
    <form method="POST" action="{{ route('organizer.events.store') }}" class="space-y-5" id="quick-event-form">
        @csrf

        <section id="event-step-one" class="rounded-2xl border bg-white p-4 shadow-sm sm:p-6 {{ $startAtStepTwo ? 'hidden' : '' }}">
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
            <div class="mt-6 flex justify-end border-t pt-4"><button id="go-to-step-two" type="button" class="min-h-12 rounded-xl bg-indigo-600 px-6 text-sm font-semibold text-white hover:bg-indigo-500">下一步：選擇組別 →</button></div>
        </section>

        <section id="event-step-two" class="rounded-2xl border bg-white p-4 shadow-sm sm:p-6 {{ $startAtStepTwo ? '' : 'hidden' }}">
            <div class="mb-5 flex flex-wrap items-start justify-between gap-3"><div><p class="text-xs font-semibold text-indigo-600">步驟 2</p><h2 class="text-lg font-semibold">第一個報名組別</h2><p class="mt-1 text-xs text-gray-500">先建立主要組別，發布後仍可新增更多組別。</p></div><button id="back-to-step-one" type="button" class="min-h-10 rounded-xl border px-4 text-sm font-medium text-gray-700 hover:bg-gray-50">← 返回基本資料</button></div>
            @if($maxArrows === 36)<div class="mb-4 flex items-center justify-between gap-3 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800"><span>免費方案僅支援單局最多 36 箭。</span><a href="{{ route('store.index') }}" class="shrink-0 font-semibold underline">查看方案</a></div>@endif
            <div class="mb-5">
                <div id="event-template-grid" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <button type="button" class="event-template min-h-24 rounded-2xl border-2 border-indigo-500 bg-indigo-50 p-4 text-left" data-preset="r70o" data-mode="outdoor" data-round="double"><strong class="block text-sm text-indigo-950">反曲弓 70m</strong><span class="mt-1 block text-xs text-indigo-700">公開組</span></button>
                    <button type="button" class="event-template min-h-24 rounded-2xl border-2 border-gray-200 bg-white p-4 text-left" data-preset="c50o" data-mode="outdoor" data-round="double"><strong class="block text-sm">複合弓 50m</strong><span class="mt-1 block text-xs text-gray-500">公開組</span></button>
                    <button type="button" class="event-template min-h-24 rounded-2xl border-2 border-gray-200 bg-white p-4 text-left" data-preset="r30o" data-mode="outdoor" data-round="single"><strong class="block text-sm">反曲弓 30m</strong><span class="mt-1 block text-xs text-gray-500">公開組</span></button>
                    <button type="button" class="event-template hidden min-h-24 rounded-2xl border-2 border-gray-200 bg-white p-4 text-left" data-preset="i18ro" data-mode="indoor" data-round="double"><strong class="block text-sm">室內反曲弓 18m</strong><span class="mt-1 block text-xs text-gray-500">公開組</span></button>
                    <button type="button" class="event-template hidden min-h-24 rounded-2xl border-2 border-gray-200 bg-white p-4 text-left" data-preset="i18co" data-mode="indoor" data-round="double"><strong class="block text-sm">室內複合弓 18m</strong><span class="mt-1 block text-xs text-gray-500">公開組</span></button>
                    <button type="button" class="event-template min-h-24 rounded-2xl border-2 border-dashed border-gray-300 bg-gray-50 p-4 text-left" data-preset="custom" data-mode="all" data-round="single"><strong class="block text-sm">自訂賽制</strong><span class="mt-1 block text-xs text-gray-500">自行設定完整內容</span></button>
                </div>
                <div id="selected-template-summary" class="mt-3 rounded-xl bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">已選擇：反曲弓 70 公尺公開組</div>
            </div>
            <div id="advanced-group-settings" class="hidden">
            <div class="mb-5 rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div><h3 class="font-semibold text-slate-950">賽事內容</h3><p class="mt-1 text-xs text-slate-600">選擇這個組別除了個人排名賽，還要開放哪一種團體賽。</p></div>
                    @if($maxArrows === 36)<a href="{{ route('store.index') }}" class="text-xs font-semibold text-indigo-600">團體賽需升級 →</a>@endif
                </div>
                <div class="mt-4 grid gap-3 md:grid-cols-3">
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4"><span class="inline-flex rounded-full bg-emerald-600 px-2 py-0.5 text-[10px] font-bold text-white">固定包含</span><p class="mt-2 font-semibold text-emerald-950">個人排名賽</p><p class="mt-1 text-xs text-emerald-700">所有選手先完成個人報名與排名計分。</p></div>
                    <label class="team-format-card cursor-pointer rounded-xl border bg-white p-4 transition has-[:checked]:border-violet-500 has-[:checked]:bg-violet-50 {{ $maxArrows === 36 ? 'opacity-60' : '' }}"><span class="flex items-start gap-3"><input type="checkbox" id="standard-team-selector" @checked(old('groups.0.standard_team_enabled')) @disabled($maxArrows === 36) class="mt-0.5 h-5 w-5 rounded text-violet-600"><span><strong class="block text-sm text-slate-950">3 人團體賽</strong><span class="mt-1 block text-xs text-slate-600">每隊登記4人，團體排名取個人成績最高3人，每局／趟共6箭。</span></span></span></label>
                    <label class="team-format-card cursor-pointer rounded-xl border bg-white p-4 transition has-[:checked]:border-violet-500 has-[:checked]:bg-violet-50 {{ $maxArrows === 36 ? 'opacity-60' : '' }}"><span class="flex items-start gap-3"><input type="checkbox" id="mixed-team-selector" @checked(old('groups.0.mixed_team_enabled')) @disabled($maxArrows === 36) class="mt-0.5 h-5 w-5 rounded text-violet-600"><span><strong class="block text-sm text-slate-950">男女混雙</strong><span class="mt-1 block text-xs text-slate-600">固定一男一女，每局／趟共 4 箭。</span></span></span></label>
                </div>
                @if($maxArrows > 36)<button id="clear-team-format" type="button" class="mt-3 min-h-9 text-xs font-semibold text-slate-500 underline">此組別不開放團體賽</button>@endif
                <div class="mt-4 rounded-xl border border-indigo-100 bg-white px-4 py-3" aria-live="polite"><p class="text-xs font-semibold text-indigo-600">建立架構預覽</p><div id="competition-summary" class="mt-2 flex flex-wrap gap-2"></div><p id="competition-note" class="mt-2 text-xs text-slate-500"></p></div>
            </div>
            <div class="mb-4">
                <label class="text-sm font-medium">快速套用賽制</label>
                <select id="group-preset" class="mt-1 min-h-12 w-full rounded-xl border-indigo-200 bg-indigo-50">
                    @foreach([
                        'r70o'=>'反曲弓 70 公尺公開組','r70m'=>'反曲弓 70 公尺男子組','r70f'=>'反曲弓 70 公尺女子組',
                        'r30o'=>'反曲弓 30 公尺公開組','r30m'=>'反曲弓 30 公尺男子組','r30f'=>'反曲弓 30 公尺女子組',
                        'c50o'=>'複合弓 50 公尺公開組','c50m'=>'複合弓 50 公尺男子組','c50f'=>'複合弓 50 公尺女子組',
                    ] as $value=>$label)<option value="{{ $value }}" data-mode="outdoor">{{ $label }}</option>@endforeach
                    @foreach([
                        'i18ro'=>'反曲弓 18 公尺公開組','i18rm'=>'反曲弓 18 公尺男子組','i18rf'=>'反曲弓 18 公尺女子組',
                        'i18co'=>'複合弓 18 公尺公開組','i18cm'=>'複合弓 18 公尺男子組','i18cf'=>'複合弓 18 公尺女子組',
                    ] as $value=>$label)<option value="{{ $value }}" data-mode="indoor">{{ $label }}</option>@endforeach
                    <option value="custom" data-mode="all">自訂</option>
                </select>
                <p id="preset-mode-help" class="mt-1 text-xs text-gray-500">預設組別會依步驟 1 選擇的室內／室外賽事切換。室外 70m／{{ $maxArrows > 36 ? 72 : 36 }} 箭・室內 18m／{{ $maxArrows > 36 ? 60 : 30 }} 箭</p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div class="sm:col-span-2 lg:col-span-1"><label class="text-sm font-medium">組別名稱 *</label><input id="group-name" name="groups[0][name]" required value="{{ old('groups.0.name','反曲弓 70 公尺公開組') }}" class="mt-1 min-h-12 w-full rounded-xl border-gray-300"></div>
                <div><label class="text-sm font-medium">弓種</label><select id="group-bow" name="groups[0][bow_type]" class="mt-1 min-h-12 w-full rounded-xl border-gray-300"><option value="recurve">反曲弓</option><option value="compound">複合弓</option><option value="barebow">光弓</option><option value="">不限</option></select></div>
                <div><label class="text-sm font-medium">性別</label><select id="group-gender" name="groups[0][gender]" class="mt-1 min-h-12 w-full rounded-xl border-gray-300"><option value="open">公開／不限</option><option value="male">男子</option><option value="female">女子</option></select></div>
                <div><label class="text-sm font-medium">距離</label><input id="group-distance" name="groups[0][distance]" value="{{ old('groups.0.distance','70m') }}" class="mt-1 min-h-12 w-full rounded-xl border-gray-300"></div>
                <div><label class="text-sm font-medium">排名賽局數 *</label><select id="group-round-format" class="mt-1 min-h-12 w-full rounded-xl border-gray-300"><option value="single">單局（室外 36 箭／室內 30 箭）</option>@if($maxArrows > 36)<option value="double">雙局（室外 72 箭／室內 60 箭）</option>@endif</select>@if($maxArrows === 36)<p class="mt-1 text-xs text-amber-700">免費方案僅支援單局。</p>@endif<input id="group-arrows" type="hidden" name="groups[0][arrow_count]" value="{{ old('groups.0.arrow_count', 36) }}"></div>
                <input id="group-arrows-per-end" type="hidden" name="groups[0][arrows_per_end]" value="{{ old('groups.0.arrows_per_end', old('mode') === 'indoor' ? 3 : 6) }}">
                <div><label class="text-sm font-medium">名額</label><input type="number" min="1" name="groups[0][quota]" value="{{ old('groups.0.quota') }}" placeholder="不填表示不限" class="mt-1 min-h-12 w-full rounded-xl border-gray-300"></div>
                <div><label class="text-sm font-medium">報名費</label><input type="number" min="0" name="groups[0][fee]" value="{{ old('groups.0.fee',0) }}" class="mt-1 min-h-12 w-full rounded-xl border-gray-300"></div>
                <input id="group-is-team" type="hidden" name="groups[0][is_team]" value="{{ old('groups.0.is_team', 0) ? 1 : 0 }}">
                <input id="group-standard-team" type="hidden" name="groups[0][standard_team_enabled]" value="{{ old('groups.0.standard_team_enabled', 0) ? 1 : 0 }}">
                <input id="group-mixed-team" type="hidden" name="groups[0][mixed_team_enabled]" value="{{ old('groups.0.mixed_team_enabled', 0) ? 1 : 0 }}">
                <input id="group-team-type" type="hidden" name="groups[0][team_type]" value="{{ old('groups.0.team_type', 'standard') }}">
                <input id="group-team-size" type="hidden" name="groups[0][team_size]" value="{{ old('groups.0.team_size', 3) }}">
                @if($maxArrows > 36)
                    <div id="team-settings" class="sm:col-span-2 lg:col-span-3 hidden rounded-xl border border-violet-200 bg-violet-50 p-4"><label class="block text-sm text-violet-900">組隊截止<input type="datetime-local" name="groups[0][team_formation_end]" value="{{ old('groups.0.team_formation_end') }}" class="mt-1 min-h-12 w-full rounded-xl border-violet-200 bg-white"></label><p class="mt-2 text-xs text-violet-700">未設定時沿用報名截止。三人團體的第4人自動依排名成為替補；混雙固定2人且沒有替補。</p></div>
                @endif
            </div>
            </div>
        </section>

        <div id="event-final-actions" class="sticky bottom-3 z-10 {{ $startAtStepTwo ? 'grid' : 'hidden' }} grid-cols-2 gap-3 rounded-2xl border bg-white/95 p-3 shadow-lg backdrop-blur sm:static sm:flex sm:justify-end sm:border-0 sm:bg-transparent sm:p-0 sm:shadow-none">
            <button name="submit_mode" value="draft" class="min-h-12 rounded-xl border px-4 text-sm font-medium text-gray-700">儲存草稿</button>
            <button name="submit_mode" value="publish" class="min-h-12 rounded-xl bg-indigo-600 px-5 text-sm font-semibold text-white hover:bg-indigo-500">建立並發布</button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('quick-event-form');
    const stepOne = document.getElementById('event-step-one');
    const stepTwo = document.getElementById('event-step-two');
    const finalActions = document.getElementById('event-final-actions');
    const nextButton = document.getElementById('go-to-step-two');
    const backButton = document.getElementById('back-to-step-one');
    let currentStep = stepTwo.classList.contains('hidden') ? 1 : 2;
    const showStep = step => {
        currentStep = step;
        stepOne.classList.toggle('hidden', step !== 1);
        stepTwo.classList.toggle('hidden', step !== 2);
        finalActions.classList.toggle('hidden', step !== 2);
        finalActions.classList.toggle('grid', step === 2);
        window.scrollTo({ top: form.offsetTop, behavior: 'smooth' });
    };
    const advanceToStepTwo = () => {
        const invalidField = stepOne.querySelector(':invalid');
        if (invalidField) {
            invalidField.reportValidity();
            invalidField.focus();
            return false;
        }
        showStep(2);
        return true;
    };
    nextButton.addEventListener('click', advanceToStepTwo);
    backButton.addEventListener('click', () => showStep(1));
    form.addEventListener('submit', event => {
        if (currentStep === 1) {
            event.preventDefault();
            advanceToStepTwo();
        }
    });
    const start = document.getElementById('event-start');
    const end = document.getElementById('event-end');
    const regEnd = document.getElementById('reg-end');
    const preset = document.getElementById('group-preset');
    const templateButtons = [...document.querySelectorAll('.event-template')];
    const advancedSettings = document.getElementById('advanced-group-settings');
    const selectedTemplateSummary = document.getElementById('selected-template-summary');
    const distance = document.getElementById('group-distance');
    const arrows = document.getElementById('group-arrows');
    const roundFormat = document.getElementById('group-round-format');
    const arrowsPerEnd = document.getElementById('group-arrows-per-end');
    const mode = document.getElementById('event-mode');
    const groupName = document.getElementById('group-name');
    const groupBow = document.getElementById('group-bow');
    const groupGender = document.getElementById('group-gender');
    const standardTeamSelector = document.getElementById('standard-team-selector');
    const mixedTeamSelector = document.getElementById('mixed-team-selector');
    const isTeam = document.getElementById('group-is-team');
    const teamType = document.getElementById('group-team-type');
    const teamSize = document.getElementById('group-team-size');
    const standardTeam = document.getElementById('group-standard-team');
    const mixedTeam = document.getElementById('group-mixed-team');
    const teamSettings = document.getElementById('team-settings');
    const summary = document.getElementById('competition-summary');
    const summaryNote = document.getElementById('competition-note');

    start.addEventListener('change', () => {
        if (!end.value) end.value = start.value;
        if (!regEnd.value && start.value) regEnd.value = `${start.value}T23:59`;
    });

    const presets = {
        r70o: { name:'反曲弓 70 公尺公開組', bow:'recurve', gender:'open', mode:'outdoor', distance:'70m' },
        r70m: { name:'反曲弓 70 公尺男子組', bow:'recurve', gender:'male', mode:'outdoor', distance:'70m' },
        r70f: { name:'反曲弓 70 公尺女子組', bow:'recurve', gender:'female', mode:'outdoor', distance:'70m' },
        r30o: { name:'反曲弓 30 公尺公開組', bow:'recurve', gender:'open', mode:'outdoor', distance:'30m', arrows:36 },
        r30m: { name:'反曲弓 30 公尺男子組', bow:'recurve', gender:'male', mode:'outdoor', distance:'30m', arrows:36 },
        r30f: { name:'反曲弓 30 公尺女子組', bow:'recurve', gender:'female', mode:'outdoor', distance:'30m', arrows:36 },
        c50o: { name:'複合弓 50 公尺公開組', bow:'compound', gender:'open', mode:'outdoor', distance:'50m' },
        c50m: { name:'複合弓 50 公尺男子組', bow:'compound', gender:'male', mode:'outdoor', distance:'50m' },
        c50f: { name:'複合弓 50 公尺女子組', bow:'compound', gender:'female', mode:'outdoor', distance:'50m' },
        i18ro: { name:'反曲弓 18 公尺公開組', bow:'recurve', gender:'open', mode:'indoor', distance:'18m' },
        i18rm: { name:'反曲弓 18 公尺男子組', bow:'recurve', gender:'male', mode:'indoor', distance:'18m' },
        i18rf: { name:'反曲弓 18 公尺女子組', bow:'recurve', gender:'female', mode:'indoor', distance:'18m' },
        i18co: { name:'複合弓 18 公尺公開組', bow:'compound', gender:'open', mode:'indoor', distance:'18m' },
        i18cm: { name:'複合弓 18 公尺男子組', bow:'compound', gender:'male', mode:'indoor', distance:'18m' },
        i18cf: { name:'複合弓 18 公尺女子組', bow:'compound', gender:'female', mode:'indoor', distance:'18m' },
    };
    const templateLabel = button => button?.querySelector('strong')?.textContent.trim() ?? '自訂賽制';
    const selectTemplate = button => {
        if (!button || button.classList.contains('hidden')) return;
        templateButtons.forEach(item => {
            const selected = item === button;
            item.classList.toggle('border-indigo-500', selected);
            item.classList.toggle('bg-indigo-50', selected);
            item.classList.toggle('border-gray-200', !selected && item.dataset.preset !== 'custom');
            item.classList.toggle('bg-white', !selected && item.dataset.preset !== 'custom');
        });
        preset.value = button.dataset.preset;
        advancedSettings.classList.toggle('hidden', preset.value !== 'custom');
        if (preset.value !== 'custom') {
            if (standardTeamSelector) standardTeamSelector.checked = false;
            if (mixedTeamSelector) mixedTeamSelector.checked = false;
            applySelectedPreset();
            if (button.dataset.round === 'double' && roundFormat.querySelector('option[value="double"]')) roundFormat.value = 'double';
            else roundFormat.value = 'single';
            syncArrowCount();
            renderCompetition();
        }
        selectedTemplateSummary.textContent = preset.value === 'custom' ? '自訂賽制' : `已選擇：${groupName.value}`;
    };
    const syncArrowCount = () => {
        const singleArrows = mode.value === 'indoor' ? 30 : 36;
        arrows.value = roundFormat.value === 'double' ? singleArrows * 2 : singleArrows;
        arrowsPerEnd.value = mode.value === 'indoor' ? 3 : 6;
    };
    const applySelectedPreset = () => {
        const selected = presets[preset.value];
        if (!selected) return;
        if(selected.name) groupName.value=selected.name;
        if(selected.bow) groupBow.value=selected.bow;
        if(selected.gender) groupGender.value=selected.gender;
        distance.value = selected.distance;
        syncArrowCount();
    };
    const syncPresetOptions = (applyFirst = false) => {
        const available = [...preset.options].filter(option => option.dataset.mode === mode.value || option.dataset.mode === 'all');
        [...preset.options].forEach(option => {
            const visible = option.dataset.mode === mode.value || option.dataset.mode === 'all';
            option.hidden = !visible;
            option.disabled = !visible;
        });
        if (!available.includes(preset.selectedOptions[0])) preset.value = available[0]?.value ?? 'custom';
        if (applyFirst) applySelectedPreset();
    };
    const syncTemplateOptions = (selectFirst = false) => {
        templateButtons.forEach(button => button.classList.toggle('hidden', button.dataset.mode !== 'all' && button.dataset.mode !== mode.value));
        if (selectFirst) selectTemplate(templateButtons.find(button => button.dataset.mode === mode.value));
    };
    preset.addEventListener('change', applySelectedPreset);
    roundFormat.addEventListener('change', syncArrowCount);
    mode.addEventListener('change', () => {
        syncPresetOptions(true);
        syncArrowCount();
        syncTemplateOptions(true);
    });
    roundFormat.value = Number(arrows.value) > (mode.value === 'indoor' ? 30 : 36) ? 'double' : 'single';
    syncPresetOptions();
    syncArrowCount();

    const renderCompetition = () => {
        const standardSelected = !!standardTeamSelector?.checked;
        const mixedSelected = !!mixedTeamSelector?.checked;
        const selected = standardSelected || mixedSelected;
        isTeam.value = selected ? '1' : '0';
        standardTeam.value = standardSelected ? '1' : '0';
        mixedTeam.value = mixedSelected ? '1' : '0';
        teamType.value = mixedSelected && !standardSelected ? 'mixed' : 'standard';
        teamSize.value = mixedSelected && !standardSelected ? '2' : '3';
        teamSettings?.classList.toggle('hidden', !selected);
        const badges = ['<span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800">✓ 個人排名賽</span>'];
        if (standardSelected) badges.push('<span class="rounded-full bg-violet-100 px-3 py-1 text-xs font-semibold text-violet-800">✓ 3 人團體賽</span>');
        if (mixedSelected) badges.push('<span class="rounded-full bg-violet-100 px-3 py-1 text-xs font-semibold text-violet-800">✓ 男女混雙</span>');
        summary.innerHTML = badges.join('');
        summaryNote.textContent = selected ? '選手只需先報名這個個人組別，之後再進行組隊。' : '目前為純個人排名賽；建立後仍可編輯組別開啟團體功能。';
    };
    [standardTeamSelector,mixedTeamSelector].filter(Boolean).forEach(input => input.addEventListener('change', renderCompetition));
    document.getElementById('clear-team-format')?.addEventListener('click', () => { if(standardTeamSelector) standardTeamSelector.checked=false; if(mixedTeamSelector) mixedTeamSelector.checked=false; renderCompetition(); });
    templateButtons.forEach(button => button.addEventListener('click', () => selectTemplate(button)));
    renderCompetition();
    syncTemplateOptions();
    selectTemplate(@js($startAtStepTwo) ? templateButtons.find(button => button.dataset.preset === 'custom') : templateButtons.find(button => button.dataset.preset === preset.value && !button.classList.contains('hidden')));
});
</script>
@endsection
