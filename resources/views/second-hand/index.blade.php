@extends('layouts.app')

@section('title', 'ArrowTrack 二手市集')

@section('content')
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8 space-y-8">
        <section class="rounded-2xl border bg-white p-5 sm:p-6">
            <h1 class="text-2xl font-semibold tracking-tight">二手器材市集</h1>
            <p class="mt-2 text-sm text-gray-600">把你的閒置器材分享給其他射友，讓好裝備繼續發光。</p>

            @if (session('status'))
                <div class="mt-4 rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-2 text-sm text-emerald-800">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mt-4 rounded-xl border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <ul class="list-disc pl-5 space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('second-hand.store') }}" method="POST" enctype="multipart/form-data" class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                @csrf
                <label class="space-y-1">
                    <span class="text-sm text-gray-700">商品名稱</span>
                    <input name="title" value="{{ old('title') }}" required class="w-full rounded-xl border px-3 py-2 text-sm" placeholder="例如：WNS 鋁鎂弓把 25 吋">
                </label>
                <label class="space-y-1">
                    <span class="text-sm text-gray-700">售價（NT$）</span>
                    <input type="number" min="0" name="price" value="{{ old('price') }}" required class="w-full rounded-xl border px-3 py-2 text-sm" placeholder="3200">
                </label>
                <label class="space-y-1">
                    <span class="text-sm text-gray-700">販售者暱稱</span>
                    <input name="seller_nickname" value="{{ old('seller_nickname', auth()->user()?->display_name) }}" class="w-full rounded-xl border px-3 py-2 text-sm" placeholder="你的暱稱">
                </label>
                <label class="space-y-1">
                    <span class="text-sm text-gray-700">商品照片</span>
                    <input type="file" name="photo" accept="image/*" required class="w-full rounded-xl border px-3 py-2 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-gray-900 file:px-3 file:py-1 file:text-white">
                </label>
                <label class="md:col-span-2 space-y-1">
                    <span class="text-sm text-gray-700">補充說明（選填）</span>
                    <textarea name="description" rows="3" class="w-full rounded-xl border px-3 py-2 text-sm" placeholder="例如：九成新、右手弓、含弦與箭台">{{ old('description') }}</textarea>
                </label>
                <div class="md:col-span-2">
                    <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-gray-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-gray-800">上架商品</button>
                </div>
            </form>
        </section>

        <section>
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-lg font-semibold">商品總覽</h2>
                <span class="text-xs text-gray-500">共 {{ $items->count() }} 件</span>
            </div>

            <div class="flex gap-4 overflow-x-auto pb-2 snap-x snap-mandatory md:grid md:grid-cols-3 lg:grid-cols-4 md:overflow-visible">
                @forelse ($items as $item)
                    <article class="min-w-[78%] sm:min-w-[48%] md:min-w-0 snap-start rounded-2xl border bg-white shadow-sm overflow-hidden">
                        <img src="{{ asset('storage/' . $item->photo_path) }}" alt="{{ $item->title }}" class="h-48 w-full object-cover">
                        <div class="p-4">
                            <h3 class="font-semibold text-gray-900">{{ $item->title }}</h3>
                            <div class="mt-3 flex items-center justify-between">
                                <p class="text-lg font-bold">NT$ {{ number_format($item->price) }}</p>
                                <p class="text-sm text-gray-600">{{ $item->seller_nickname }}</p>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="rounded-xl border border-dashed bg-white p-8 text-center text-sm text-gray-500 md:col-span-3 lg:col-span-4">
                        還沒有商品，快來上架第一件！
                    </div>
                @endforelse
            </div>
        </section>
    </div>
@endsection
