@extends('layouts.app')
@section('title', '編輯 '.$event->name)
@section('content')
<div class="mx-auto max-w-3xl px-4 py-8 sm:px-6"><div class="mb-6"><a href="{{ route('organizer.events.show', $event) }}" class="text-sm text-indigo-600">← 返回賽事工作台</a><h1 class="mt-2 text-2xl font-bold">編輯賽事</h1></div><form action="{{ route('organizer.events.update', $event) }}" method="POST" class="space-y-8">@csrf @method('PUT') @include('events.partials._form-fields', ['event'=>$event, 'cancelRoute'=>route('organizer.events.show',$event), 'showVerification'=>false])</form></div>
@endsection
