@extends('layouts.app')
@section('title','編輯組別')
@section('content')
<div class="mx-auto max-w-2xl px-4 py-8">
    <a href="{{ route('events.groups.index',$event) }}" class="text-sm text-indigo-600">← 返回組別</a>
    <h1 class="mt-2 text-2xl font-bold">編輯 {{ $group->name }}</h1>
    <form method="POST" action="{{ route('events.groups.update',[$event,$group]) }}" class="mt-6 grid gap-4 rounded-2xl border bg-white p-6">
        @csrf @method('PUT')
        @foreach(['name'=>'名稱','age_class'=>'年齡組','distance'=>'距離','arrow_count'=>'總箭數','quota'=>'名額','fee'=>'報名費'] as $field=>$label)
            <div><label class="text-sm font-medium">{{ $label }}</label><input name="{{ $field }}" value="{{ old($field,$group->$field) }}" class="mt-1 w-full rounded-xl border-gray-300"></div>
        @endforeach
        <div><label class="text-sm font-medium">弓種</label><select name="bow_type" class="mt-1 w-full rounded-xl border-gray-300">@foreach(['recurve'=>'反曲','compound'=>'複合','barebow'=>'光弓'] as $v=>$l)<option value="{{ $v }}" @selected($group->bow_type===$v)>{{ $l }}</option>@endforeach</select></div>
        <div><label class="text-sm font-medium">性別</label><select name="gender" class="mt-1 w-full rounded-xl border-gray-300">@foreach(['male'=>'男子','female'=>'女子','open'=>'公開'] as $v=>$l)<option value="{{ $v }}" @selected($group->gender===$v)>{{ $l }}</option>@endforeach</select></div>
        <div><label class="text-sm font-medium">每趟箭數</label><select name="arrows_per_end" class="mt-1 w-full rounded-xl border-gray-300"><option value="6" @selected($group->arrows_per_end===6)>6 箭</option><option value="3" @selected($group->arrows_per_end===3)>3 箭</option></select></div>
        <div><label class="text-sm font-medium">組別報名開始</label><input type="datetime-local" name="reg_start" value="{{ optional($group->reg_start)->format('Y-m-d\TH:i') }}" class="mt-1 w-full rounded-xl border-gray-300"></div>
        <div><label class="text-sm font-medium">組別報名截止</label><input type="datetime-local" name="reg_end" value="{{ optional($group->reg_end)->format('Y-m-d\TH:i') }}" class="mt-1 w-full rounded-xl border-gray-300"></div>
        <input type="hidden" name="is_team" value="0">
        <button class="rounded-xl bg-indigo-600 px-5 py-2.5 text-white">儲存</button>
    </form>
</div>
@endsection
