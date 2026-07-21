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
        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-gray-600">
            <p>賣家：{{ $item->seller_display_name }}</p>
            <p>瀏覽次數：{{ number_format($item->view_count) }}</p>
        </div>
        @auth
            <p class="text-sm text-gray-600">聯絡方式：{{ $item->contact_type === 'phone' ? '手機' : '社群媒體' }} / {{ $item->contact_value }}</p>
        @else
            <p class="text-sm text-gray-500">聯絡方式：登入後可查看</p>
        @endauth
        @if($item->description)<p class="text-sm text-gray-700">{{ $item->description }}</p>@endif

        <div class="pt-1">
            <button id="share-btn" type="button" data-share-url="{{ $shareUrl }}" class="rounded-xl border px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">分享商品</button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            @foreach($item->photos as $photo)
                <button type="button" class="group" data-lightbox-src="{{ asset('storage/' . $photo->photo_path) }}" data-lightbox-alt="{{ $item->title }}">
                    <img src="{{ asset('storage/' . $photo->photo_path) }}" class="w-full h-56 object-cover rounded-xl group-hover:opacity-90 transition" alt="{{ $item->title }}">
                </button>
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

<div id="image-lightbox" class="fixed inset-0 z-50 hidden bg-black/80 p-4 sm:p-8">
    <div class="mx-auto flex h-full max-w-6xl flex-col">
        <div class="mb-3 flex items-center justify-end gap-2">
            <button id="zoom-out" type="button" class="rounded-lg bg-white/10 px-3 py-2 text-sm text-white hover:bg-white/20">－</button>
            <button id="zoom-in" type="button" class="rounded-lg bg-white/10 px-3 py-2 text-sm text-white hover:bg-white/20">＋</button>
            <button id="lightbox-close" type="button" class="rounded-lg bg-white/10 px-3 py-2 text-sm text-white hover:bg-white/20">關閉</button>
        </div>
        <div class="relative flex-1 overflow-auto rounded-xl bg-black/40">
            <img id="lightbox-image" src="" alt="" class="mx-auto my-6 max-w-none origin-center transition-transform duration-150">
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const lightbox = document.getElementById('image-lightbox');
        const lightboxImg = document.getElementById('lightbox-image');
        const closeBtn = document.getElementById('lightbox-close');
        const zoomInBtn = document.getElementById('zoom-in');
        const zoomOutBtn = document.getElementById('zoom-out');
        const triggers = document.querySelectorAll('[data-lightbox-src]');
        let zoom = 1;

        const applyZoom = () => {
            lightboxImg.style.transform = `scale(${zoom})`;
        };

        const openLightbox = (src, alt) => {
            zoom = 1;
            lightboxImg.src = src;
            lightboxImg.alt = alt || '';
            applyZoom();
            lightbox.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        };

        const closeLightbox = () => {
            lightbox.classList.add('hidden');
            lightboxImg.src = '';
            document.body.classList.remove('overflow-hidden');
        };

        triggers.forEach((el) => {
            el.addEventListener('click', () => openLightbox(el.dataset.lightboxSrc, el.dataset.lightboxAlt));
        });

        closeBtn.addEventListener('click', closeLightbox);
        lightbox.addEventListener('click', (e) => {
            if (e.target === lightbox) closeLightbox();
        });
        document.addEventListener('keydown', (e) => {
            if (!lightbox.classList.contains('hidden') && e.key === 'Escape') closeLightbox();
        });

        zoomInBtn.addEventListener('click', () => {
            zoom = Math.min(zoom + 0.2, 3);
            applyZoom();
        });
        zoomOutBtn.addEventListener('click', () => {
            zoom = Math.max(zoom - 0.2, 0.5);
            applyZoom();
        });

        const shareBtn = document.getElementById('share-btn');
        shareBtn?.addEventListener('click', async () => {
            const shareUrl = shareBtn.dataset.shareUrl;
            try {
                if (navigator.share) {
                    await navigator.share({ title: document.title, url: shareUrl });
                    return;
                }
            } catch (e) {}

            try {
                await navigator.clipboard.writeText(shareUrl);
                shareBtn.textContent = '連結已複製';
                setTimeout(() => shareBtn.textContent = '分享商品', 1500);
            } catch (e) {
                window.prompt('請複製連結', shareUrl);
            }
        });
    });
</script>
@endsection
