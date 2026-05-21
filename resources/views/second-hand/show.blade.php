@extends('layouts.app')
@section('title', $item->title)
@section('content')
<div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 py-6">
    <div class="rounded-2xl border bg-white p-4 sm:p-6 space-y-4">
        <div>
            <a href="{{ route('second-hand.index') }}" class="inline-flex items-center rounded-xl border px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">← 返回二手市集</a>
        </div>
        @if (session('status'))
            <div class="rounded-xl border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif
        <div class="flex items-center gap-2">
            <h1 class="text-2xl font-semibold">{{ $item->title }}</h1>
            @if($item->is_sold)
                <span class="rounded-full bg-gray-900 px-3 py-1 text-xs font-semibold text-white">已售出</span>
            @endif
        </div>
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
                @if(! $item->is_sold)
                    <div class="flex gap-2">
                        <form method="POST" action="{{ route('second-hand.sold', $item) }}">@csrf @method('PATCH')
                            <button class="rounded-xl bg-amber-500 px-4 py-2 text-white text-sm">標記已售出</button>
                        </form>
                        <form method="POST" action="{{ route('second-hand.destroy', $item) }}" onsubmit="return confirm('確定刪除？');">@csrf @method('DELETE')
                            <button class="rounded-xl bg-red-600 px-4 py-2 text-white text-sm">刪除商品</button>
                        </form>
                    </div>
                @else
                    <p class="text-sm text-gray-500">此商品已售出，為保留紀錄不可刪除。</p>
                @endif
            @endif
        @endauth
    </div>
</div>
@endsection
