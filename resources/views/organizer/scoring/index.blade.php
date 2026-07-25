@extends('layouts.app')

@section('title', $event->name.' 靶位計分')

@section('content')
<div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 sm:py-8">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <a href="{{ route('organizer.events.show',$event) }}" class="inline-flex min-h-11 items-center text-sm font-medium text-indigo-600">← 返回賽事工作台</a>
            <p class="text-xs font-semibold uppercase tracking-widest text-indigo-600">Target scoring</p>
            <h1 class="mt-1 text-2xl font-bold">靶位共用設備計分</h1>
            <p class="mt-1 text-sm text-gray-500">依組別自動排靶，每台設備負責同靶 2～4 位選手。</p>
        </div>
        <a href="{{ route('organizer.events.results.index',$event) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border px-5 text-sm font-medium">前往成績核對與發布</a>
    </div>

    @if(session('success'))<div class="rounded-xl bg-green-50 p-4 text-sm text-green-700">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="rounded-xl bg-red-50 p-4 text-sm text-red-700">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="rounded-xl bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>@endif

    @if($sessions->isNotEmpty())
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            賽事已執行排靶，報名已停止。已排靶的組別會永久鎖定，不能再次執行。
        </div>
    @endif

    <section class="rounded-2xl border bg-white p-4 shadow-sm sm:p-6">
        <h2 class="font-semibold">建立計分場次</h2>
        <p class="mt-1 text-sm text-gray-500">系統會依姓名排序，自動將已報名或已報到選手排入 1A、1B…。第一次排靶後，整場賽事會立即停止報名。</p>
        <form method="POST" action="{{ route('organizer.events.scoring.store',$event) }}" onsubmit="return confirm('排靶後將立即停止整場賽事報名，且此組別不能重新排靶。確定執行？')" class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_12rem_auto]">
            @csrf
            <select name="event_group_id" required class="min-h-12 rounded-xl border-gray-300">
                <option value="">選擇組別</option>
                @foreach($event->groups as $group)
                    <option value="{{ $group->id }}" @disabled($group->scoring_sessions_count > 0)>
                        {{ $group->name }}（{{ $group->active_registrations_count }} 人{{ $group->scoring_sessions_count > 0 ? '，已排靶' : '' }}）
                    </option>
                @endforeach
            </select>
            <input name="name" required value="{{ old('name',$event->name.' 資格賽') }}" class="min-h-12 rounded-xl border-gray-300" placeholder="場次名稱">
            <select name="athletes_per_target" class="min-h-12 rounded-xl border-gray-300"><option value="4">每靶 4 人</option><option value="3">每靶 3 人</option><option value="2">每靶 2 人</option></select>
            <button class="min-h-12 rounded-xl bg-indigo-600 px-5 text-sm font-medium text-white">確認排靶並停止報名</button>
        </form>
    </section>

    <div class="space-y-5">
        @forelse($sessions as $session)
            <section class="rounded-2xl border bg-white p-4 shadow-sm sm:p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div><h2 class="text-lg font-semibold">{{ $session->name }}</h2><p class="mt-1 text-sm text-gray-500">{{ $session->group?->name }} · {{ $session->total_arrows }} 箭 · 每趟 {{ $session->arrows_per_end }} 箭 · {{ $session->targets->count() }} 靶</p></div>
                    <span class="rounded-full px-3 py-1 text-xs font-medium {{ $session->status==='completed' ? 'bg-green-100 text-green-700' : ($session->status==='scoring' ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-700') }}">{{ ['ready'=>'待開始','scoring'=>'計分中','completed'=>'已完成'][$session->status] ?? $session->status }}</span>
                </div>
                <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($session->targets as $target)
                        @php($stationUrl=route('scoring-stations.show',$target->access_token))
                        <article class="rounded-xl border p-4">
                            <div class="flex items-start justify-between gap-2"><div><h3 class="font-semibold">靶號 {{ str_pad($target->target_number,2,'0',STR_PAD_LEFT) }}</h3><p class="mt-1 text-xs text-gray-500">完成 {{ $target->last_completed_end }} / {{ $session->totalEnds() }} 趟</p></div><span class="rounded-full bg-gray-100 px-2 py-1 text-xs">{{ ['ready'=>'待開始','scoring'=>'計分中','completed'=>'完成'][$target->status] ?? $target->status }}</span></div>
                            <div class="mt-3 space-y-1">@foreach($target->assignments as $assignment)<p class="text-sm"><span class="inline-block w-8 font-mono font-semibold">{{ $target->target_number.$assignment->position }}</span>{{ $assignment->registration?->name }}</p>@endforeach</div>
                            <div class="mt-4 grid grid-cols-2 gap-2">
                                <a href="{{ $stationUrl }}" target="_blank" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-gray-900 px-3 text-sm font-medium text-white">開啟計分台</a>
                                <button type="button" data-copy="{{ $stationUrl }}" class="copy-station min-h-11 rounded-xl border px-3 text-sm">複製連結</button>
                            </div>
                            <p class="mt-2 text-xs text-gray-400">最後同步：{{ $target->last_synced_at?->diffForHumans() ?? '尚未同步' }}</p>
                        </article>
                    @endforeach
                </div>
            </section>
        @empty
            <div class="rounded-2xl border border-dashed bg-white p-8 text-center text-sm text-gray-500">尚未建立計分場次。</div>
        @endforelse
    </div>
</div>
<script>document.querySelectorAll('.copy-station').forEach(button=>button.addEventListener('click',async()=>{await navigator.clipboard.writeText(button.dataset.copy);button.textContent='已複製';setTimeout(()=>button.textContent='複製連結',1500)}));</script>
@endsection
