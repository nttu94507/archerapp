{{-- resources/views/events/create.blade.php --}}
@extends('layouts.app')

@section('title', '新增賽事')

@section('content')
    <div class="mx-auto max-w-3xl px-4 py-6 sm:px-6 sm:py-8 lg:px-8">

        {{-- Page Header --}}
        <div class="mb-5 sm:mb-6">
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">新增賽事</h1>
            <p class="mt-1 text-sm leading-6 text-gray-500">填寫以下欄位以建立一個新賽事。</p>
        </div>

        {{-- Form --}}
        <form action="{{ route('events.store') }}" method="POST" class="space-y-5 pb-3 sm:space-y-8">
            @csrf
            @include('events.partials._form-fields', ['event' => null, 'cancelRoute' => route('events.index')])
        </form>
    </div>
@endsection
