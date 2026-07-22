@extends('layouts.app')
@section('title', '建立賽事')
@section('content')
<div class="mx-auto max-w-3xl px-4 py-6 sm:px-6 sm:py-8"><div class="mb-5 sm:mb-6"><a href="{{ route('organizer.events.index') }}" class="inline-flex min-h-11 items-center text-sm font-medium text-indigo-600">← 我的賽事</a><h1 class="text-2xl font-bold">建立賽事草稿</h1><p class="mt-1 text-sm leading-6 text-gray-500">先建立基本資料，之後可新增組別並送交平台審核。</p></div><form action="{{ route('organizer.events.store') }}" method="POST" class="space-y-5 pb-3 sm:space-y-8">@csrf @include('events.partials._form-fields', ['event'=>null, 'cancelRoute'=>route('organizer.events.index'), 'showVerification'=>false])</form></div>
@endsection
