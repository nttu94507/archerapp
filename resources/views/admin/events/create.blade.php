@extends('layouts.app')

@section('title', 'Admin / 新增賽事')

@section('content')
    <div class="mx-auto max-w-3xl px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
        <div class="mb-5 sm:mb-6">
            <p class="text-xs uppercase tracking-widest text-indigo-600 font-semibold">Admin</p>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">新增賽事</h1>
            <p class="mt-1 text-sm leading-6 text-gray-500">在後台建立賽事並設定報名資訊。</p>
        </div>

        <form action="{{ route('admin.events.store') }}" method="POST" class="space-y-5 pb-3 sm:space-y-8">
            @csrf
            @include('events.partials._form-fields', ['event' => null, 'cancelRoute' => route('admin.events.index'), 'showVerification' => true])
        </form>
    </div>
@endsection
