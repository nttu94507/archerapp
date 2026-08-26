@extends('layouts.app')

@section('title', $event->name.' 裁判工作台')

@section('content')
<div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 sm:py-8">
    <div>
        <a href="{{ route('organizer.events.show', $event) }}" class="inline-flex min-h-11 items-center text-sm font-medium text-indigo-600">← 返回賽事工作台</a>
        <h1 class="text-2xl font-bold">裁判工作台</h1>
        <p class="mt-1 text-sm text-gray-500">裁判核對各靶成績並標記爭議；主裁判完成簽核後，主辦方才能正式發布該組成績。</p>
    </div>

    @if(session('success'))<div class="rounded-xl bg-green-50 p-4 text-sm text-green-700">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="rounded-xl bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>@endif

    <div class="grid grid-cols-3 gap-3">
        @php
            $targets = $event->scoringSessions->flatMap->targets;
        @endphp
        <div class="rounded-2xl border bg-white p-4"><p class="text-xs text-gray-500">全部靶位</p><p class="mt-1 text-2xl font-bold">{{ $targets->count() }}</p></div>
        <div class="rounded-2xl border bg-white p-4"><p class="text-xs text-gray-500">待主裁判簽核</p><p class="mt-1 text-2xl font-bold text-amber-700">{{ $targets->where('status', '!=', 'dns')->where('judge_status', '!=', 'confirmed')->count() }}</p></div>
        <div class="rounded-2xl border bg-white p-4"><p class="text-xs text-gray-500">爭議靶位</p><p class="mt-1 text-2xl font-bold text-red-700">{{ $targets->where('judge_status', 'disputed')->count() }}</p></div>
    </div>

    @forelse($event->scoringSessions as $session)
        <section class="rounded-2xl border bg-white p-4 shadow-sm sm:p-5">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div><h2 class="text-lg font-semibold">{{ $session->group?->name }}</h2><p class="text-sm text-gray-500">{{ $session->name }} · {{ $session->targets->count() }} 靶</p></div>
            </div>
            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                @foreach($session->targets as $target)
                    <article class="rounded-xl border p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div><h3 class="font-semibold">靶號 {{ $target->target_number }}</h3><p class="mt-1 text-xs text-gray-500">計分 {{ $target->last_completed_end }} / {{ $session->totalEnds() }} 趟</p></div>
                            <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $target->status === 'dns' ? 'bg-amber-100 text-amber-700' : ($target->judge_status === 'confirmed' ? 'bg-green-100 text-green-700' : ($target->judge_status === 'disputed' ? 'bg-red-100 text-red-700' : ($target->judge_status === 'reviewed' ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-600'))) }}">{{ $target->status === 'dns' ? '全靶 DNS・無需核對' : (['pending'=>'待核對','reviewed'=>'裁判已核對','confirmed'=>'主裁判已簽核','disputed'=>'成績爭議'][$target->judge_status] ?? $target->judge_status) }}</span>
                        </div>
                        @php
                            $twoRounds = (int) $session->total_arrows === 72
                                && (int) $session->arrows_per_end === 6;
                        @endphp
                        <div class="mt-3 divide-y rounded-xl border bg-gray-50 px-3 sm:px-4">
                            @foreach($target->assignments as $assignment)
                                @php
                                    $entries = $assignment->registration?->scoreEntries ?? collect();
                                    $scoreStats = function ($scoreEntries): array {
                                        $scores = $scoreEntries->flatMap(fn ($entry) => $entry->scores ?? []);
                                        return [
                                            'total' => (int) $scoreEntries->sum('end_total'),
                                            'ten' => $scores->filter(fn ($score) => (string) $score === '10')->count(),
                                            'x' => $scores->filter(fn ($score) => strtoupper((string) $score) === 'X')->count(),
                                        ];
                                    };
                                    $totalStats = $scoreStats($entries);
                                    $firstRoundStats = $twoRounds ? $scoreStats($entries->where('end_number', '<=', 6)) : null;
                                    $secondRoundStats = $twoRounds ? $scoreStats($entries->where('end_number', '>', 6)) : null;
                                @endphp
                                <div class="py-4">
                                    <div class="flex items-center justify-between gap-3">
                                        <p class="font-semibold">{{ $target->target_number.$assignment->position }} {{ $assignment->registration?->name }}</p>
                                        <span class="shrink-0 text-xs text-gray-500">{{ $entries->count() }} / {{ $session->totalEnds() }} 趟</span>
                                    </div>

                                    @if($twoRounds)
                                        <div class="mt-3 grid grid-cols-3 gap-2">
                                            <div class="rounded-xl bg-white p-3 text-center">
                                                <p class="text-xs text-gray-500">上半局</p>
                                                <p class="mt-1 text-xl font-bold tabular-nums text-gray-900">{{ $firstRoundStats['total'] }}<span class="ml-0.5 text-xs font-medium text-gray-400">分</span></p>
                                                <div class="mt-2 flex justify-center gap-1.5">
                                                    <span class="rounded-lg bg-amber-100 px-2 py-1 text-xs font-medium text-amber-900">10 <strong class="ml-0.5 tabular-nums">{{ $firstRoundStats['ten'] }}</strong></span>
                                                    <span class="rounded-lg bg-orange-100 px-2 py-1 text-xs font-medium text-orange-900">X <strong class="ml-0.5 tabular-nums">{{ $firstRoundStats['x'] }}</strong></span>
                                                </div>
                                            </div>
                                            <div class="rounded-xl bg-white p-3 text-center">
                                                <p class="text-xs text-gray-500">下半局</p>
                                                <p class="mt-1 text-xl font-bold tabular-nums text-gray-900">{{ $secondRoundStats['total'] }}<span class="ml-0.5 text-xs font-medium text-gray-400">分</span></p>
                                                <div class="mt-2 flex justify-center gap-1.5">
                                                    <span class="rounded-lg bg-amber-100 px-2 py-1 text-xs font-medium text-amber-900">10 <strong class="ml-0.5 tabular-nums">{{ $secondRoundStats['ten'] }}</strong></span>
                                                    <span class="rounded-lg bg-orange-100 px-2 py-1 text-xs font-medium text-orange-900">X <strong class="ml-0.5 tabular-nums">{{ $secondRoundStats['x'] }}</strong></span>
                                                </div>
                                            </div>
                                            <div class="rounded-xl bg-indigo-50 p-3 text-center ring-1 ring-indigo-100">
                                                <p class="text-xs font-medium text-indigo-600">全場</p>
                                                <p class="mt-1 text-xl font-bold tabular-nums text-indigo-900">{{ $totalStats['total'] }}<span class="ml-0.5 text-xs font-medium text-indigo-400">分</span></p>
                                                <div class="mt-2 flex justify-center gap-1.5">
                                                    <span class="rounded-lg bg-amber-100 px-2 py-1 text-xs font-medium text-amber-900">10 <strong class="ml-0.5 tabular-nums">{{ $totalStats['ten'] }}</strong></span>
                                                    <span class="rounded-lg bg-orange-100 px-2 py-1 text-xs font-medium text-orange-900">X <strong class="ml-0.5 tabular-nums">{{ $totalStats['x'] }}</strong></span>
                                                </div>
                                            </div>
                                        </div>
                                    @else
                                        <div class="mt-3 rounded-xl bg-white p-3 text-center">
                                            <p class="text-xs text-gray-500">總分</p>
                                            <p class="mt-1 text-2xl font-bold tabular-nums text-gray-900">{{ $totalStats['total'] }}<span class="ml-0.5 text-xs font-medium text-gray-400">分</span></p>
                                            <div class="mt-2 flex justify-center gap-2">
                                                <span class="rounded-lg bg-amber-100 px-3 py-1 text-xs font-medium text-amber-900">10 <strong class="ml-1 tabular-nums">{{ $totalStats['ten'] }}</strong></span>
                                                <span class="rounded-lg bg-orange-100 px-3 py-1 text-xs font-medium text-orange-900">X <strong class="ml-1 tabular-nums">{{ $totalStats['x'] }}</strong></span>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        @if($target->judge_note)<p class="mt-3 rounded-lg bg-amber-50 p-3 text-xs text-amber-800">裁判備註：{{ $target->judge_note }}</p>@endif
                        @if($target->status !== 'dns')<form method="POST" action="{{ route('organizer.events.judging.targets.update', [$event, $target]) }}" class="mt-4 space-y-2">
                            @csrf @method('PATCH')
                            <textarea name="judge_note" rows="2" class="w-full rounded-xl border-gray-300 text-sm" placeholder="有爭議時必須填寫原因">{{ old('judge_note', $target->judge_note) }}</textarea>
                            <div class="grid {{ $canConfirm ? 'grid-cols-3' : 'grid-cols-2' }} gap-2">
                                <button name="judge_status" value="reviewed" class="min-h-11 rounded-xl border border-indigo-200 px-3 text-xs font-medium text-indigo-700">裁判核對完成</button>
                                <button name="judge_status" value="disputed" class="min-h-11 rounded-xl border border-red-200 px-3 text-xs font-medium text-red-700">標記爭議</button>
                                @if($canConfirm)<button name="judge_status" value="confirmed" class="min-h-11 rounded-xl bg-green-600 px-3 text-xs font-medium text-white">主裁判簽核</button>@endif
                            </div>
                        </form>@endif
                    </article>
                @endforeach
            </div>
        </section>
    @empty
        <div class="rounded-2xl border border-dashed bg-white p-8 text-center text-sm text-gray-500">尚未完成排靶，目前沒有可核對的靶位。</div>
    @endforelse
</div>
@endsection
