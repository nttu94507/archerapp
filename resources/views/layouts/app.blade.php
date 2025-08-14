{{-- resources/views/layouts/app.blade.php --}}
    <!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'Archery Leaderboard')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @vite(['resources/css/app.css', 'resources/js/app.js']) {{-- 確保 Tailwind 有編譯 --}}
</head>
<body class="bg-gray-100 text-gray-900">
{{-- 頁首區塊 --}}
<header class="bg-white border-b">
    <div class="max-w-7xl mx-auto px-4 py-4">
        <h1 class="text-xl font-bold">🏹 ArrowTrack</h1>
    </div>
</header>

{{-- 內容 --}}
<main>
    @yield('content')
</main>

{{-- 頁尾區塊 --}}
<footer class="mt-10 py-6 text-center text-sm text-gray-500">
    &copy; {{ date('Y') }} Archery Leaderboard Demo
</footer>
</body>
</html>
