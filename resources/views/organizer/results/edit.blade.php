@extends('layouts.app')

@section('title', $registration->name.' 成績明細')

@section('content')
@php
    $entries = $registration->scoreEntries->keyBy('end_number');
    $published = $registration->result_published_at !== null;
    $editable = $canCorrect;
    $twoRounds = $requiredEnds === 12 && $arrowsPerEnd === 6;
@endphp
<div class="mx-auto max-w-6xl space-y-5 px-4 py-6 sm:px-6 sm:py-8">
    <div>
        <a href="{{ route('organizer.events.results.index', $event) }}" class="inline-flex min-h-11 items-center text-sm font-medium text-indigo-600">← 返回成績核對</a>
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div><h1 class="text-2xl font-bold">{{ $registration->name }}・完整成績</h1><p class="mt-1 text-sm text-gray-500">{{ $registration->event_group?->name }}{{ $registration->scoringAssignment?->target ? ' / 靶位 '.$registration->scoringAssignment->target->target_number.$registration->scoringAssignment->position : '' }}</p></div>
            @if($published)<span class="rounded-full bg-green-100 px-3 py-1 text-xs font-medium text-green-700">正式成績已發布・唯讀</span>@elseif(!$editable)<span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600">僅供查看</span>@endif
        </div>
    </div>

    @if($errors->any())<div class="rounded-xl bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>@endif

    <section class="grid grid-cols-3 gap-3">
        <div class="rounded-2xl border bg-white p-4"><p class="text-xs text-gray-500">總分</p><p id="score-total" class="mt-1 text-3xl font-bold">{{ $stats['total'] }}</p></div>
        <div class="rounded-2xl border bg-white p-4"><p class="text-xs text-gray-500">10</p><p id="score-ten-count" class="mt-1 text-3xl font-bold">{{ $stats['ten_count'] }}</p></div>
        <div class="rounded-2xl border bg-white p-4"><p class="text-xs text-gray-500">X</p><p id="score-x-count" class="mt-1 text-3xl font-bold">{{ $stats['x_count'] }}</p></div>
    </section>

    <form id="score-correction-form" method="POST" action="{{ route('organizer.events.results.registrations.update', [$event, $registration]) }}" class="space-y-5 {{ $editable ? 'pb-72' : '' }}" onsubmit="return confirm('儲存後會撤銷既有成績確認與裁判簽核，確定修正？')">
        @csrf @method('PATCH')
        <div class="grid items-start gap-5 {{ $twoRounds ? 'lg:grid-cols-2' : '' }}">
            @foreach($twoRounds ? [['上半局', 1, 6], ['下半局', 7, 12]] : [['成績明細', 1, $requiredEnds]] as [$roundLabel, $fromEnd, $toEnd])
                <section class="overflow-hidden rounded-2xl border bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b bg-gray-50 px-4 py-3">
                        <h2 class="font-semibold text-gray-900">{{ $roundLabel }}</h2>
                        <span class="text-xs text-gray-500">{{ $toEnd - $fromEnd + 1 }} 趟・每趟 {{ $arrowsPerEnd }} 箭</span>
                    </div>
                    <div class="divide-y">
                        @foreach(range($fromEnd, $toEnd) as $end)
                            @php
                                $entry = $entries->get($end);
                            @endphp
                            <div class="score-end grid grid-cols-[2.5rem_minmax(0,1fr)_3.5rem] items-center gap-2 px-3 py-3 sm:grid-cols-[3.5rem_minmax(0,1fr)_4rem] sm:px-4" data-end="{{ $end }}">
                                <div class="text-center"><p class="text-[10px] text-gray-400">趟次</p><p class="font-bold text-gray-700">{{ $twoRounds ? ($end > 6 ? $end - 6 : $end) : $end }}</p></div>
                                <div class="grid gap-1.5" style="grid-template-columns: repeat({{ $arrowsPerEnd }}, minmax(0, 1fr));">
                                    @for($arrow = 0; $arrow < $arrowsPerEnd; $arrow++)
                                        @php
                                            $value = old('ends.'.$end.'.'.$arrow, $entry?->scores[$arrow] ?? '');
                                        @endphp
                                        <input name="ends[{{ $end }}][]" value="{{ $value }}" readonly inputmode="none" @disabled(!$editable)
                                               placeholder="＿" aria-label="{{ $roundLabel }}第 {{ $twoRounds ? ($end > 6 ? $end - 6 : $end) : $end }} 趟第 {{ $arrow + 1 }} 箭"
                                               class="score-value h-10 min-w-0 touch-manipulation cursor-pointer select-none rounded-lg border-gray-300 p-0 text-center text-sm font-bold placeholder:text-gray-300 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                                    @endfor
                                </div>
                                <div class="text-right"><p class="text-[10px] text-gray-400">小計</p><p class="end-total text-lg font-bold tabular-nums">{{ $entry?->end_total ?? 0 }}</p></div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>

        @if($editable)
            <section id="correction-reason" class="rounded-2xl border bg-white p-4 shadow-sm">
                <label class="text-sm font-medium">修正原因 *</label>
                <textarea name="correction_reason" required maxlength="500" rows="3" class="mt-2 w-full rounded-xl border-gray-300" placeholder="例如：第 4 趟紙本記分卡核對為 10、9、9、8、8、7">{{ old('correction_reason') }}</textarea>
                <p class="mt-2 text-xs text-amber-700">儲存後會撤銷主辦確認及該靶位裁判簽核，必須重新核對後才能發布。</p>
                <button class="mt-4 min-h-12 w-full rounded-xl bg-indigo-600 px-5 text-sm font-semibold text-white">儲存成績修正</button>
            </section>
        @endif
    </form>

    <section class="rounded-2xl border bg-white shadow-sm {{ $editable ? 'mb-72' : '' }}">
        <div class="border-b px-4 py-4 sm:px-5">
            <h2 class="font-semibold text-gray-900">成績修正紀錄</h2>
            <p class="mt-1 text-xs text-gray-500">每次修正的操作人、原因及逐趟前後差異都會永久保留。</p>
        </div>
        <div class="divide-y">
            @forelse($correctionLogs as $log)
                @php
                    $metadata = $log->metadata ?? [];
                    $scoreChanges = is_array($metadata['changes'] ?? null) ? $metadata['changes'] : [];
                @endphp
                <details class="group px-4 py-4 sm:px-5" @if($loop->first) open @endif>
                    <summary class="flex cursor-pointer list-none flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-gray-900">{{ $log->user?->display_name ?? '系統' }}・{{ $log->created_at->format('Y-m-d H:i:s') }}</p>
                            <p class="mt-1 text-xs text-gray-500">原因：{{ $metadata['reason'] ?? '未記錄' }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium">{{ $metadata['old_total'] ?? '—' }} → {{ $metadata['new_total'] ?? '—' }} 分</span>
                            <span class="text-gray-400 transition group-open:rotate-180">⌄</span>
                        </div>
                    </summary>
                    <div class="mt-4 space-y-2">
                        @forelse($scoreChanges as $scoreChange)
                            @php
                                $before = $scoreChange['before'] ?? null;
                                $after = $scoreChange['after'] ?? null;
                            @endphp
                            <div class="grid gap-2 rounded-xl bg-gray-50 p-3 text-sm sm:grid-cols-[4rem_1fr_auto_1fr] sm:items-center">
                                <p class="font-semibold text-gray-700">第 {{ $scoreChange['end_number'] ?? '—' }} 趟</p>
                                <div><p class="text-[10px] text-gray-400">修改前</p><p class="font-mono font-semibold text-red-700">{{ $before ? implode(' / ', $before['scores'] ?? []).'（'.($before['end_total'] ?? 0).'）' : '無成績' }}</p></div>
                                <span class="hidden text-gray-400 sm:block">→</span>
                                <div><p class="text-[10px] text-gray-400">修改後</p><p class="font-mono font-semibold text-green-700">{{ $after ? implode(' / ', $after['scores'] ?? []).'（'.($after['end_total'] ?? 0).'）' : '已移除' }}</p></div>
                            </div>
                        @empty
                            <p class="rounded-xl bg-gray-50 p-3 text-sm text-gray-500">此為舊版修正紀錄，僅保留修改前後總分。</p>
                        @endforelse
                    </div>
                </details>
            @empty
                <p class="p-6 text-center text-sm text-gray-500">尚無成績修正紀錄。</p>
            @endforelse
        </div>
    </section>

    @if($editable)
        <section class="fixed inset-x-0 bottom-0 z-30 mx-auto max-w-3xl border-t bg-white/95 p-3 pb-[max(.75rem,env(safe-area-inset-bottom))] shadow-[0_-6px_24px_rgba(15,23,42,.12)] backdrop-blur sm:bottom-3 sm:rounded-2xl sm:border">
            <p id="keypad-status" class="mb-2 text-center text-xs text-gray-500">請先點選要修正的箭值</p>
            <div class="grid grid-cols-4 gap-2">
                @foreach([
                    ['X','X','border-yellow-200 bg-yellow-50 text-yellow-900'],
                    ['10','10','border-yellow-200 bg-yellow-50 text-yellow-900'],
                    ['9','9','border-yellow-200 bg-yellow-50 text-yellow-900'],
                    ['BKSP','⌫','border-gray-300 bg-white text-gray-900'],
                    ['8','8','border-red-200 bg-red-50 text-red-900'],
                    ['7','7','border-red-200 bg-red-50 text-red-900'],
                    ['6','6','border-blue-200 bg-blue-50 text-blue-900'],
                    ['5','5','border-blue-200 bg-blue-50 text-blue-900'],
                    ['4','4','border-gray-300 bg-gray-100 text-gray-900'],
                    ['3','3','border-gray-300 bg-gray-100 text-gray-900'],
                    ['2','2','border-gray-300 bg-white text-gray-900'],
                    ['1','1','border-gray-300 bg-white text-gray-900'],
                ] as [$key, $label, $color])
                    <button type="button" data-score-key="{{ $key }}" class="min-h-12 touch-manipulation rounded-xl border text-lg font-bold active:brightness-95 {{ $color }}">{{ $label }}</button>
                @endforeach
                <button type="button" data-score-key="M" class="col-span-2 min-h-12 rounded-xl border border-green-200 bg-green-50 text-lg font-bold text-green-800">M</button>
                <button type="button" data-score-key="DONE" class="col-span-2 min-h-12 rounded-xl bg-indigo-600 text-sm font-semibold text-white">完成輸入</button>
            </div>
        </section>
    @endif
</div>
<script>
(() => {
    const inputs = Array.from(document.querySelectorAll('.score-value:not(:disabled)'));
    if (!inputs.length) return;
    const keypadStatus = document.getElementById('keypad-status');
    let activeIndex = null;
    const scoreNumber = value => value === 'X' ? 10 : (value === 'M' || !value ? 0 : Number(value));
    const recalculate = () => {
        document.querySelectorAll('.score-end').forEach(end => {
            const total = Array.from(end.querySelectorAll('.score-value')).reduce((sum, input) => sum + scoreNumber(input.value), 0);
            end.querySelector('.end-total').textContent = total;
        });
        document.getElementById('score-total').textContent = inputs.reduce((sum, input) => sum + scoreNumber(input.value), 0);
        document.getElementById('score-ten-count').textContent = inputs.filter(input => input.value === '10').length;
        document.getElementById('score-x-count').textContent = inputs.filter(input => input.value.toUpperCase() === 'X').length;
    };
    const selectInput = index => {
        activeIndex = Math.max(0, Math.min(inputs.length - 1, index));
        inputs.forEach(input => input.classList.remove('ring-2', 'ring-indigo-500', 'bg-indigo-50'));
        const input = inputs[activeIndex];
        input.classList.add('ring-2', 'ring-indigo-500', 'bg-indigo-50');
        keypadStatus.textContent = input.getAttribute('aria-label');
    };
    inputs.forEach((input, index) => {
        input.addEventListener('pointerdown', event => { event.preventDefault(); selectInput(index); });
        input.addEventListener('click', event => event.preventDefault());
    });
    document.querySelectorAll('[data-score-key]').forEach(key => key.addEventListener('click', () => {
        const action = key.dataset.scoreKey;
        if (action === 'DONE') {
            document.getElementById('correction-reason').scrollIntoView({behavior: 'smooth', block: 'center'});
            document.querySelector('[name="correction_reason"]').focus();
            return;
        }
        if (activeIndex === null) return;
        if (action === 'BKSP') {
            const indexToClear = inputs[activeIndex].value ? activeIndex : Math.max(0, activeIndex - 1);
            inputs[indexToClear].value = '';
            selectInput(indexToClear);
        } else {
            inputs[activeIndex].value = action;
            if (activeIndex < inputs.length - 1) selectInput(activeIndex + 1);
        }
        recalculate();
    }));
    recalculate();
})();
</script>
@endsection
