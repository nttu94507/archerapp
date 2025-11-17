@extends('layouts.app')

@section('title','組隊貼文')

@section('content')
    <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 py-8">

        {{-- Header --}}
        <div class="mb-4 flex items-center justify-between gap-4">
            <div class="flex items-center gap-2">
                <a href="{{ route('team-posts.index') }}"
                   class="inline-flex items-center justify-center rounded-xl border border-gray-300 bg-white px-3 py-1.5 text-xs sm:text-sm font-medium text-gray-700 hover:bg-gray-50">
                    &laquo; 返回列表
                </a>
            </div>
        </div>

        {{-- Card --}}
        <div class="rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="p-4 sm:p-6 space-y-4">
                {{-- 標題與時間 --}}
                <div>
                    <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-gray-900">
                        {{ $teamPost->title }}
                    </h1>
                    <p class="mt-2 text-xs text-gray-500">
                        發佈於 {{ $teamPost->created_at->format('Y-m-d H:i') }}
{{--                        @if($teamPost->relationLoaded('user') || isset($teamPost->user))--}}
{{--                            ・ 由 {{ $teamPost->user->name ?? '匿名' }} 發佈--}}
{{--                        @endif--}}
                    </p>
                </div>

                {{-- 聯繫方式 --}}
                <div class="rounded-xl bg-indigo-50 px-4 py-3">
                    <div class="flex items-start gap-2">
                        <span class="mt-0.5 inline-flex items-center rounded-full bg-indigo-600 px-2 py-0.5 text-[10px] font-medium text-white">
                            聯繫方式
                        </span>
                        <p class="text-sm text-gray-800 break-all">
                            {{ $teamPost->contact }}
                        </p>
                    </div>
                    <p class="mt-1 text-[11px] text-indigo-700/80">
                        請留意自身資訊安全，與對方聯絡時可先使用公開社群帳號或一時性聯絡方式。
                    </p>
                </div>

                {{-- 內文 --}}
                <div class="pt-2 border-t border-gray-100">
                    <h2 class="mb-2 text-xs font-semibold tracking-wide text-gray-500">
                        組隊說明
                    </h2>
                    <div class="prose prose-sm max-w-none text-gray-800 whitespace-pre-wrap">
                        {{ $teamPost->content }}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
