@extends('layouts.app')

@section('title', '選擇登入方式')

@section('content')
    <div class="max-w-md mx-auto mt-12 bg-white p-6 rounded-lg shadow">
        <h2 class="text-xl font-bold mb-6 text-center">選擇登入方式</h2>

        <div class="space-y-4">
            {{-- Google 登入 --}}
            <a href="{{ route('login.google.redirect') }}"
               class="w-full flex items-center justify-center gap-3 px-4 py-3 border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50 transition">
                <svg class="w-5 h-5" viewBox="0 0 533.5 544.3">
                    <path d="M533.5 278.4c0-17.4-1.6-34.1-4.7-50.3H272v95.1h147.5c-6.4 34.6-25.8 63.9-55.2 83.5l89.2 69.3c52.2-48.1 80-119 80-197.6z" fill="#4285F4"/>
                    <path d="M272 544.3c73.9 0 135.9-24.4 181.2-66.2l-89.2-69.3c-24.8 16.7-56.4 26.6-92 26.6-70.7 0-130.7-47.7-152.1-111.4H27.6v70.1C72.9 474.3 166.5 544.3 272 544.3z" fill="#34A853"/>
                    <path d="M119.9 323.9c-10.8-32.6-10.8-67.7 0-100.3V153.5H27.6c-39.6 79.1-39.6 173.5 0 252.6l92.3-70.2z" fill="#FBBC05"/>
                    <path d="M272 107.7c39.9 0 75.7 13.7 103.9 40.5l77.8-77.8C407.9 24.1 345.9 0 272 0 166.5 0 72.9 70 27.6 153.5l92.3 70.1C141.3 155.4 201.3 107.7 272 107.7z" fill="#EA4335"/>
                </svg>
                <span>使用 Google 登入</span>
            </a>

            {{-- 預留其他登入方式（未來） --}}
            <button class="w-full flex items-center justify-center px-4 py-3 border border-gray-300 rounded-lg text-gray-400 cursor-not-allowed">
                Facebook（即將推出）
            </button>
            <button class="w-full flex items-center justify-center px-4 py-3 border border-gray-300 rounded-lg text-gray-400 cursor-not-allowed">
                Apple（即將推出）
            </button>
        </div>
    </div>
@endsection
