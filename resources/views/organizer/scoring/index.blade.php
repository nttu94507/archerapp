@extends('layouts.app')

@section('title', $event->name.' 靶位計分')

@section('content')
<div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 sm:py-8">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <a href="{{ route('organizer.events.show',$event) }}" class="inline-flex min-h-11 items-center text-sm font-medium text-indigo-600">← 返回賽事工作台</a>
            <p class="text-xs font-semibold uppercase tracking-widest text-indigo-600">Target scoring</p>
            <h1 class="mt-1 text-2xl font-bold">靶位列表</h1>
            <p class="mt-1 text-sm text-gray-500">依組別自動排靶，每台設備負責同靶 2～4 位選手。</p>
        </div>
    </div>

    @if(session('success'))<div class="rounded-xl bg-green-50 p-4 text-sm text-green-700">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="rounded-xl bg-red-50 p-4 text-sm text-red-700">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="rounded-xl bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>@endif

    @if($sessions->isNotEmpty())
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            賽事已完成全部組別排靶，所有組別報名均已停止，不能再次執行排靶。
        </div>
    @endif

    <section class="rounded-2xl border bg-white p-4 shadow-sm sm:p-6">
        <div class="flex items-start justify-between gap-3">
            <h2 class="font-semibold">全部組別排靶位</h2>
            <details class="group relative shrink-0">
                <summary class="flex h-11 w-11 cursor-pointer list-none items-center justify-center rounded-full border bg-gray-50 text-sm font-bold text-gray-500 hover:bg-gray-100" aria-label="查看全部組別排靶說明">i</summary>
                <div class="absolute right-0 z-20 mt-2 w-72 max-w-[calc(100vw-3rem)] rounded-xl border bg-white p-4 text-sm leading-6 text-gray-600 shadow-xl">
                    <p class="font-semibold text-gray-900">排靶說明</p>
                    <p class="mt-1">系統會一次處理所有組別，依姓名將已報名或已報到選手排入 1A、1B…。無選手組別會略過；成功後所有組別立即截止報名，且整場只能執行一次。</p>
                </div>
            </details>
        </div>
        <div class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($event->groups as $group)
                <div class="flex items-center justify-between rounded-xl bg-gray-50 px-4 py-3 text-sm">
                    <span class="font-medium">{{ $group->name }}</span>
                    <span class="{{ $group->active_registrations_count > 0 ? 'text-gray-600' : 'text-amber-600' }}">{{ $group->active_registrations_count > 0 ? '已報到 '.$group->checked_in_registrations_count.' / '.$group->active_registrations_count.' 人' : '無選手，將略過' }}</span>
                </div>
            @endforeach
        </div>
        @if($sessions->isEmpty() && $unreportedRegistrations->isNotEmpty())
            <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4">
                <p class="font-semibold text-amber-900">尚有 {{ $unreportedRegistrations->count() }} 位選手未報到</p>
                <p class="mt-1 text-sm text-amber-800">若繼續排靶，以下選手仍會保留靶位，但會標記為 DNS 且計分設備不能輸入其成績。</p>
                <div class="mt-3 flex max-h-36 flex-wrap gap-2 overflow-y-auto">
                    @foreach($unreportedRegistrations as $registration)
                        <span class="rounded-full bg-white px-3 py-1 text-xs text-amber-800">{{ $registration->event_group?->name }}・{{ $registration->name }}</span>
                    @endforeach
                </div>
            </div>
        @endif
        @if($sessions->isEmpty())
            <form method="POST" action="{{ route('organizer.events.scoring.store',$event) }}" onsubmit="return confirmScoringAssignment(this)" class="mt-4 grid gap-3 lg:grid-cols-[1fr_12rem_auto]">
                @csrf
                <input type="hidden" name="confirm_unreported" value="0">
                <input name="name" required value="{{ old('name',$event->name.' 資格賽') }}" class="min-h-12 rounded-xl border-gray-300" placeholder="場次名稱">
                <select name="athletes_per_target" class="min-h-12 rounded-xl border-gray-300"><option value="4">每靶 4 人</option><option value="3">每靶 3 人</option><option value="2">每靶 2 人</option></select>
                <button class="min-h-12 rounded-xl bg-indigo-600 px-5 text-sm font-medium text-white">確認全部排靶並停止報名</button>
            </form>
        @else
            <div class="mt-4 rounded-xl bg-gray-100 px-4 py-3 text-sm font-medium text-gray-500">全賽事排靶已完成，此操作已鎖定。</div>
        @endif
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
                            <div class="flex items-start justify-between gap-2"><div><h3 class="font-semibold">靶號 {{ str_pad($target->target_number,2,'0',STR_PAD_LEFT) }}</h3><p class="mt-1 text-xs text-gray-500">完成 {{ $target->last_completed_end }} / {{ $session->totalEnds() }} 趟</p></div><span class="rounded-full bg-gray-100 px-2 py-1 text-xs">{{ ['ready'=>'待開始','scoring'=>'計分中','round_break'=>'上半局完成','completed'=>'完成','dns'=>'全靶 DNS'][$target->status] ?? $target->status }}</span></div>
                            <div class="mt-3 space-y-1">@foreach($target->assignments as $assignment)<p class="text-sm"><span class="inline-block w-8 font-mono font-semibold">{{ $target->target_number.$assignment->position }}</span>{{ $assignment->registration?->name }} @if($assignment->registration?->status === 'no_show')<span class="ml-1 text-xs font-semibold text-amber-700">DNS</span>@endif</p>@endforeach</div>
                            <div class="mt-4 grid grid-cols-[6rem_1fr] items-center gap-3 rounded-xl bg-gray-50 p-3">
                                <img src="{{ route('organizer.events.scoring.targets.qrcode', [$event, $target]) }}" alt="靶號 {{ $target->target_number }} 計分 QR Code" class="h-24 w-24 rounded-lg bg-white p-1">
                                <div class="min-w-0">
                                    <p class="text-xs text-gray-500">設備綁定 PIN</p>
                                    <p class="mt-1 font-mono text-2xl font-bold tracking-[0.2em] text-gray-900">{{ $target->device_pin }}</p>
                                    <p class="mt-2 text-xs leading-5 text-gray-500">掃描 QR Code 或開啟連結，再輸入此 PIN。</p>
                                </div>
                            </div>
                            <div class="mt-4 grid grid-cols-2 gap-2">
                                <a href="{{ $stationUrl }}" target="_blank" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-gray-900 px-3 text-sm font-medium text-white">開啟驗證頁</a>
                                <button type="button" data-copy="{{ $stationUrl }}" class="copy-station min-h-11 rounded-xl border px-3 text-sm">複製連結</button>
                            </div>
                            <p class="mt-2 text-xs text-gray-400">最後同步：{{ $target->last_synced_at?->diffForHumans() ?? '尚未同步' }}</p>
                            <div class="mt-3 flex items-center justify-between gap-3 border-t pt-3">
                                <div class="min-w-0">
                                    <p class="text-xs font-medium {{ $target->device_bound_at ? 'text-emerald-700' : 'text-gray-500' }}">{{ $target->device_bound_at ? '設備已綁定' : '等待設備綁定' }}</p>
                                    @if($target->device_bound_at)<p class="mt-1 text-xs text-gray-400">最後連線：{{ $target->device_last_seen_at?->diffForHumans() ?? '未知' }}</p>@endif
                                </div>
                                @if($target->device_bound_at)
                                    <form method="POST" action="{{ route('organizer.events.scoring.targets.device.destroy', [$event, $target]) }}" onsubmit="return confirm('解除後，目前設備與舊連結會立即失效，系統將產生新的計分連結。確定更換設備？')">
                                        @csrf @method('DELETE')
                                        <button class="min-h-10 rounded-lg border border-red-200 px-3 text-xs font-medium text-red-600">解除設備</button>
                                    </form>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @empty
            <div class="rounded-2xl border border-dashed bg-white p-8 text-center text-sm text-gray-500">尚未建立計分場次。</div>
        @endforelse
    </div>
</div>
<script>
const unreportedCount = {{ $unreportedRegistrations->count() }};
function confirmScoringAssignment(form) {
    const message = unreportedCount > 0
        ? `目前還有 ${unreportedCount} 位選手尚未報到。繼續後仍會排入靶位，但將標記為 DNS 且不能輸入分數。此操作不能重做，確定繼續？`
        : '確定要截止所有組別報名並一次完成全賽事排靶？成功後不能重新排靶。';
    if (!confirm(message)) return false;
    form.elements.confirm_unreported.value = unreportedCount > 0 ? '1' : '0';
    return true;
}
document.querySelectorAll('.copy-station').forEach(button=>button.addEventListener('click',async()=>{await navigator.clipboard.writeText(button.dataset.copy);button.textContent='已複製';setTimeout(()=>button.textContent='複製連結',1500)}));
</script>
@endsection
