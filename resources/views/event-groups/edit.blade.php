@extends('layouts.app')
@section('title','編輯組別')
@section('content')
<div class="mx-auto max-w-2xl px-4 py-8">
    <a href="{{ route('events.groups.index',$event) }}" class="text-sm text-indigo-600">← 返回組別</a>
    <h1 class="mt-2 text-2xl font-bold">編輯 {{ $group->name }}</h1>
    <form method="POST" action="{{ route('events.groups.update',[$event,$group]) }}" class="mt-6 grid gap-4 rounded-2xl border bg-white p-6" x-data="{ customWindow: {{ $group->usesCustomRegistrationWindow() ? 'true' : 'false' }} }">
        @csrf @method('PUT')
        @foreach(['name'=>'名稱','age_class'=>'年齡組','distance'=>'距離','quota'=>'名額','fee'=>'報名費'] as $field=>$label)<div><label class="text-sm font-medium">{{ $label }}</label><input name="{{ $field }}" value="{{ old($field,$group->$field) }}" class="mt-1 w-full rounded-xl border-gray-300"></div>@endforeach
        @php($singleArrows = $event->mode === 'indoor' ? 30 : 36)
        <div x-data="{ roundFormat: {{ old('arrow_count', $group->arrow_count) > $singleArrows ? "'double'" : "'single'" }} }"><label class="text-sm font-medium">排名賽局數</label><select x-model="roundFormat" class="mt-1 w-full rounded-xl border-gray-300"><option value="single">單局（{{ $singleArrows }} 箭）</option>@if($maxArrows > 36)<option value="double">雙局（{{ $singleArrows * 2 }} 箭）</option>@endif</select><input type="hidden" name="arrow_count" :value="roundFormat === 'double' ? {{ $singleArrows * 2 }} : {{ $singleArrows }}">@if($maxArrows === 36)<p class="mt-1 text-xs text-amber-700">免費方案僅支援單局。</p>@endif</div>
        <div><label class="text-sm font-medium">弓種</label><select name="bow_type" class="mt-1 w-full rounded-xl border-gray-300">@foreach(['recurve'=>'反曲','compound'=>'複合','barebow'=>'光弓'] as $v=>$l)<option value="{{ $v }}" @selected($group->bow_type===$v)>{{ $l }}</option>@endforeach</select></div>
        <div><label class="text-sm font-medium">性別</label><select name="gender" class="mt-1 w-full rounded-xl border-gray-300">@foreach(['male'=>'男子','female'=>'女子','open'=>'公開'] as $v=>$l)<option value="{{ $v }}" @selected($group->gender===$v)>{{ $l }}</option>@endforeach</select></div>
        <input type="hidden" name="arrows_per_end" value="{{ $event->mode === 'indoor' ? 3 : 6 }}">
        <label class="inline-flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" name="use_custom_reg_window" value="1" x-model="customWindow" class="h-5 w-5 rounded">自訂此組報名時間</label>
        <div x-show="customWindow" class="grid gap-4 sm:grid-cols-2"><div><label class="text-sm font-medium">報名開始</label><input type="datetime-local" name="reg_start" :required="customWindow" value="{{ optional($group->reg_start)->format('Y-m-d\TH:i') }}" class="mt-1 w-full rounded-xl border-gray-300"></div><div><label class="text-sm font-medium">報名截止</label><input type="datetime-local" name="reg_end" :required="customWindow" value="{{ optional($group->reg_end)->format('Y-m-d\TH:i') }}" class="mt-1 w-full rounded-xl border-gray-300"></div></div>
        <input type="hidden" name="is_team" value="0">
        @if($event->hasPlanFeature('team_competition'))<div class="rounded-xl border p-4"><p class="text-sm font-semibold">團體賽形式（可複選）</p><div class="mt-2 grid gap-2 sm:grid-cols-2"><label class="flex min-h-11 items-center gap-3 rounded-xl bg-gray-50 px-3 text-sm"><input type="checkbox" name="standard_team_enabled" value="1" @checked(old('standard_team_enabled',$group->standard_team_enabled)) class="h-5 w-5 rounded">3 人團體賽</label><label class="flex min-h-11 items-center gap-3 rounded-xl bg-gray-50 px-3 text-sm"><input type="checkbox" name="mixed_team_enabled" value="1" @checked(old('mixed_team_enabled',$group->mixed_team_enabled)) class="h-5 w-5 rounded">男女混雙</label></div><p class="mt-2 text-xs text-amber-700">每位選手只能選擇其中一種團體形式。三人團體登記4人並取前三高，混雙固定2人且沒有替補。</p><label class="mt-3 block text-sm">組隊截止<input type="datetime-local" name="team_formation_end" value="{{ old('team_formation_end', optional($group->team_formation_end)->format('Y-m-d\TH:i')) }}" class="mt-1 w-full rounded-xl border-gray-300"></label><p class="mt-1 text-xs text-gray-500">未設定時沿用組別或賽事報名截止。</p></div>@endif
        <button class="rounded-xl bg-indigo-600 px-5 py-2.5 text-white">儲存</button>
    </form>
</div>
@endsection
