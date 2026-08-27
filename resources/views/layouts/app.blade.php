{{-- resources/views/layouts/app.blade.php (updated) --}}
    <!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'Archery Leaderboard')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
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
    <style>
        html {
            overflow-x: hidden;
            -webkit-text-size-adjust: 100%;
        }

        body {
            min-width: 0;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* iPad 直向與橫向：保留精簡 Header，完整導覽改用可收合側邊欄。 */
        @media (min-width: 768px) and (max-width: 1366px) and (any-pointer: coarse) {
            body {
                min-height: 100dvh;
            }

            #primary-navigation,
            #desktop-account-navigation {
                display: none !important;
            }

            #mobile-menu-button {
                display: inline-flex !important;
            }

            #mobile-drawer {
                display: block !important;
                width: min(22rem, 82vw);
            }

            #header-inner {
                max-width: none;
                padding-left: max(1.25rem, env(safe-area-inset-left));
                padding-right: max(1.25rem, env(safe-area-inset-right));
                padding-top: 0.625rem;
                padding-bottom: 0.625rem;
            }

            #mobile-drawer {
                padding-left: env(safe-area-inset-left);
            }

            main {
                width: 100%;
                min-width: 0;
            }

            main :where(input:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]), select, textarea, button, summary) {
                min-height: 44px !important;
            }

            main :where(input:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]), select, textarea) {
                font-size: 16px !important;
            }

            main :where(.overflow-x-auto, .overflow-auto) {
                max-width: 100%;
                overscroll-behavior-inline: contain;
                -webkit-overflow-scrolling: touch;
            }

            main table {
                white-space: nowrap;
            }

            main :where(th, td) {
                padding-top: 0.75rem;
                padding-bottom: 0.75rem;
            }

            main :where(.max-w-3xl, .max-w-4xl, .max-w-5xl, .max-w-6xl, .max-w-7xl) {
                width: 100%;
            }

            footer {
                padding-bottom: max(1.5rem, env(safe-area-inset-bottom));
            }
        }

        /* iPad 直向：避免桌機型多欄表單與資訊卡被壓得太窄。 */
        @media (min-width: 768px) and (max-width: 1023px) and (any-pointer: coarse) {
            main .md\:grid-cols-3,
            main .md\:grid-cols-4,
            main .md\:grid-cols-5,
            main .md\:grid-cols-7 {
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            }

            main .md\:grid-cols-6 {
                grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
            }

            main .md\:col-span-3,
            main .md\:col-span-4,
            main .md\:col-span-5,
            main .md\:col-span-6,
            main .md\:col-span-7 {
                grid-column: 1 / -1 !important;
            }
        }

        /* iPad 橫向：桌機四欄以上的區塊維持可閱讀的兩至三欄。 */
        @media (min-width: 1024px) and (max-width: 1180px) and (any-pointer: coarse) {
            main .lg\:grid-cols-3,
            main .lg\:grid-cols-4,
            main .lg\:grid-cols-5,
            main .lg\:grid-cols-6 {
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            }

            main .xl\:grid-cols-4,
            main .xl\:grid-cols-5,
            main .xl\:grid-cols-6 {
                grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
            }
        }
    </style>
    {{-- @vite(['resources/css/app.css', 'resources/js/app.js']) --}}
</head>
<body class="bg-gray-100 text-gray-900">

<div id="modal-root"></div>

<header class="bg-white border-b sticky top-0 z-40">
    <div id="header-inner" class="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between gap-4">

        {{-- 左邊 Logo （點擊回首頁）--}}
        <a href="{{ url('/') }}"
           class="text-xl font-bold shrink-0 hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-brand rounded-lg">
            🏹 ArrowTrack
        </a>

        {{-- 中間主選單（桌機顯示） --}}
        <nav id="primary-navigation" class="hidden md:flex items-center gap-6 text-sm font-medium">
            <a href="{{ route('events.index') }}" class="px-2 py-1 rounded-lg hover:bg-gray-100 {{ request()->routeIs('events.*') ? 'text-gray-900' : 'text-gray-600' }}">賽事</a>
            <a href="{{ route('scores.index') }}"
               class="px-2 py-1 rounded-lg hover:bg-gray-100 {{ request()->routeIs('scores.*') ? 'text-gray-900' : 'text-gray-600' }}">
                訓練紀錄
            </a>
            <a href="{{ route('second-hand.index') }}"
               class="px-2 py-1 rounded-lg hover:bg-gray-100 {{ request()->routeIs('second-hand.*') ? 'text-gray-900' : 'text-gray-600' }}">
                二手市集
            </a>
            @auth
                <a href="{{ route('store.index') }}" class="px-2 py-1 rounded-lg hover:bg-gray-100 {{ request()->routeIs('store.*') ? 'text-gray-900' : 'text-gray-600' }}">商店</a>
            @endauth
{{--            <a href="{{ route('events.index') }}"--}}
{{--               class="px-2 py-1 rounded-lg hover:bg-gray-100 {{ request()->routeIs('events.*') ? 'text-gray-900' : 'text-gray-600' }}">--}}
{{--                賽事情報--}}
{{--            </a>--}}
{{--            @auth--}}
{{--                <a href="{{ route('my-events.index') }}"--}}
{{--                   class="px-2 py-1 rounded-lg hover:bg-gray-100 {{ request()->routeIs('my-events.*') ? 'text-gray-900' : 'text-gray-600' }}">--}}
{{--                    我的賽事--}}
{{--                </a>--}}
{{--            @endauth--}}
            {{--            <a href="{{ route('leaderboards.index') }}"--}}
            {{--               class="px-2 py-1 rounded-lg hover:bg-gray-100 {{ request()->routeIs('leaderboards.*') ? 'text-gray-900' : 'text-gray-600' }}">--}}
            {{--                排行榜--}}
            {{--            </a>--}}
            {{--            <a href="{{ route('events.index') }}"--}}
            {{--               class="px-2 py-1 rounded-lg hover:bg-gray-100 {{ request()->routeIs('events.*') ? 'text-gray-900' : 'text-gray-600' }}">--}}
            {{--                賽事--}}
            {{--            </a>--}}
{{--            <a href="{{ route('team-posts.index') }}"--}}
{{--               class="px-2 py-1 rounded-lg hover:bg-gray-100 {{ request()->routeIs('events.*') ? 'text-gray-900' : 'text-gray-600' }}">--}}
{{--                組隊區--}}
{{--            </a>--}}
{{--            @auth--}}
{{--                @if(auth()->user()->isAdmin())--}}
{{--                    <a href="{{ route('admin.events.index') }}"--}}
{{--                       class="px-2 py-1 rounded-lg hover:bg-gray-100 {{ request()->routeIs('admin.*') ? 'text-gray-900' : 'text-gray-600' }}">--}}
{{--                        Admin--}}
{{--                    </a>--}}
{{--                @endif--}}
{{--            @endauth--}}

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
            <nav id="desktop-account-navigation" class="hidden md:flex items-center gap-2">
                @auth
                    <div class="relative">
                        <button id="user-menu-button"
                                class="flex items-center gap-2 rounded-xl bg-gray-100 px-3 py-2 text-sm font-medium hover:bg-gray-200"
                                aria-haspopup="true" aria-expanded="false">
                            {{ auth()->user()->display_name }}
                            <svg class="h-4 w-4 text-gray-500" viewBox="0 0 20 20" fill="currentColor"
                                 aria-hidden="true">
                                <path fill-rule="evenodd"
                                      d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.24a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z"
                                      clip-rule="evenodd"/>
                            </svg>
                        </button>

                        <div id="user-menu"
                             class="hidden absolute right-0 mt-2 max-h-[calc(100vh-5rem)] w-64 overflow-y-auto rounded-xl border bg-white p-2 shadow-lg"
                             role="menu" aria-labelledby="user-menu-button">
                            <div class="px-3 pb-1 pt-2 text-xs font-medium text-gray-400">我的功能</div>
                            <a href="{{ route('member-profile.index') }}" class="flex min-h-10 items-center rounded-lg px-3 text-sm hover:bg-gray-50 {{ request()->routeIs('member-profile.*') || request()->routeIs('members.*') ? 'bg-gray-50 font-semibold text-gray-900' : 'text-gray-700' }}" role="menuitem">會員資料</a>
                            @if(auth()->user()->hasCompletedProfile())<a href="{{ route('my-events.index') }}" class="flex min-h-10 items-center rounded-lg px-3 text-sm hover:bg-gray-50 {{ request()->routeIs('my-events.*') ? 'bg-gray-50 font-semibold text-gray-900' : 'text-gray-700' }}" role="menuitem">我的賽事</a>@endif

                            <div class="mt-2 border-t px-3 pb-1 pt-3 text-xs font-medium text-gray-400">主辦方工具</div>
                            @if(auth()->user()->canCreateEvents())
                                <a href="{{ route('organizer.events.index') }}" class="flex min-h-10 items-center rounded-lg px-3 text-sm hover:bg-gray-50 {{ request()->routeIs('organizer.events.index') || request()->routeIs('organizer.events.show') ? 'bg-gray-50 font-semibold text-gray-900' : 'text-gray-700' }}" role="menuitem">我的主辦賽事</a>
                                <a href="{{ route('organizer.events.create') }}" class="flex min-h-10 items-center rounded-lg px-3 text-sm hover:bg-gray-50 {{ request()->routeIs('organizer.events.create') ? 'bg-gray-50 font-semibold text-gray-900' : 'text-gray-700' }}" role="menuitem">建立賽事</a>
                                <a href="{{ route('organizer.badges.index') }}" class="flex min-h-10 items-center rounded-lg px-3 text-sm hover:bg-gray-50 {{ request()->routeIs('organizer.badges.*') ? 'bg-gray-50 font-semibold text-gray-900' : 'text-gray-700' }}" role="menuitem">Badge 列表</a>
                                @unless(auth()->user()->isVerifiedOrganizer())<a href="{{ route('organizer.qualification.show') }}" class="flex min-h-10 items-center rounded-lg px-3 text-sm text-indigo-700 hover:bg-indigo-50 {{ request()->routeIs('organizer.qualification.*') ? 'bg-indigo-50 font-semibold' : '' }}" role="menuitem">申請官方主辦方認證</a>@endunless
                            @else
                                <a href="{{ route('organizer.qualification.show') }}" class="flex min-h-10 items-center rounded-lg px-3 text-sm text-red-700 hover:bg-red-50 {{ request()->routeIs('organizer.qualification.*') ? 'bg-red-50 font-semibold' : '' }}" role="menuitem">查看主辦方資格狀態</a>
                            @endif

                            @if(auth()->user()->isAdmin())
                                <div class="mt-2 border-t px-3 pb-1 pt-3 text-xs font-medium text-gray-400">平台管理</div>
                                <a href="{{ route('admin.organizers.index') }}" class="flex min-h-10 items-center rounded-lg px-3 text-sm hover:bg-gray-50 {{ request()->routeIs('admin.organizers.*') ? 'bg-gray-50 font-semibold text-gray-900' : 'text-gray-700' }}" role="menuitem">主辦方審核</a>
                                <a href="{{ route('admin.events.index') }}" class="flex min-h-10 items-center rounded-lg px-3 text-sm hover:bg-gray-50 {{ request()->routeIs('admin.events.*') ? 'bg-gray-50 font-semibold text-gray-900' : 'text-gray-700' }}" role="menuitem">賽事管理</a>
                                <a href="{{ route('admin.badges.index') }}" class="flex min-h-10 items-center rounded-lg px-3 text-sm hover:bg-gray-50 {{ request()->routeIs('admin.badges.*') ? 'bg-gray-50 font-semibold text-gray-900' : 'text-gray-700' }}" role="menuitem">Badge 管理</a>
                                <a href="{{ route('admin.users.index') }}" class="flex min-h-10 items-center rounded-lg px-3 text-sm hover:bg-gray-50 {{ request()->routeIs('admin.users.*') ? 'bg-gray-50 font-semibold text-gray-900' : 'text-gray-700' }}" role="menuitem">使用者管理</a>
                            @endif

                            <form method="POST" action="{{ route('logout') }}" class="mt-2 border-t pt-2" role="none">
                                @csrf
                                <button type="submit"
                                        class="flex min-h-10 w-full items-center rounded-lg px-3 text-left text-sm text-red-600 hover:bg-red-50"
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

        <nav class="flex-1 overflow-y-auto p-3 text-sm">
            @auth
                <div class="px-3 pb-1 pt-2 text-xs font-medium text-gray-400">我的功能</div>
                <a href="{{ route('member-profile.index') }}" class="flex min-h-11 items-center rounded-lg px-3 hover:bg-gray-50 {{ request()->routeIs('member-profile.*') || request()->routeIs('members.*') ? 'bg-gray-50 font-semibold text-gray-900' : 'text-gray-700' }}">會員資料</a>
                @if(auth()->user()->hasCompletedProfile())<a href="{{ route('my-events.index') }}" class="flex min-h-11 items-center rounded-lg px-3 hover:bg-gray-50 {{ request()->routeIs('my-events.*') ? 'bg-gray-50 font-semibold text-gray-900' : 'text-gray-700' }}">我的賽事</a>@endif
                <a href="{{ route('scores.index') }}" class="flex min-h-11 items-center rounded-lg px-3 hover:bg-gray-50 {{ request()->routeIs('scores.*') ? 'bg-gray-50 font-semibold text-gray-900' : 'text-gray-700' }}">訓練紀錄</a>
            @endauth

            <div class="mt-3 border-t px-3 pb-1 pt-4 text-xs font-medium text-gray-400">探索</div>
            <a href="{{ route('events.index') }}" class="flex min-h-11 items-center rounded-lg px-3 hover:bg-gray-50 {{ request()->routeIs('events.index') || request()->routeIs('events.show') ? 'bg-gray-50 font-semibold text-gray-900' : 'text-gray-700' }}">賽事</a>
            <a href="{{ route('second-hand.index') }}" class="flex min-h-11 items-center rounded-lg px-3 hover:bg-gray-50 {{ request()->routeIs('second-hand.*') ? 'bg-gray-50 font-semibold text-gray-900' : 'text-gray-700' }}">二手市集</a>
            @auth<a href="{{ route('store.index') }}" class="flex min-h-11 items-center rounded-lg px-3 hover:bg-gray-50 {{ request()->routeIs('store.*') ? 'bg-gray-50 font-semibold text-gray-900' : 'text-gray-700' }}">商店</a>@endauth

            @auth
                <div class="mt-3 border-t px-3 pb-1 pt-4 text-xs font-medium text-gray-400">主辦方工具</div>
                @if(auth()->user()->canCreateEvents())
                    <a href="{{ route('organizer.events.index') }}" class="flex min-h-11 items-center rounded-lg px-3 hover:bg-gray-50 {{ request()->routeIs('organizer.events.index') || request()->routeIs('organizer.events.show') ? 'bg-gray-50 font-semibold text-gray-900' : 'text-gray-700' }}">我的主辦賽事</a>
                    <a href="{{ route('organizer.badges.index') }}" class="flex min-h-11 items-center rounded-lg px-3 hover:bg-gray-50 {{ request()->routeIs('organizer.badges.*') ? 'bg-gray-50 font-semibold text-gray-900' : 'text-gray-700' }}">Badge 列表</a>
                    <a href="{{ route('organizer.events.create') }}" class="flex min-h-11 items-center rounded-lg px-3 hover:bg-gray-50 {{ request()->routeIs('organizer.events.create') ? 'bg-gray-50 font-semibold text-gray-900' : 'text-gray-700' }}">建立賽事</a>
                    @unless(auth()->user()->isVerifiedOrganizer())<a href="{{ route('organizer.qualification.show') }}" class="flex min-h-11 items-center rounded-lg px-3 text-indigo-700 hover:bg-indigo-50 {{ request()->routeIs('organizer.qualification.*') ? 'bg-indigo-50 font-semibold' : '' }}">申請官方主辦方認證</a>@endunless
                @else
                    <a href="{{ route('organizer.qualification.show') }}" class="flex min-h-11 items-center rounded-lg px-3 text-red-700 hover:bg-red-50 {{ request()->routeIs('organizer.qualification.*') ? 'bg-red-50 font-semibold' : '' }}">查看主辦方資格狀態</a>
                @endif

                @if(auth()->user()->isAdmin())
                    <div class="mt-3 border-t px-3 pb-1 pt-4 text-xs font-medium text-gray-400">平台管理</div>
                    <a href="{{ route('admin.organizers.index') }}" class="flex min-h-11 items-center rounded-lg px-3 hover:bg-gray-50 {{ request()->routeIs('admin.organizers.*') ? 'bg-gray-50 font-semibold text-gray-900' : 'text-gray-700' }}">主辦方審核</a>
                    <a href="{{ route('admin.events.index') }}" class="flex min-h-11 items-center rounded-lg px-3 hover:bg-gray-50 {{ request()->routeIs('admin.events.*') ? 'bg-gray-50 font-semibold text-gray-900' : 'text-gray-700' }}">賽事管理</a>
                    <a href="{{ route('admin.badges.index') }}" class="flex min-h-11 items-center rounded-lg px-3 hover:bg-gray-50 {{ request()->routeIs('admin.badges.*') ? 'bg-gray-50 font-semibold text-gray-900' : 'text-gray-700' }}">Badge 管理</a>
                    <a href="{{ route('admin.users.index') }}" class="flex min-h-11 items-center rounded-lg px-3 hover:bg-gray-50 {{ request()->routeIs('admin.users.*') ? 'bg-gray-50 font-semibold text-gray-900' : 'text-gray-700' }}">使用者管理</a>
                @endif
            @endauth
        </nav>

        <div class="mt-auto border-t p-3">
            {{-- 使用者區塊 --}}
            @auth
                <div class="px-3 py-2 text-xs text-gray-500">使用者</div>
                <div class="px-3 py-2 font-medium">{{ auth()->user()->display_name }}</div>
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
        window.addEventListener('resize', () => {
            if (drawer && getComputedStyle(drawer).display === 'none') closeDrawer();
        });
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
