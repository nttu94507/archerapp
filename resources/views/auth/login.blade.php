<!doctype html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>登入</title>
    @vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="min-h-screen flex items-center justify-center bg-gray-50">
<div class="w-full max-w-md bg-white rounded-2xl shadow p-8">
    <h1 class="text-2xl font-semibold text-center mb-6">射箭會員登入</h1>

    @if ($errors->any())
        <div class="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf
        <div>
            <label class="block text-sm mb-1">Email</label>
            <input type="email" name="email" required autofocus
                   value="{{ old('email') }}"
                   class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
        </div>

        <div>
            <label class="block text-sm mb-1">密碼</label>
            <input type="password" name="password" required
                   class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" />
        </div>

        <div class="flex items-center justify-between">
            <label class="inline-flex items-center space-x-2">
                <input type="checkbox" name="remember" class="rounded border-gray-300">
                <span class="text-sm">記住我</span>
            </label>
            <a href="{{ route('password.request') }}" class="text-sm text-indigo-600 hover:underline">忘記密碼？</a>
        </div>

        <button type="submit"
                class="w-full rounded-lg bg-indigo-600 text-white py-2 font-medium hover:bg-indigo-700">
            登入
        </button>
    </form>

    <div class="my-6 flex items-center">
        <div class="flex-1 h-px bg-gray-200"></div>
        <span class="px-3 text-gray-500 text-sm">或</span>
        <div class="flex-1 h-px bg-gray-200"></div>
    </div>

    <a href="{{ route('login.google.redirect') }}"
       class="w-full inline-flex items-center justify-center gap-2 rounded-lg border border-gray-300 py-2 hover:bg-gray-50">
        {{-- 你可以放 Google Icon --}}
        <span>使用 Google 登入</span>
    </a>

    <p class="text-center text-sm text-gray-600 mt-6">
        還沒有帳號？ <a class="text-indigo-600 hover:underline" href="{{ route('register') }}">建立帳號</a>
    </p>
</div>
</body>
</html>
