@extends('layouts.app')

@section('title', '成就')

@section('content')
    <div class="max-w-5xl mx-auto px-4 py-8 space-y-8">
        <section>
            <h1 class="text-2xl font-bold">🏅 成就</h1>
            <p class="text-sm text-gray-600 mt-1">追蹤你的連續訓練、累積天數與總箭數進度。</p>
        </section>

        <section class="space-y-4">
            <h2 class="text-xl font-semibold">即將達成</h2>

            @forelse($inProgress as $item)
                <article class="bg-white rounded-xl border p-4">
                    <div class="flex justify-between gap-4 items-center">
                        <div>
                            <h3 class="font-semibold">{{ $item->definition->name }}</h3>
                            <p class="text-sm text-gray-600">{{ $item->definition->description }}</p>
                        </div>
                        <p class="text-sm text-gray-700">{{ $item->current_value }} / {{ $item->target_value }}</p>
                    </div>
                    <div class="mt-3 w-full h-2 bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full bg-indigo-500" style="width: {{ $item->progress_percent }}%"></div>
                    </div>
                </article>
            @empty
                <p class="text-sm text-gray-600">目前沒有進行中的成就 🎯</p>
            @endforelse
        </section>

        <section class="space-y-4">
            <h2 class="text-xl font-semibold">已解鎖</h2>

            @forelse($unlocked as $item)
                <article class="bg-green-50 border border-green-200 rounded-xl p-4">
                    <h3 class="font-semibold">{{ $item->definition->name }}</h3>
                    <p class="text-sm text-gray-700">{{ $item->definition->description }}</p>
                    <p class="text-xs text-gray-500 mt-1">解鎖時間：{{ optional($item->unlocked_at)->format('Y-m-d H:i') }}</p>
                </article>
            @empty
                <p class="text-sm text-gray-600">還沒有解鎖成就，先從連續 3 天開始吧！</p>
            @endforelse
        </section>
    </div>
@endsection
