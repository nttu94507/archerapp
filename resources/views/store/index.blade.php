@extends('layouts.app')

@section('title', '方案商店')

@section('content')
<main class="mx-auto w-full max-w-6xl space-y-6 px-4 py-6 sm:px-6 lg:py-10">
    <section class="overflow-hidden rounded-3xl bg-gradient-to-br from-indigo-700 via-violet-700 to-purple-800 px-6 py-8 text-white shadow-lg sm:px-10 sm:py-10">
        <span class="inline-flex rounded-full bg-white/15 px-3 py-1 text-xs font-semibold ring-1 ring-white/20">ArrowTrack 方案商店</span>
        <h1 class="mt-4 text-3xl font-bold tracking-tight sm:text-4xl">依照賽事規模選擇功能</h1>
        <p class="mt-3 max-w-2xl text-sm leading-6 text-indigo-100 sm:text-base">免費方案適合公開的社團排名賽；需要不公開分享、多組別、第二局或個人對抗賽時，可為單場賽事升級進階方案。</p>
    </section>

    @if($events->isNotEmpty())
        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <form method="GET" action="{{ route('store.index') }}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <label class="min-w-0 flex-1">
                    <span class="mb-2 block text-sm font-semibold text-gray-800">要升級哪一場賽事？</span>
                    <select name="event" class="min-h-11 w-full rounded-xl border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">請選擇賽事</option>
                        @foreach($upgradeableEvents as $event)
                            <option value="{{ $event->uuid }}" @selected($selectedEvent?->is($event))>{{ $event->name }}（{{ $event->start_date?->format('Y-m-d') }}）</option>
                        @endforeach
                    </select>
                </label>
                <button @disabled($upgradeableEvents->isEmpty()) class="min-h-11 rounded-xl px-5 text-sm font-semibold {{ $upgradeableEvents->isEmpty() ? 'cursor-not-allowed bg-gray-200 text-gray-400' : 'bg-gray-900 text-white' }}">查看適用方案</button>
            </form>

            @if($upgradeableEvents->isEmpty())
                <p class="mt-3 text-sm text-gray-500">目前沒有可購買單場升級的免費賽事。已正式完成或已取消的賽事不適用。</p>
            @endif

            @if(request()->filled('event') && !$selectedEvent)
                <p class="mt-3 text-sm text-red-600">找不到可由你管理的賽事，請重新選擇。</p>
            @elseif($selectedEvent)
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl {{ $selectedEvent->eventPassUpgradeBlockReason() && $selectedEvent->isFreePlan() ? 'bg-amber-50' : 'bg-indigo-50' }} px-4 py-3">
                    <div><p class="font-semibold {{ $selectedEvent->eventPassUpgradeBlockReason() && $selectedEvent->isFreePlan() ? 'text-amber-950' : 'text-indigo-950' }}">{{ $selectedEvent->name }}</p><p class="mt-0.5 text-xs {{ $selectedEvent->eventPassUpgradeBlockReason() && $selectedEvent->isFreePlan() ? 'text-amber-700' : 'text-indigo-700' }}">{{ $selectedEvent->eventPassUpgradeBlockReason() ?? '目前方案：免費方案・可升級' }}</p></div>
                    @unless($selectedEvent->isFreePlan())<span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">已啟用進階功能</span>@endunless
                </div>
            @endif

            @if($unavailableFreeEvents->isNotEmpty())
                <details class="mt-4 rounded-xl border bg-gray-50 px-4 py-2">
                    <summary class="flex min-h-10 cursor-pointer items-center justify-between text-sm font-medium"><span>不可升級的賽事</span><span class="text-xs text-gray-500">{{ $unavailableFreeEvents->count() }} 場</span></summary>
                    <div class="divide-y border-t">@foreach($unavailableFreeEvents as $event)<div class="py-3 text-sm"><p class="font-medium text-gray-700">{{ $event->name }}</p><p class="mt-0.5 text-xs text-gray-500">{{ $event->eventPassUpgradeBlockReason() }}</p></div>@endforeach</div>
                </details>
            @endif
        </section>
    @else
        <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">你目前沒有可管理的賽事。建立賽事後，就能在這裡選擇要升級的場次。</section>
    @endif

    <section class="grid gap-5 lg:grid-cols-3">
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
                <li>✓ 公開顯示於賽事列表</li>
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
                <li>✓ 可設為不公開，僅持連結者可進入</li>
            </ul>
            @if($selectedEvent && !$selectedEvent->isFreePlan())
                <div class="mt-7 min-h-11 rounded-xl bg-emerald-100 px-4 py-3 text-center text-sm font-semibold text-emerald-700">這場賽事已啟用</div>
            @elseif($selectedEvent && !$selectedEvent->canUpgradeToEventPass())
                <div class="mt-7 min-h-11 rounded-xl bg-amber-100 px-4 py-3 text-center text-sm font-semibold text-amber-800">{{ $selectedEvent->eventPassUpgradeBlockReason() }}</div>
            @else
                <button type="button" disabled class="mt-7 min-h-11 w-full cursor-not-allowed rounded-xl bg-indigo-200 px-4 text-sm font-semibold text-indigo-700">付款功能準備中</button>
            @endif
        </article>

        <article class="relative rounded-3xl border-2 border-violet-500 bg-white p-6 shadow-md">
            <span class="absolute right-5 top-5 rounded-full bg-violet-100 px-3 py-1 text-xs font-semibold text-violet-700">主辦方帳號</span>
            <p class="text-sm font-semibold text-violet-600">訂閱方案</p>
            <h2 class="mt-2 text-2xl font-bold text-gray-950">持續舉辦賽事</h2>
            <p class="mt-2 text-sm text-gray-500">訂閱有效期間建立的新賽事，會自動取得完整進階權益。</p>
            <ul class="mt-6 space-y-3 text-sm text-gray-700">
                <li>✓ 不必逐場購買方案</li>
                <li>✓ 新賽事自動套用進階功能</li>
                <li>✓ 可建立不公開的校內賽或社團賽</li>
                <li>✓ 訂閱到期不影響既有賽事</li>
                <li>✓ 適合社團、俱樂部與協會</li>
            </ul>
            @if($subscription)
                <div class="mt-7 rounded-xl bg-emerald-100 px-4 py-3 text-center text-sm font-semibold text-emerald-700">
                    訂閱中{{ $subscription->ends_at ? '・至 '.$subscription->ends_at->format('Y-m-d') : '・無到期日' }}
                </div>
            @else
                <button type="button" disabled class="mt-7 min-h-11 w-full cursor-not-allowed rounded-xl bg-violet-200 px-4 text-sm font-semibold text-violet-700">線上訂閱準備中</button>
            @endif
        </article>
    </section>

    <p class="text-center text-xs text-gray-500">此頁目前僅提供方案入口與功能比較，不會扣款，也不會變更賽事方案。</p>
</main>
@endsection
