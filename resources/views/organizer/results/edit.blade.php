@extends('layouts.app')

@section('title', $registration->name.' 成績明細')

@section('content')
@php
    $entries = $registration->scoreEntries->keyBy('end_number');
    $published = $registration->result_published_at !== null;
    $twoRounds = $requiredEnds === 12 && $arrowsPerEnd === 6;
@endphp
<div class="mx-auto max-w-6xl space-y-5 px-4 py-6 sm:px-6 sm:py-8">
    <div>
        <a href="{{ route('organizer.events.results.index', $event) }}" class="inline-flex min-h-11 items-center text-sm font-medium text-indigo-600">← 返回成績核對</a>
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div><h1 class="text-2xl font-bold">{{ $registration->name }}・完整成績</h1><p class="mt-1 text-sm text-gray-500">{{ $registration->event_group?->name }}{{ $registration->scoringAssignment?->target ? ' / 靶位 '.$registration->scoringAssignment->target->target_number.$registration->scoringAssignment->position : '' }}</p></div>
            @if($published)<span class="rounded-full bg-green-100 px-3 py-1 text-xs font-medium text-green-700">正式成績已發布・唯讀</span>@endif
        </div>
    </div>

    @if($errors->any())<div class="rounded-xl bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>@endif

    <section class="grid grid-cols-3 gap-3">
        <div class="rounded-2xl border bg-white p-4"><p class="text-xs text-gray-500">總分</p><p class="mt-1 text-3xl font-bold">{{ $stats['total'] }}</p></div>
        <div class="rounded-2xl border bg-white p-4"><p class="text-xs text-gray-500">10</p><p class="mt-1 text-3xl font-bold">{{ $stats['ten_count'] }}</p></div>
        <div class="rounded-2xl border bg-white p-4"><p class="text-xs text-gray-500">X</p><p class="mt-1 text-3xl font-bold">{{ $stats['x_count'] }}</p></div>
    </section>

    <form method="POST" action="{{ route('organizer.events.results.registrations.update', [$event, $registration]) }}" class="space-y-5" onsubmit="return confirm('儲存後會撤銷既有成績確認與裁判簽核，確定修正？')">
        @csrf @method('PATCH')
        @foreach(range(1, $requiredEnds) as $end)
            @if($twoRounds && in_array($end, [1, 7], true))
                <h2 class="pt-2 text-lg font-semibold text-gray-900">{{ $end === 1 ? '上半局・第 1～6 趟' : '下半局・第 7～12 趟' }}</h2>
            @endif
            @php($entry = $entries->get($end))
            <section class="score-end rounded-2xl border bg-white p-4 shadow-sm" data-end="{{ $end }}">
                <div class="flex items-center justify-between"><h3 class="font-semibold">第 {{ $end }} 趟</h3><p class="end-total text-lg font-bold">{{ $entry?->end_total ?? 0 }} 分</p></div>
                <div class="mt-3 grid gap-2" style="grid-template-columns: repeat({{ $arrowsPerEnd }}, minmax(0, 1fr));">
                    @for($arrow = 0; $arrow < $arrowsPerEnd; $arrow++)
                        @php($value = old('ends.'.$end.'.'.$arrow, $entry?->scores[$arrow] ?? ''))
                        <select name="ends[{{ $end }}][]" @disabled($published) class="score-value min-h-11 min-w-0 rounded-xl border-gray-300 px-1 text-center text-sm font-semibold">
                            <option value="">—</option>
                            @foreach(['X','10','9','8','7','6','5','4','3','2','1','M'] as $score)<option value="{{ $score }}" @selected((string) $value === $score)>{{ $score }}</option>@endforeach
                        </select>
                    @endfor
                </div>
            </section>
        @endforeach

        @unless($published)
            <section class="rounded-2xl border bg-white p-4 shadow-sm">
                <label class="text-sm font-medium">修正原因 *</label>
                <textarea name="correction_reason" required maxlength="500" rows="3" class="mt-2 w-full rounded-xl border-gray-300" placeholder="例如：第 4 趟紙本記分卡核對為 10、9、9、8、8、7">{{ old('correction_reason') }}</textarea>
                <p class="mt-2 text-xs text-amber-700">儲存後會撤銷主辦確認及該靶位裁判簽核，必須重新核對後才能發布。</p>
                <button class="mt-4 min-h-12 w-full rounded-xl bg-indigo-600 px-5 text-sm font-semibold text-white">儲存成績修正</button>
            </section>
        @endunless
    </form>
</div>
<script>
document.querySelectorAll('.score-end').forEach(end => {
    const recalculate = () => {
        const total = Array.from(end.querySelectorAll('.score-value')).reduce((sum, select) => sum + (select.value === 'X' ? 10 : (select.value === 'M' || !select.value ? 0 : Number(select.value))), 0);
        end.querySelector('.end-total').textContent = total + ' 分';
    };
    end.querySelectorAll('.score-value').forEach(select => select.addEventListener('change', recalculate));
});
</script>
@endsection
