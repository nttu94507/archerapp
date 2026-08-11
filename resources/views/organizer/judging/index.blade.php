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
        @php($targets = $event->scoringSessions->flatMap->targets)
        <div class="rounded-2xl border bg-white p-4"><p class="text-xs text-gray-500">全部靶位</p><p class="mt-1 text-2xl font-bold">{{ $targets->count() }}</p></div>
        <div class="rounded-2xl border bg-white p-4"><p class="text-xs text-gray-500">待主裁判簽核</p><p class="mt-1 text-2xl font-bold text-amber-700">{{ $targets->where('judge_status', '!=', 'confirmed')->count() }}</p></div>
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
                            <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $target->judge_status === 'confirmed' ? 'bg-green-100 text-green-700' : ($target->judge_status === 'disputed' ? 'bg-red-100 text-red-700' : ($target->judge_status === 'reviewed' ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-600')) }}">{{ ['pending'=>'待核對','reviewed'=>'裁判已核對','confirmed'=>'主裁判已簽核','disputed'=>'成績爭議'][$target->judge_status] ?? $target->judge_status }}</span>
                        </div>
                        <div class="mt-3 overflow-x-auto">
                            <table class="min-w-full text-sm"><thead class="text-left text-xs text-gray-500"><tr><th class="py-2">選手</th><th class="py-2">波數</th><th class="py-2 text-right">總分</th></tr></thead><tbody class="divide-y">
                            @foreach($target->assignments as $assignment)
                                <tr><td class="py-2 font-medium">{{ $target->target_number.$assignment->position }} {{ $assignment->registration?->name }}</td><td class="py-2">{{ $assignment->registration?->scoreEntries->count() ?? 0 }}</td><td class="py-2 text-right font-semibold">{{ $assignment->registration?->scoreEntries->sum('end_total') ?? 0 }}</td></tr>
                            @endforeach
                            </tbody></table>
                        </div>
                        @if($target->judge_note)<p class="mt-3 rounded-lg bg-amber-50 p-3 text-xs text-amber-800">裁判備註：{{ $target->judge_note }}</p>@endif
                        <form method="POST" action="{{ route('organizer.events.judging.targets.update', [$event, $target]) }}" class="mt-4 space-y-2">
                            @csrf @method('PATCH')
                            <textarea name="judge_note" rows="2" class="w-full rounded-xl border-gray-300 text-sm" placeholder="有爭議時必須填寫原因">{{ old('judge_note', $target->judge_note) }}</textarea>
                            <div class="grid {{ $canConfirm ? 'grid-cols-3' : 'grid-cols-2' }} gap-2">
                                <button name="judge_status" value="reviewed" class="min-h-11 rounded-xl border border-indigo-200 px-3 text-xs font-medium text-indigo-700">裁判核對完成</button>
                                <button name="judge_status" value="disputed" class="min-h-11 rounded-xl border border-red-200 px-3 text-xs font-medium text-red-700">標記爭議</button>
                                @if($canConfirm)<button name="judge_status" value="confirmed" class="min-h-11 rounded-xl bg-green-600 px-3 text-xs font-medium text-white">主裁判簽核</button>@endif
                            </div>
                        </form>
                    </article>
                @endforeach
            </div>
        </section>
    @empty
        <div class="rounded-2xl border border-dashed bg-white p-8 text-center text-sm text-gray-500">尚未完成排靶，目前沒有可核對的靶位。</div>
    @endforelse
</div>
@endsection
