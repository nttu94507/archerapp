@extends('layouts.app')
@section('title', '建立賽事')
@section('content')
<div class="mx-auto max-w-3xl px-4 py-8 sm:px-6"><div class="mb-6"><a href="{{ route('organizer.events.index') }}" class="text-sm text-indigo-600">← 我的賽事</a><h1 class="mt-2 text-2xl font-bold">建立賽事草稿</h1><p class="mt-1 text-sm text-gray-500">完成基本資料與組別後，再送交平台審核發布。</p></div><form action="{{ route('organizer.events.store') }}" method="POST" class="space-y-8">@csrf @include('events.partials._form-fields', ['event'=>null, 'cancelRoute'=>route('organizer.events.index'), 'showVerification'=>false])</form></div>
@endsection
