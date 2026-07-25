@extends('layouts.app')

@section('title', '此靶位已有計分設備')

@section('content')
<div class="mx-auto flex min-h-[calc(100dvh-4rem)] max-w-xl items-center px-4 py-10">
    <div class="w-full rounded-2xl border border-red-200 bg-white p-8 text-center shadow-sm">
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-red-50 text-2xl text-red-600">×</div>
        <h1 class="mt-4 text-xl font-bold text-gray-900">無法開啟此靶位</h1>
        <p class="mt-3 text-sm leading-6 text-gray-600">此靶位已綁定其他計分設備，不允許第二台設備讀取或輸入成績。</p>
        <p class="mt-2 text-sm font-medium text-gray-800">請重新掃描或開啟其他靶位的計分連結。</p>
        <p class="mt-6 text-xs text-gray-400">若原設備故障，請聯絡主辦方先解除設備綁定。</p>
    </div>
</div>
@endsection
