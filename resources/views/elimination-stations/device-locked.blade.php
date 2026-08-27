@extends('layouts.app')
@section('title', '此對戰已有計分設備')
@section('content')
<div class="mx-auto flex min-h-[calc(100dvh-4rem)] max-w-xl items-center px-4 py-10"><div class="w-full rounded-2xl border border-red-200 bg-white p-8 text-center"><div class="text-4xl text-red-600">×</div><h1 class="mt-4 text-xl font-bold">無法開啟此對戰</h1><p class="mt-3 text-sm text-gray-600">此場已綁定其他計分設備，第二台設備不能讀取或輸入成績。</p><p class="mt-2 text-sm font-medium">請重新掃描其他場次，或請主辦方解除原設備。</p></div></div>
@endsection
