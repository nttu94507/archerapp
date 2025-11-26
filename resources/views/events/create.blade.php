{{-- resources/views/events/create.blade.php --}}
@extends('layouts.app')

@section('title', '新增賽事')

@section('content')
    <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8 py-8">

        {{-- Page Header --}}
        <div class="mb-6">
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">新增賽事</h1>
            <p class="text-sm text-gray-500 mt-1">填寫以下欄位以建立一個新賽事。</p>
        </div>

        {{-- Form --}}
        <form action="{{ route('events.store') }}" method="POST" class="space-y-8">
            @csrf
            @include('events.partials._form-fields', ['event' => null, 'cancelRoute' => route('events.index')])
        </form>
    </div>
@endsection
