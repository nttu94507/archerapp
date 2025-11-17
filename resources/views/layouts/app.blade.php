{{-- resources/views/layouts/app.blade.php (updated) --}}
    <!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'Archery Leaderboard')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        // 可選：自訂主題/色票/斷點
        tailwind.config = {
            theme: {
                extend: {
                    colors: {brand: '#4f46e5'}
                }
            }
        }
    </script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    {{-- @vite(['resources/css/app.css', 'resources/js/app.js']) --}}
</head>
<body class="bg-gray-100 text-gray-900">

<div id="modal-root"></div>

<header class="bg-white border-b sticky top-0 z-40">
    <div class="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between gap-4">

        {{-- 左邊 Logo （點擊回首頁）--}}
        <a href="{{ url('/') }}"
           class="text-xl font-bold shrink-0 hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-brand rounded-lg">
            🏹 ArrowTrack
        </a>

        {{-- 中間主選單（桌機顯示） --}}
        <nav class="hidden md:flex items-center gap-6 text-sm font-medium">
            <a href="{{ route('scores.index') }}"
               class="px-2 py-1 rounded-lg hover:bg-gray-100 {{ request()->routeIs('scores.*') ? 'text-gray-900' : 'text-gray-600' }}">
                訓練紀錄
            </a>
            {{--            <a href="{{ route('leaderboards.index') }}"--}}
            {{--               class="px-2 py-1 rounded-lg hover:bg-gray-100 {{ request()->routeIs('leaderboards.*') ? 'text-gray-900' : 'text-gray-600' }}">--}}
            {{--                排行榜--}}
            {{--            </a>--}}
            {{--            <a href="{{ route('events.index') }}"--}}
            {{--               class="px-2 py-1 rounded-lg hover:bg-gray-100 {{ request()->routeIs('events.*') ? 'text-gray-900' : 'text-gray-600' }}">--}}
            {{--                賽事--}}
            {{--            </a>--}}
            <a href="{{ route('team-posts.index') }}"
               class="px-2 py-1 rounded-lg hover:bg-gray-100 {{ request()->routeIs('events.*') ? 'text-gray-900' : 'text-gray-600' }}">
                組隊區
            </a>

        </nav>

        <div class="flex items-center gap-3">
            {{-- 手機版漢堡按鈕：打開側邊欄 --}}
            <button id="mobile-menu-button"
                    class="md:hidden inline-flex items-center justify-center rounded-xl bg-gray-100 w-10 h-10 hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-brand"
                    aria-controls="mobile-drawer" aria-expanded="false" aria-label="開啟主選單">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>

            {{-- 右邊導覽區（桌機使用者） --}}
            <nav class="hidden md:flex items-center gap-2">
                @auth
                    <div class="relative">
                        <button id="user-menu-button"
                                class="flex items-center gap-2 rounded-xl bg-gray-100 px-3 py-2 text-sm font-medium hover:bg-gray-200"
                                aria-haspopup="true" aria-expanded="false">
                            {{ auth()->user()->name }}
                            <svg class="h-4 w-4 text-gray-500" viewBox="0 0 20 20" fill="currentColor"
                                 aria-hidden="true">
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
</header>

{{-- 手機版：側邊欄 + 背景遮罩 --}}
<div id="backdrop" class="fixed inset-0 bg-black/40 hidden z-40"></div>
<aside id="mobile-drawer"
       class="fixed inset-y-0 left-0 w-72 bg-white border-r shadow-xl transform -translate-x-full transition-transform duration-200 ease-out z-50 md:hidden"
       aria-hidden="true" aria-label="手機主選單">
    <div class="h-full flex flex-col">
        <div class="flex items-center justify-between px-4 h-14 border-b">
            <span class="font-semibold">選單</span>
            <button id="drawer-close" class="inline-flex items-center justify-center rounded-lg p-2 hover:bg-gray-100"
                    aria-label="關閉選單">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <nav class="p-3 space-y-1 text-sm">
            {{-- 主要導覽 --}}
            <a href="{{ route('scores.index') }}"
               class="flex items-center gap-2 rounded-lg px-3 py-2 hover:bg-gray-50 {{ request()->routeIs('scores.*') ? 'font-semibold text-gray-900' : 'text-gray-700' }}">
                訓練紀錄
            </a>
            {{--            <a href="{{ route('leaderboards.index') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 hover:bg-gray-50 {{ request()->routeIs('leaderboards.*') ? 'font-semibold text-gray-900' : 'text-gray-700' }}">--}}
            {{--                排行榜--}}
            {{--            </a>--}}
            {{--            <a href="{{ route('events.index') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 hover:bg-gray-50 {{ request()->routeIs('events.*') ? 'font-semibold text-gray-900' : 'text-gray-700' }}">--}}
            {{--                賽事--}}
            {{--            </a>--}}
            <a href="{{ route('team-posts.index') }}"
               class="flex items-center gap-2 rounded-lg px-3 py-2 hover:bg-gray-50 {{ request()->routeIs('events.*') ? 'font-semibold text-gray-900' : 'text-gray-700' }}">
                組隊區
            </a>
        </nav>

        <div class="mt-auto border-t p-3">
            {{-- 使用者區塊 --}}
            @auth
                <div class="px-3 py-2 text-xs text-gray-500">使用者</div>
                <div class="px-3 py-2 font-medium">{{ auth()->user()->name }}</div>
                <a href="{{ route('user.profile.completion') }}"
                   class="block rounded-lg px-3 py-2 text-sm hover:bg-gray-50 text-gray-700">個人資料</a>
                <form method="POST" action="{{ route('logout') }}" class="mt-1">
                    @csrf
                    <button type="submit"
                            class="w-full text-left rounded-lg px-3 py-2 text-sm text-red-600 hover:bg-red-50">登出
                    </button>
                </form>
            @endauth
            @guest
                <a href="{{ route('login.options') }}"
                   class="block rounded-lg px-3 py-2 text-sm text-white bg-gray-900 text-center hover:bg-gray-800">登入</a>
            @endguest
        </div>
    </div>
</aside>

<main class="min-h-[60vh]">
    @yield('content')
</main>

<footer class="mt-10 py-6 text-center text-sm text-gray-500">
    &copy; {{ date('Y') }} Arrow Track
</footer>

{{-- 下拉選單（桌機）與 側邊欄（手機）控制腳本 --}}
<script>
    document.addEventListener('DOMContentLoaded', () => {
        // 桌機使用者選單
        const btn = document.getElementById('user-menu-button');
        const menu = document.getElementById('user-menu');
        if (btn && menu) {
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
        }

        // 手機側邊欄
        const drawer = document.getElementById('mobile-drawer');
        const openBtn = document.getElementById('mobile-menu-button');
        const closeBtn = document.getElementById('drawer-close');
        const backdrop = document.getElementById('backdrop');

        function openDrawer() {
            drawer.classList.remove('-translate-x-full');
            drawer.setAttribute('aria-hidden', 'false');
            backdrop.classList.remove('hidden');
            openBtn?.setAttribute('aria-expanded', 'true');
            // 鎖卷動（可選）
            document.body.classList.add('overflow-hidden');
        }

        function closeDrawer() {
            drawer.classList.add('-translate-x-full');
            drawer.setAttribute('aria-hidden', 'true');
            backdrop.classList.add('hidden');
            openBtn?.setAttribute('aria-expanded', 'false');
            document.body.classList.remove('overflow-hidden');
        }

        openBtn?.addEventListener('click', openDrawer);
        closeBtn?.addEventListener('click', closeDrawer);
        backdrop?.addEventListener('click', closeDrawer);
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeDrawer();
        });

        // 互動後自動關閉（例如點了連結）
        drawer.querySelectorAll('a, button[type="submit"]').forEach(el => {
            el.addEventListener('click', () => {
                // 讓表單先送出或連結跳轉，再關閉抽屜
                setTimeout(closeDrawer, 50);
            });
        });
    });
</script>
@yield('js')

</body>
</html>
