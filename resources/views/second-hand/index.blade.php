@extends('layouts.app')

@section('title', '二手市集')

@section('content')
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-5 space-y-4">
        <section class="rounded-2xl border bg-white px-4 py-3 sm:px-5 space-y-3">
            <div class="flex items-center justify-between gap-3">
                <h1 class="text-xl sm:text-2xl font-semibold tracking-tight">二手市集</h1>
                <span class="text-xs text-gray-500">共 {{ $items->total() }} 件</span>
            </div>

            <form method="GET" action="{{ route('second-hand.index') }}" class="flex items-center gap-2">
                <input
                    type="text"
                    name="q"
                    value="{{ $keyword ?? '' }}"
                    placeholder="搜尋標題或內文關鍵字"
                    class="w-full rounded-xl border px-3 py-2 text-sm"
                >
                <button type="submit" class="shrink-0 rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">搜尋</button>
            </form>

            @if (session('status'))
                <div class="rounded-xl border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                    {{ session('status') }}
                </div>
            @endif
        </section>

        <section>
            <div id="item-list" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @include('second-hand.partials.item-cards', ['items' => $items])
            </div>

            <div id="load-more-anchor" class="h-10"></div>
            <p id="loading-text" class="py-3 text-center text-sm text-gray-500 {{ $items->hasMorePages() ? '' : 'hidden' }}">載入中...</p>
            <p id="done-text" class="py-3 text-center text-sm text-gray-400 {{ $items->hasMorePages() ? 'hidden' : '' }}">已載入全部商品</p>
        </section>
    </div>

    @auth
        <a href="{{ route('second-hand.create') }}" class="fixed bottom-6 right-6 z-30 inline-flex h-14 w-14 items-center justify-center rounded-full bg-gray-900 text-3xl leading-none text-white shadow-lg hover:bg-gray-800" aria-label="新增商品" title="新增商品">+</a>
    @else
        <a href="{{ route('login.options') }}" class="fixed bottom-6 right-6 z-30 inline-flex h-14 w-14 items-center justify-center rounded-full bg-gray-900 text-3xl leading-none text-white shadow-lg hover:bg-gray-800" aria-label="登入後新增商品" title="登入後新增商品">+</a>
    @endauth

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            let nextPageUrl = @json($items->nextPageUrl());
            let loading = false;
            const anchor = document.getElementById('load-more-anchor');
            const list = document.getElementById('item-list');
            const loadingText = document.getElementById('loading-text');
            const doneText = document.getElementById('done-text');

            const observer = new IntersectionObserver(async (entries) => {
                if (!entries[0].isIntersecting || loading || !nextPageUrl) return;
                loading = true;
                try {
                    const res = await fetch(nextPageUrl, {headers: {'X-Requested-With': 'XMLHttpRequest'}});
                    const data = await res.json();
                    list.insertAdjacentHTML('beforeend', data.html);
                    nextPageUrl = data.next_page_url;
                    if (!nextPageUrl) {
                        loadingText.classList.add('hidden');
                        doneText.classList.remove('hidden');
                        observer.disconnect();
                    }
                } catch (e) {
                    loadingText.textContent = '載入失敗，請重新整理';
                } finally {
                    loading = false;
                }
            }, { rootMargin: '200px' });

            if (nextPageUrl) observer.observe(anchor);
        });
    </script>
@endsection
