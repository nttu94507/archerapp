@extends('layouts.app')
@section('title','新增 Badge')
@section('content')
<div class="mx-auto max-w-3xl space-y-6 px-4 py-6 sm:px-6">
    <div><a href="{{route('organizer.badges.index')}}" class="text-sm text-indigo-600">← 返回 Badge 列表</a><h1 class="mt-2 text-2xl font-bold">新增 Badge</h1></div>
    @if($errors->any())<div class="rounded-xl bg-red-50 p-4 text-sm text-red-700">{{$errors->first()}}</div>@endif
    <section class="rounded-2xl border bg-white p-5 shadow-sm sm:p-6">@include('organizer.badge-hub._form')</section>
</div>
@endsection
