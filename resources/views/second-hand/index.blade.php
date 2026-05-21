@extends('layouts.app')

@section('title', 'ArrowTrack 二手市集')

@section('content')
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <section class="rounded-2xl border bg-white p-5 sm:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">二手器材市集</h1>
                    <p class="mt-2 text-sm text-gray-600">瀏覽所有二手器材，找到適合你的裝備。</p>
                </div>
                @auth
                    <a href="{{ route('second-hand.create') }}" class="inline-flex items-center justify-center rounded-xl bg-gray-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-gray-800">新增商品</a>
                @else
                    <a href="{{ route('login.options') }}" class="inline-flex items-center justify-center rounded-xl bg-gray-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-gray-800">登入後新增商品</a>
                @endauth
            </div>
            @if (session('status'))
                <div class="mt-4 rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-2 text-sm text-emerald-800">
                    {{ session('status') }}
                </div>
            @endif
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
                        <div class="p-4 space-y-2">
                            <h3 class="font-semibold text-gray-900">{{ $item->title }}</h3>
                            <div class="flex items-center justify-between">
                                <p class="text-lg font-bold">NT$ {{ number_format($item->price) }}</p>
                                <p class="text-sm text-gray-600">{{ $item->seller_nickname }}</p>
                            </div>
                            <p class="text-xs text-gray-500">聯絡方式：{{ $item->contact_type === 'phone' ? '手機' : '社群媒體' }} / {{ $item->contact_value }}</p>
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
