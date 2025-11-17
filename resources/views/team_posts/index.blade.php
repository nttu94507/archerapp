@extends('layouts.app')

@section('title','組隊區')

@section('content')
    <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 py-8">

        {{-- Header --}}
        <div class="mb-4 flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-gray-900">賽事 組隊區</h1>
                <p class="text-sm text-gray-500 mt-1">瀏覽與發佈組隊資訊。</p>
            </div>
            @auth
                <a href="{{ route('team-posts.create') }}"
                   class="inline-flex items-center justify-center rounded-xl bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-600">
                    我要組隊
                </a>
            @endauth
        </div>

        {{-- List (卡片 Grid) --}}
        <div class="rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden">
            <div class="max-h-[70vh] overflow-auto p-4">
                @if($posts->count())
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach($posts as $post)
                            @php
                                $showUrl = route('team-posts.show', $post);
                            @endphp

                            <div
                                class="js-row-link flex flex-col rounded-2xl border border-gray-200 bg-white p-4 hover:shadow-md transition cursor-pointer"
                                data-href="{{ $showUrl }}"
                                role="link"
                                tabindex="0"
                                aria-label="檢視組隊貼文：{{ $post->title }}"
                            >
                                {{-- 標題與時間 --}}
                                <div>
                                    <h2 class="text-base sm:text-lg font-semibold text-gray-900 line-clamp-2">
                                        {{ $post->title }}
                                    </h2>
{{--                                    <p class="mt-1 text-xs text-gray-500">--}}
{{--                                        發佈於 {{ $post->created_at->format('Y-m-d H:i') }}--}}
{{--                                        @if($post->relationLoaded('user') || isset($post->user))--}}
{{--                                            ・ 由 {{ $post->user->name ?? '匿名' }} 發佈--}}
{{--                                        @endif--}}
{{--                                    </p>--}}
                                </div>

                                {{-- 內文摘要 --}}
                                <div class="mt-3 flex-1">
                                    <p class="text-sm text-gray-700 line-clamp-3">
                                        {{ \Illuminate\Support\Str::limit($post->content, 120) }}
                                    </p>
                                </div>

                                {{-- Footer：查看詳細 --}}
{{--                                <div class="mt-4 flex items-center justify-between">--}}
{{--                                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-xs text-gray-600">--}}
{{--                                        組隊貼文--}}
{{--                                    </span>--}}
{{--                                    <span class="text-xs font-medium text-indigo-600">--}}
{{--                                        查看詳細 &rarr;--}}
{{--                                    </span>--}}
{{--                                </div>--}}
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="px-4 py-12">
                        <div class="flex flex-col items-center justify-center text-center">
                            <div class="mb-3 rounded-2xl bg-gray-100 p-3">🤝</div>
                            <p class="text-gray-900 font-medium">目前還沒有組隊貼文</p>
                            <p class="text-gray-500 text-sm mt-1">可以先發一篇，揪揪看有沒有同好一起玩。</p>
                            @auth
                                <a
                                    href="{{ route('team-posts.create') }}"
                                    class="mt-3 inline-flex items-center justify-center rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500"
                                >
                                    發佈第一篇組隊貼文
                                </a>
                            @endauth
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Pagination --}}
        @if($posts->count())
            <div class="mt-4 flex items-center justify-between">
                <p class="text-xs text-gray-500">
                    第 {{ $posts->firstItem() }} - {{ $posts->lastItem() }} 筆，共 {{ $posts->total() }} 筆
                </p>
                <div class="hidden sm:block">
                    {{ $posts->onEachSide(1)->links() }}
                </div>
                <div class="sm:hidden">
                    {{ $posts->onEachSide(0)->links() }}
                </div>
            </div>
        @endif
    </div>

    {{-- 整卡可點導頁（沿用訓練紀錄的互動方式） --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const isInteractive = el =>
                el.closest('a, button, input, select, textarea, label, [role="button"], [contenteditable="true"]');

            // 點擊整個卡片導頁
            document.addEventListener('click', function (e) {
                const card = e.target.closest('.js-row-link');
                if (!card) return;
                if (isInteractive(e.target)) return;

                const sel = window.getSelection && window.getSelection().toString();
                if (sel) return; // 避免選字後誤觸

                const href = card.getAttribute('data-href');
                if (href) window.location.assign(href);
            }, { passive: true });

            // 鍵盤 Enter / Space 也能進入
            document.addEventListener('keydown', function (e) {
                if (e.key !== 'Enter' && e.key !== ' ') return;
                const card = e.target.closest('.js-row-link');
                if (!card) return;
                if (isInteractive(e.target)) return;
                e.preventDefault();
                const href = card.getAttribute('data-href');
                if (href) window.location.assign(href);
            });
        });
    </script>
@endsection
