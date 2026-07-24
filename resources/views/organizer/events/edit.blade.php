@extends('layouts.app')
@section('title', '編輯 '.$event->name)
@section('content')
<div class="mx-auto max-w-3xl px-4 py-6 sm:px-6 sm:py-8"><div class="mb-5 sm:mb-6"><a href="{{ route('organizer.events.show', $event) }}" class="inline-flex min-h-11 items-center text-sm font-medium text-indigo-600">← 返回賽事工作台</a><h1 class="text-2xl font-bold">編輯賽事</h1></div><form action="{{ route('organizer.events.update', $event) }}" method="POST" class="space-y-5 pb-3 sm:space-y-8">@csrf @method('PUT') @include('events.partials._form-fields', ['event'=>$event, 'cancelRoute'=>route('organizer.events.show',$event), 'showVerification'=>false])</form></div>
@endsection
