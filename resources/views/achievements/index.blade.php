@extends('layouts.app')

@section('title', '成就')

@section('content')
    <div class="max-w-6xl mx-auto px-4 py-8 space-y-8">
        <section class="rounded-2xl border bg-white p-5 sm:p-6">
            <h1 class="text-2xl font-bold">🏅 射箭成就系統（完整版本）</h1>
            <p class="mt-2 text-sm text-gray-600">依照你提供的完整清單重建，含核心 / 距離 / 跨距離 / 等級成就。</p>
            <div class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-3 text-center">
                <div class="rounded-xl bg-gray-50 p-3">
                    <div class="text-xs text-gray-500">總成就數</div>
                    <div class="text-xl font-semibold">{{ $summary['total'] }}</div>
                </div>
                <div class="rounded-xl bg-gray-50 p-3">
                    <div class="text-xs text-gray-500">已解鎖</div>
                    <div class="text-xl font-semibold">{{ $summary['unlocked'] }}</div>
                </div>
                <div class="rounded-xl bg-gray-50 p-3 col-span-2 sm:col-span-2">
                    <div class="text-xs text-gray-500">完成率</div>
                    <div class="text-xl font-semibold">
                        {{ $summary['total'] > 0 ? number_format(($summary['unlocked'] / $summary['total']) * 100, 1) : 0 }}%
                    </div>
                </div>
            </div>
        </section>

        <section class="space-y-3">
            <h2 class="text-xl font-semibold">可使用稱號</h2>
            @forelse($availableTitles as $title)
                <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-4 text-indigo-800 font-semibold">{{ $title }}</div>
            @empty
                <p class="text-sm text-gray-600">尚未解鎖稱號，先完成第一批成就吧！</p>
            @endforelse
        </section>

        @foreach($groupTitles as $groupKey => $groupTitle)
            @php $items = $groups->get($groupKey, collect()); @endphp
            <section class="space-y-3">
                <h2 class="text-lg font-semibold">{{ $groupTitle }}</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    @forelse($items as $item)
                        <article class="rounded-xl border p-4 {{ $item->unlocked_at ? 'bg-emerald-50 border-emerald-200' : 'bg-white' }}">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="font-semibold">{{ $item->definition->name }}</h3>
                                    <p class="text-sm text-gray-600 mt-1">{{ $item->definition->description }}</p>
                                </div>
                                <span class="text-xs px-2 py-1 rounded-full {{ $item->unlocked_at ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600' }}">
                                    {{ $item->unlocked_at ? '已達成' : '進行中' }}
                                </span>
                            </div>

                            <div class="mt-3 text-xs text-gray-500">{{ $item->current_value }} / {{ $item->target_value }}</div>
                            <div class="mt-1 h-2 w-full rounded-full bg-gray-100 overflow-hidden">
                                <div class="h-2 rounded-full bg-indigo-500" style="width: {{ $item->progress_percent }}%"></div>
                            </div>

                            @if($item->definition->title_name)
                                <p class="mt-2 text-xs text-indigo-700">稱號：{{ $item->definition->title_name }}</p>
                            @endif
                        </article>
                    @empty
                        <p class="text-sm text-gray-500">尚無資料。</p>
                    @endforelse
                </div>
            </section>
        @endforeach
    </div>
@endsection
