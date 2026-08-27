@extends('layouts.app')

@section('title', '快速建立賽事')

@section('content')
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
            </div>
        </section>

        <section class="rounded-2xl border bg-white p-4 shadow-sm sm:p-6">
            <div class="mb-5"><p class="text-xs font-semibold text-indigo-600">步驟 2</p><h2 class="text-lg font-semibold">第一個報名組別</h2><p class="mt-1 text-xs text-gray-500">先建立主要組別，發布後仍可新增更多組別。</p></div>
            @if($maxArrows === 36)<div class="mb-4 flex items-center justify-between gap-3 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800"><span>免費方案僅支援單局最多 36 箭。</span><a href="{{ route('store.index') }}" class="shrink-0 font-semibold underline">查看方案</a></div>@endif
            <div class="mb-4">
                <label class="text-sm font-medium">快速套用賽制</label>
                <select id="group-preset" class="mt-1 min-h-12 w-full rounded-xl border-indigo-200 bg-indigo-50">
                    @if($maxArrows > 36)<option value="outdoor70">室外 70m／72 箭</option>
                    <option value="outdoor50">室外 50m／72 箭</option>@endif
                    <option value="outdoor30">室外 30m／36 箭</option>
                    @if($maxArrows > 36)<option value="indoor18">室內 18m／60 箭</option>@endif
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
                <input type="hidden" name="groups[0][is_team]" value="0">
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

    start.addEventListener('change', () => {
        if (!end.value) end.value = start.value;
        if (!regEnd.value && start.value) regEnd.value = `${start.value}T23:59`;
    });

    const presets = {
        outdoor70: { mode: 'outdoor', distance: '70m', arrows: 72 },
        outdoor50: { mode: 'outdoor', distance: '50m', arrows: 72 },
        outdoor30: { mode: 'outdoor', distance: '30m', arrows: 36 },
        indoor18: { mode: 'indoor', distance: '18m', arrows: 60 },
    };
    preset.addEventListener('change', () => {
        const selected = presets[preset.value];
        if (!selected) return;
        mode.value = selected.mode;
        distance.value = selected.distance;
        arrows.value = selected.arrows;
    });
});
</script>
@endsection
