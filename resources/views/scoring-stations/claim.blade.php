@extends('layouts.app')

@section('title', '靶號 '.$target->target_number.' 設備驗證')

@section('content')
<div class="mx-auto flex min-h-[calc(100dvh-4rem)] max-w-md items-center px-4 py-10">
    <div class="w-full rounded-2xl border bg-white p-6 shadow-sm sm:p-8">
        <div class="text-center">
            <p class="text-sm font-medium text-gray-500">{{ $target->session->event->name }}</p>
            <h1 class="mt-2 text-2xl font-bold text-gray-900">{{ $target->session->group?->name }} / 靶號 {{ str_pad($target->target_number, 2, '0', STR_PAD_LEFT) }}</h1>
            <p class="mt-3 text-sm leading-6 text-gray-600">請輸入主辦方提供的 6 位數 PIN。驗證成功後，這台設備將成為本靶唯一的計分設備。</p>
        </div>

        <form method="POST" action="{{ route('scoring-stations.claim', $target->access_token) }}" class="mt-6 space-y-4">
            @csrf
            <div>
                <label for="pin" class="block text-sm font-medium text-gray-700">設備綁定 PIN</label>
                <input id="pin" name="pin" value="{{ old('pin') }}" required autofocus autocomplete="one-time-code"
                       inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                       class="mt-2 min-h-14 w-full rounded-xl border-gray-300 text-center font-mono text-2xl font-bold tracking-[0.35em] focus:border-indigo-500 focus:ring-indigo-500"
                       placeholder="000000">
                @error('pin')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <button class="min-h-12 w-full rounded-xl bg-indigo-600 px-4 text-sm font-semibold text-white hover:bg-indigo-500">驗證並綁定此設備</button>
        </form>

        <p class="mt-5 text-center text-xs leading-5 text-gray-400">綁定後，其他設備將無法讀取此靶位。需要更換設備時，請由主辦方解除綁定。</p>
    </div>
</div>
@endsection
