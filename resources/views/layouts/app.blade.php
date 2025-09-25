{{-- resources/views/layouts/app.blade.php --}}
    <!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'Archery Leaderboard')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 text-gray-900">

<header class="bg-white border-b">
    <div class="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between gap-4">

        {{-- 左邊 Logo --}}
        <h1 class="text-xl font-bold shrink-0">🏹 ArrowTrack</h1>

        {{-- 中間主選單（桌機顯示） --}}
        <nav class="hidden md:flex items-center gap-6 text-sm font-medium">
{{--            <a href="{{ route('home') }}"--}}
{{--               class="px-2 py-1 rounded-lg hover:bg-gray-100 {{ request()->routeIs('home') ? 'text-gray-900' : 'text-gray-600' }}">--}}
{{--                首頁--}}
{{--            </a>--}}
            <a href="{{ route('leaderboards.index') }}"
               class="px-2 py-1 rounded-lg hover:bg-gray-100 {{ request()->routeIs('leaderboards.*') ? 'text-gray-900' : 'text-gray-600' }}">
                排行榜
            </a>
            <a href="{{ route('events.index') }}"
               class="px-2 py-1 rounded-lg hover:bg-gray-100 {{ request()->routeIs('events.*') ? 'text-gray-900' : 'text-gray-600' }}">
                賽事
            </a>
{{--            <a href="{{ route('clubs.index') }}"--}}
{{--               class="px-2 py-1 rounded-lg hover:bg-gray-100 {{ request()->routeIs('clubs.*') ? 'text-gray-900' : 'text-gray-600' }}">--}}
{{--                射箭場所--}}
{{--            </a>--}}
{{--            <a href="{{ route('articles.index') }}"--}}
{{--               class="px-2 py-1 rounded-lg hover:bg-gray-100 {{ request()->routeIs('articles.*') ? 'text-gray-900' : 'text-gray-600' }}">--}}
{{--                文章--}}
{{--            </a>--}}
        </nav>

        <div class="flex items-center gap-3">
            {{-- 手機版漢堡按鈕（中間選單用） --}}
            <button id="mobile-menu-button"
                    class="md:hidden inline-flex items-center justify-center rounded-xl bg-gray-100 w-10 h-10 hover:bg-gray-200"
                    aria-controls="mobile-menu" aria-expanded="false">
                <span class="sr-only">開啟主選單</span>
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>

            {{-- 右邊導覽區（使用者） --}}
            <nav class="flex items-center gap-2">
                @auth
                    <div class="relative">
                        <button id="user-menu-button"
                                class="flex items-center gap-2 rounded-xl bg-gray-100 px-3 py-2 text-sm font-medium hover:bg-gray-200"
                                aria-haspopup="true" aria-expanded="false">
                            {{ auth()->user()->name }}
                            <svg class="h-4 w-4 text-gray-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd"
                                      d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.24a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z"
                                      clip-rule="evenodd"/>
                            </svg>
                        </button>

                        <div id="user-menu"
                             class="hidden absolute right-0 mt-2 w-48 rounded-xl border bg-white py-2 shadow-lg"
                             role="menu" aria-labelledby="user-menu-button">
                            <a href="{{ route('user.profile.completion') }}"
                               class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50" role="menuitem">
                                個人資料
                            </a>
                            <form method="POST" action="{{ route('logout') }}" class="mt-1" role="none">
                                @csrf
                                <button type="submit"
                                        class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50"
                                        role="menuitem">
                                    登出
                                </button>
                            </form>
                        </div>
                    </div>
                @endauth
                @guest
                    <a href="{{ route('login.options') }}"
                       class="inline-flex items-center justify-center rounded-xl bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800">
                        登入
                    </a>
                @endguest
            </nav>
        </div>
    </div>

    {{-- 手機版主選單面板 --}}
    <div id="mobile-menu" class="md:hidden hidden border-t bg-white">
        <div class="max-w-7xl mx-auto px-4 py-3 grid gap-2">
{{--            <a href="{{ route('leaderboards.index') }}"--}}
{{--               class="block rounded-lg px-3 py-2 text-sm hover:bg-gray-50 {{ request()->routeIs('leaderboards.index') ? 'font-semibold text-gray-900' : 'text-gray-700' }}">--}}
{{--                首頁--}}
{{--            </a>--}}
            <a href="{{ route('leaderboards.index') }}"
               class="block rounded-lg px-3 py-2 text-sm hover:bg-gray-50 {{ request()->routeIs('leaderboards.*') ? 'font-semibold text-gray-900' : 'text-gray-700' }}">
                排行榜
            </a>
            <a href="{{ route('events.index') }}"
               class="block rounded-lg px-3 py-2 text-sm hover:bg-gray-50 {{ request()->routeIs('events.*') ? 'font-semibold text-gray-900' : 'text-gray-700' }}">
                賽事
            </a>
{{--            <a href="{{ route('clubs.index') }}"--}}
{{--               class="block rounded-lg px-3 py-2 text-sm hover:bg-gray-50 {{ request()->routeIs('clubs.*') ? 'font-semibold text-gray-900' : 'text-gray-700' }}">--}}
{{--                射箭場所--}}
{{--            </a>--}}
{{--            <a href="{{ route('articles.index') }}"--}}
{{--               class="block rounded-lg px-3 py-2 text-sm hover:bg-gray-50 {{ request()->routeIs('articles.*') ? 'font-semibold text-gray-900' : 'text-gray-700' }}">--}}
{{--                文章--}}
{{--            </a>--}}
        </div>
    </div>
</header>


<main>
    @yield('content')
</main>

<footer class="mt-10 py-6 text-center text-sm text-gray-500">
    &copy; {{ date('Y') }} Archery Leaderboard Demo
</footer>

</body>
</html>

{{--<script>--}}
{{--    document.addEventListener('click', function (e) {--}}
{{--        const dropdown = document.getElementById('user-menu');--}}
{{--        if (!dropdown) return;--}}

{{--        const button = document.getElementById('user-menu-button');--}}
{{--        if (button.contains(e.target)) {--}}
{{--            dropdown.classList.toggle('hidden');--}}
{{--        } else if (!dropdown.contains(e.target)) {--}}
{{--            dropdown.classList.add('hidden');--}}
{{--        }--}}
{{--    });--}}
{{--</script>--}}
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const btn = document.getElementById('user-menu-button');
        const menu = document.getElementById('user-menu');
        if (!btn || !menu) return;

        function closeMenu() {
            menu.classList.add('hidden');
            btn.setAttribute('aria-expanded', 'false');
        }
        function toggleMenu() {
            menu.classList.toggle('hidden');
            btn.setAttribute('aria-expanded', menu.classList.contains('hidden') ? 'false' : 'true');
        }

        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            toggleMenu();
        });

        document.addEventListener('click', (e) => {
            if (!menu.contains(e.target)) closeMenu();
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeMenu();
        });
    });
</script>
