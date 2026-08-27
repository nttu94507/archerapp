@extends('layouts.app')

@section('title', '方案商店')

@section('content')
<main class="mx-auto w-full max-w-6xl space-y-6 px-4 py-6 sm:px-6 lg:py-10">
    <section class="overflow-hidden rounded-3xl bg-gradient-to-br from-indigo-700 via-violet-700 to-purple-800 px-6 py-8 text-white shadow-lg sm:px-10 sm:py-10">
        <span class="inline-flex rounded-full bg-white/15 px-3 py-1 text-xs font-semibold ring-1 ring-white/20">ArrowTrack 方案商店</span>
        <h1 class="mt-4 text-3xl font-bold tracking-tight sm:text-4xl">依照賽事規模選擇功能</h1>
        <p class="mt-3 max-w-2xl text-sm leading-6 text-indigo-100 sm:text-base">免費方案適合社團排名賽；需要多組別、第二局或個人對抗賽時，可為單場賽事升級進階方案。</p>
    </section>

    @if($events->isNotEmpty())
        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <form method="GET" action="{{ route('store.index') }}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <label class="min-w-0 flex-1">
                    <span class="mb-2 block text-sm font-semibold text-gray-800">要升級哪一場賽事？</span>
                    <select name="event" class="min-h-11 w-full rounded-xl border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">請選擇賽事</option>
                        @foreach($events as $event)
                            <option value="{{ $event->uuid }}" @selected($selectedEvent?->is($event))>{{ $event->name }}（{{ $event->start_date?->format('Y-m-d') }}）</option>
                        @endforeach
                    </select>
                </label>
                <button class="min-h-11 rounded-xl bg-gray-900 px-5 text-sm font-semibold text-white">查看適用方案</button>
            </form>

            @if(request()->filled('event') && !$selectedEvent)
                <p class="mt-3 text-sm text-red-600">找不到可由你管理的賽事，請重新選擇。</p>
            @elseif($selectedEvent)
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-indigo-50 px-4 py-3">
                    <div><p class="font-semibold text-indigo-950">{{ $selectedEvent->name }}</p><p class="mt-0.5 text-xs text-indigo-700">目前方案：{{ $selectedEvent->isFreePlan() ? '免費方案' : '進階方案' }}</p></div>
                    @unless($selectedEvent->isFreePlan())<span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">已啟用進階功能</span>@endunless
                </div>
            @endif
        </section>
    @else
        <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">你目前沒有可管理的賽事。建立賽事後，就能在這裡選擇要升級的場次。</section>
    @endif

    <section class="grid gap-5 lg:grid-cols-2">
        <article class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
            <p class="text-sm font-semibold text-gray-500">免費方案</p>
            <h2 class="mt-2 text-2xl font-bold text-gray-950">社團排名賽</h2>
            <p class="mt-2 text-sm text-gray-500">適合小型、單日且流程單純的比賽。</p>
            <ul class="mt-6 space-y-3 text-sm text-gray-700">
                <li>✓ 1 個組別，最多 32 位選手</li>
                <li>✓ 單局最多 36 箭</li>
                <li>✓ 最多 2 位工作人員、8 個靶位</li>
                <li>✓ 1 枚賽事 Badge</li>
                <li>✓ 排名賽與即時戰況</li>
            </ul>
            <div class="mt-7 min-h-11 rounded-xl bg-gray-100 px-4 py-3 text-center text-sm font-semibold text-gray-600">建立賽事即可使用</div>
        </article>

        <article class="relative rounded-3xl border-2 border-indigo-500 bg-white p-6 shadow-md">
            <span class="absolute right-5 top-5 rounded-full bg-indigo-100 px-3 py-1 text-xs font-semibold text-indigo-700">單場升級</span>
            <p class="text-sm font-semibold text-indigo-600">進階方案</p>
            <h2 class="mt-2 text-2xl font-bold text-gray-950">完整正式賽事</h2>
            <p class="mt-2 text-sm text-gray-500">價格與付款方式將於金流功能完成後公布。</p>
            <ul class="mt-6 space-y-3 text-sm text-gray-700">
                <li>✓ 多組別與更多工作人員</li>
                <li>✓ 72 箭上下半場、多局賽制</li>
                <li>✓ 個人對抗表與設備鎖定計分</li>
                <li>✓ 反曲弓局分制、複合弓累計制</li>
                <li>✓ 加射判定、完整成績稽核與進階 Badge</li>
            </ul>
            @if($selectedEvent && !$selectedEvent->isFreePlan())
                <div class="mt-7 min-h-11 rounded-xl bg-emerald-100 px-4 py-3 text-center text-sm font-semibold text-emerald-700">這場賽事已啟用</div>
            @else
                <button type="button" disabled class="mt-7 min-h-11 w-full cursor-not-allowed rounded-xl bg-indigo-200 px-4 text-sm font-semibold text-indigo-700">付款功能準備中</button>
            @endif
        </article>
    </section>

    <p class="text-center text-xs text-gray-500">此頁目前僅提供方案入口與功能比較，不會扣款，也不會變更賽事方案。</p>
</main>
@endsection
