@extends('layouts.app')

@section('title', 'Admin / 新增賽事')

@section('content')
    <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8 py-8">
        <div class="mb-6">
            <p class="text-xs uppercase tracking-widest text-indigo-600 font-semibold">Admin</p>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">新增賽事</h1>
            <p class="text-sm text-gray-500 mt-1">在後台建立賽事並設定報名資訊。</p>
        </div>

        <form action="{{ route('admin.events.store') }}" method="POST" class="space-y-8">
            @csrf
            @include('events.partials._form-fields', ['event' => null, 'cancelRoute' => route('admin.events.index')])
        </form>
    </div>
@endsection
