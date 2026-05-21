@extends('layouts.app')

@section('title', '二手市集')

@section('content')
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-5 space-y-4">
        <section class="rounded-2xl border bg-white px-4 py-3 sm:px-5">
            <div class="flex items-center justify-between gap-3">
                <h1 class="text-xl sm:text-2xl font-semibold tracking-tight">二手市集</h1>
                <span class="text-xs text-gray-500">共 {{ $items->count() }} 件</span>
            </div>

            @if (session('status'))
                <div class="mt-3 rounded-xl border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                    {{ session('status') }}
                </div>
            @endif
        </section>

        <section>
            <div class="flex gap-4 overflow-x-auto pb-3 snap-x snap-mandatory md:grid md:grid-cols-3 lg:grid-cols-4 md:overflow-visible">
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

    @auth
        <a href="{{ route('second-hand.create') }}"
           class="fixed bottom-6 right-6 z-30 inline-flex h-14 w-14 items-center justify-center rounded-full bg-gray-900 text-3xl leading-none text-white shadow-lg hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-900"
           aria-label="新增商品"
           title="新增商品">
            +
        </a>
    @else
        <a href="{{ route('login.options') }}"
           class="fixed bottom-6 right-6 z-30 inline-flex h-14 w-14 items-center justify-center rounded-full bg-gray-900 text-3xl leading-none text-white shadow-lg hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-900"
           aria-label="登入後新增商品"
           title="登入後新增商品">
            +
        </a>
    @endauth
@endsection
