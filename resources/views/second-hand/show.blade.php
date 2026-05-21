@extends('layouts.app')
@section('title', $item->title)
@section('content')
<div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 py-6">
    <div class="rounded-2xl border bg-white p-4 sm:p-6 space-y-4">
        <h1 class="text-2xl font-semibold">{{ $item->title }}</h1>
        <p class="text-xl font-bold">NT$ {{ number_format($item->price) }}</p>
        <p class="text-sm text-gray-600">賣家：{{ $item->seller_display_name }}</p>
        <p class="text-sm text-gray-600">聯絡方式：{{ $item->contact_type === 'phone' ? '手機' : '社群媒體' }} / {{ $item->contact_value }}</p>
        @if($item->description)<p class="text-sm text-gray-700">{{ $item->description }}</p>@endif
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            @foreach($item->photos as $photo)
                <img src="{{ asset('storage/' . $photo->photo_path) }}" class="w-full h-56 object-cover rounded-xl" alt="{{ $item->title }}">
            @endforeach
        </div>
        @auth
            @if(auth()->id() === $item->seller_id || auth()->user()->isAdmin())
                <form method="POST" action="{{ route('second-hand.destroy', $item) }}" onsubmit="return confirm('確定刪除？');">
                    @csrf
                    @method('DELETE')
                    <button class="rounded-xl bg-red-600 px-4 py-2 text-white text-sm">刪除商品</button>
                </form>
            @endif
        @endauth
    </div>
</div>
@endsection
