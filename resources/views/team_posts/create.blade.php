@extends('layouts.app')

@section('title','發佈組隊貼文')

@section('content')
    <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 py-8">

        {{-- Header --}}
        <div class="mb-4 flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-gray-900">發佈組隊貼文</h1>
                <p class="text-sm text-gray-500 mt-1">
                    填寫標題、內文與聯繫方式，讓其他人可以找到並加入你的隊伍。
                </p>
            </div>

            <a href="{{ route('team-posts.index') }}"
               class="inline-flex items-center justify-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                返回列表
            </a>
        </div>

        {{-- Card --}}
        <div class="rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="p-4 sm:p-6">

                {{-- Errors --}}
                @if ($errors->any())
                    <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        <p class="font-medium mb-1">請檢查以下欄位：</p>
                        <ul class="list-disc list-inside space-y-0.5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('team-posts.store') }}" class="space-y-5">
                    @csrf

                    {{-- 標題 --}}
                    <div>
                        <label for="title" class="block text-xs font-medium text-gray-600 mb-1">
                            標題 <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            id="title"
                            name="title"
                            value="{{ old('title') }}"
                            placeholder="例如: [114椰林盃] 30公尺組團體缺 1 人"
                            class="block w-full rounded-xl border border-gray-300 bg-gray-50 px-3 py-2.5 text-sm
                                   focus:border-indigo-500 focus:ring-indigo-500 @error('title') border-red-400 bg-red-50 @enderror"
                            required
                        >
                    </div>

                    {{-- 內文 --}}
                    <div>
                        <label for="content" class="block text-xs font-medium text-gray-600 mb-1">
                            內文 <span class="text-red-500">*</span>
                        </label>
                        <textarea
                            id="content"
                            name="content"
                            rows="6"
                            placeholder="說明你的隊伍名稱資訊、聯繫窗口:陳先生、徵求人數等等。"
                            class="block w-full rounded-xl border border-gray-300 bg-gray-50 px-3 py-2.5 text-sm
                                   focus:border-indigo-500 focus:ring-indigo-500 resize-y min-h-[150px]
                                   @error('content') border-red-400 bg-red-50 @enderror"
                            required
                        >{{ old('content') }}</textarea>
                        <p class="mt-1 text-xs text-gray-400">
                            小提醒：請不要在內文留下太多個資，例如完整住址。
                        </p>
                    </div>

                    {{-- 聯繫方式 --}}
                    <div>
                        <label for="contact" class="block text-xs font-medium text-gray-600 mb-1">
                            聯繫方式 <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            id="contact"
                            name="contact"
                            value="{{ old('contact') }}"
                            placeholder="Line：archery_123 / IG：@myarchery / Email：xxx@example.com"
                            class="block w-full rounded-xl border border-gray-300 bg-gray-50 px-3 py-2.5 text-sm
                                   focus:border-indigo-500 focus:ring-indigo-500 @error('contact') border-red-400 bg-red-50 @enderror"
                            required
                        >
                        <p class="mt-1 text-xs text-gray-400">
                            建議使用 Line / IG / Email 等聯繫方式，避免直接公開手機號碼。
                        </p>
                    </div>

                    {{-- Action buttons --}}
                    <div class="pt-2 flex items-center justify-end gap-2">
                        <a href="{{ route('team-posts.index') }}"
                           class="inline-flex items-center justify-center rounded-xl border border-gray-300 px-4 py-2 text-xs sm:text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                            取消
                        </a>
                        <button
                            type="submit"
                            class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-4 py-2 text-xs sm:text-sm font-medium text-white hover:bg-indigo-500"
                        >
                            發佈貼文
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
