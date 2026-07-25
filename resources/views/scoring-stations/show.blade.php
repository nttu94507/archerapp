@extends('layouts.app')

@section('title', '靶號 '.$target->target_number.' 計分台')

@section('content')
@php
    $session = $target->session;
    $totalEnds = $session->totalEnds();
    $isComplete = $target->status === 'completed';
@endphp

<div id="scoring-station" class="mx-auto flex min-h-[calc(100dvh-4rem)] max-w-7xl flex-col bg-gray-50 pb-20 sm:px-4 sm:py-4">
    <header class="shrink-0 border-b bg-white px-4 py-4 sm:rounded-t-2xl sm:border">
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
                <h1 class="truncate text-xl font-bold text-gray-900">{{ $session->event->name }}</h1>
                <p class="mt-2 text-sm text-gray-500">{{ $session->group?->name }} / 靶號 {{ str_pad($target->target_number, 2, '0', STR_PAD_LEFT) }}</p>
            </div>
            <div class="shrink-0 text-right">
                <span class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span>{{ $isComplete ? '計分完成' : '計分中' }}
                </span>
            </div>
        </div>
    </header>

    <main class="min-h-0 flex-1 sm:rounded-b-2xl sm:border-x sm:border-b">
        <section id="overview-panel" data-tab-panel="overview" class="h-full bg-white">
            <div class="divide-y">
                @foreach($target->assignments as $assignment)
                    @php
                        $historyEntries = $assignment->registration->scoreEntries;
                        $historyTotal = $historyEntries->sum('end_total');
                        $historyScores = $historyEntries->flatMap(fn ($entry) => $entry->scores ?? []);
                        $historyX = $historyScores->filter(fn ($score) => strtoupper((string) $score) === 'X')->count();
                        $historyTenPlus = $historyScores->filter(fn ($score) => strtoupper((string) $score) === 'X' || (string) $score === '10')->count();
                    @endphp
                    <article role="button" tabindex="0" data-overview-registration="{{ $assignment->registration->id }}"
                             aria-label="前往 {{ $assignment->registration->name }} 的計分區域"
                             class="group grid cursor-pointer grid-cols-[2.25rem_minmax(0,1fr)_auto] gap-2 px-4 py-4 transition hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-indigo-500 sm:grid-cols-[3rem_minmax(0,1fr)_8rem] sm:gap-4">
                        <div class="pt-1 text-lg font-bold text-indigo-600">{{ $target->target_number.$assignment->position }}</div>
                        <div class="min-w-0">
                            <div>
                                <h2 class="text-lg font-bold text-gray-900">{{ $assignment->registration->name }}</h2>
                            </div>

                            @if($historyEntries->isEmpty())
                                <div class="mt-3 font-mono text-sm tracking-wider text-gray-300">
                                    {{ collect(range(1, $session->arrows_per_end))->map(fn () => '—')->implode('　') }}
                                </div>
                                <p class="mt-1 text-xs text-gray-400">尚無已送出的成績</p>
                            @else
                                <div class="mt-3 space-y-2">
                                    @foreach($historyEntries as $historyEntry)
                                        <div class="flex min-w-0 items-center gap-2 text-sm">
                                            <span class="w-12 shrink-0 text-xs text-gray-400">第 {{ $historyEntry->end_number }} 趟</span>
                                            <div class="flex min-w-0 flex-1 flex-wrap gap-1">
                                                @foreach($historyEntry->scores ?? [] as $score)
                                                    <span class="inline-flex h-7 min-w-7 items-center justify-center rounded-md bg-gray-100 px-1.5 font-mono font-semibold text-gray-800">{{ $score }}</span>
                                                @endforeach
                                            </div>
                                            <span class="shrink-0 font-semibold text-gray-700">{{ $historyEntry->end_total }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            <p class="mt-3 text-xs text-gray-500">10+X {{ $historyTenPlus }} · X {{ $historyX }}</p>
                        </div>
                        <div class="pt-1 text-right">
                            <p class="text-2xl font-bold tabular-nums text-gray-900">{{ $historyTotal }}</p>
                            <p class="text-xs text-gray-400">目前總分</p>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <section id="scoring-panel" data-tab-panel="scoring" class="hidden h-full bg-gray-50 p-3 sm:p-4">
            @if($isComplete)
                <div class="rounded-2xl border bg-white p-8 text-center shadow-sm">
                    <p class="text-4xl text-emerald-500">✓</p>
                    <h2 class="mt-3 text-xl font-bold">本靶已完成全部計分</h2>
                    <p class="mt-2 text-sm text-gray-500">請保留紙本記分卡，並交由主辦方進行最終核對。</p>
                </div>
            @else
                <form method="POST" action="{{ route('scoring-stations.ends.store', $target->access_token) }}" id="station-form" class="mx-auto flex max-w-5xl flex-col gap-3">
                    @csrf
                    <input type="hidden" name="end_number" value="{{ $endNumber }}">

                    <div class="rounded-2xl border bg-white p-3 shadow-sm">
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-gray-900">第 {{ $endNumber }} / {{ $totalEnds }} 趟</p>
                                <p class="text-xs text-gray-500">每位選手輸入 {{ $session->arrows_per_end }} 支箭</p>
                            </div>
                            <div class="text-right">
                                <p id="active-arrow-label" class="text-xs text-gray-500">從第一個空格開始</p>
                                <p id="active-arrow-value" class="text-xl font-bold text-indigo-700">—</p>
                            </div>
                        </div>

                        <div class="divide-y rounded-xl border">
                            @foreach($target->assignments as $assignment)
                                <div class="athlete-card grid grid-cols-[1.25rem_4.5rem_minmax(0,1fr)_2.5rem] items-center gap-1.5 px-2 py-1.5 sm:grid-cols-[2rem_8rem_minmax(0,1fr)_3.5rem] sm:gap-2" data-registration="{{ $assignment->registration->id }}">
                                    <span class="font-bold text-indigo-600">{{ $assignment->position }}</span>
                                    <div class="min-w-0">
                                        <p class="truncate text-xs font-semibold sm:text-sm">{{ $assignment->registration->name }}</p>
                                        <p class="text-[10px] leading-3 text-gray-400">{{ $target->target_number.$assignment->position }}</p>
                                    </div>
                                    <p class="end-total col-start-4 row-start-1 text-right text-base font-bold tabular-nums sm:text-lg">0</p>
                                    <div class="col-start-3 row-start-1 grid gap-1" style="grid-template-columns: repeat({{ $session->arrows_per_end }}, minmax(0, 1fr));">
                                        @for($arrow = 0; $arrow < $session->arrows_per_end; $arrow++)
                                            <input name="scores[{{ $assignment->registration->id }}][]" readonly inputmode="none" maxlength="2"
                                                   placeholder="＿" aria-label="{{ $assignment->registration->name }} 第 {{ $arrow + 1 }} 箭"
                                                   class="score-input h-9 min-w-0 touch-manipulation cursor-pointer select-none rounded-md border-gray-300 p-0.5 text-center text-sm font-bold placeholder:text-gray-300 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500 sm:h-10 sm:text-base">
                                        @endfor
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <section class="rounded-2xl border bg-white p-3 shadow-sm">
                        <div class="grid grid-cols-4 gap-2">
                            @foreach([
                                ['X','X'], ['10','10'], ['9','9'], ['BKSP','⌫'],
                                ['8','8'], ['7','7'], ['6','6'], ['PREV','←'],
                                ['5','5'], ['4','4'], ['3','3'], ['NEXT','→'],
                                ['2','2'], ['1','1'], ['M','M'], [null,null],
                            ] as [$key, $label])
                                @if($key)
                                    <button type="button" data-key="{{ $key }}"
                                            class="score-key min-h-14 touch-manipulation select-none rounded-xl border px-2 text-lg font-bold active:bg-indigo-100 {{ $key === 'NEXT' ? 'border-indigo-200 bg-indigo-50 text-indigo-700' : ($key === 'M' ? 'border-red-200 bg-red-50 text-red-700' : 'border-gray-300 bg-white text-gray-900') }}">{{ $label }}</button>
                                @else
                                    <span aria-hidden="true"></span>
                                @endif
                            @endforeach
                        </div>
                        <button type="button" data-key="SUBMIT" class="score-key mt-3 min-h-12 w-full rounded-xl bg-indigo-600 px-3 text-sm font-semibold text-white">核對並送出本趟</button>
                        <p id="draft-status" class="mt-2 text-center text-xs text-gray-500">輸入會暫存在這台設備；刪除會退回上一格。</p>
                    </section>
                </form>
            @endif
        </section>
    </main>

    <nav class="fixed inset-x-0 bottom-0 z-30 mx-auto grid max-w-7xl grid-cols-2 border-t bg-white/95 px-3 pb-[max(.5rem,env(safe-area-inset-bottom))] pt-2 shadow-[0_-4px_20px_rgba(15,23,42,.08)] backdrop-blur sm:bottom-4 sm:rounded-2xl sm:border">
        <button type="button" data-tab="overview" class="station-tab min-h-12 rounded-xl text-sm font-semibold text-indigo-700">
            <span class="tab-indicator mx-auto mb-1 block h-1 w-6 rounded-full bg-indigo-600"></span>總覽
        </button>
        <button type="button" data-tab="scoring" class="station-tab min-h-12 rounded-xl text-sm font-semibold text-gray-400">
            <span class="tab-indicator mx-auto mb-1 block h-1 w-6 rounded-full bg-transparent"></span>計分
        </button>
    </nav>
</div>

<script>
(() => {
    const tabs = Array.from(document.querySelectorAll('.station-tab'));
    const panels = Array.from(document.querySelectorAll('[data-tab-panel]'));
    const switchTab = name => {
        panels.forEach(panel => panel.classList.toggle('hidden', panel.dataset.tabPanel !== name));
        tabs.forEach(tab => {
            const active = tab.dataset.tab === name;
            tab.classList.toggle('text-indigo-700', active);
            tab.classList.toggle('text-gray-400', !active);
            tab.querySelector('.tab-indicator').classList.toggle('bg-indigo-600', active);
            tab.querySelector('.tab-indicator').classList.toggle('bg-transparent', !active);
        });
        history.replaceState(null, '', name === 'scoring' ? '#scoring' : '#overview');
    };
    tabs.forEach(tab => tab.addEventListener('click', () => switchTab(tab.dataset.tab)));
    switchTab(location.hash === '#scoring' ? 'scoring' : 'overview');

    const form = document.getElementById('station-form');
    if (!form) return;

    const storageKey = 'scoring:{{ $target->access_token }}:end:{{ $endNumber }}';
    const inputs = Array.from(document.querySelectorAll('.score-input'));
    const status = document.getElementById('draft-status');
    const activeLabel = document.getElementById('active-arrow-label');
    const activeValue = document.getElementById('active-arrow-value');
    let activeIndex = 0;
    const jumpToAthlete = registrationId => {
        const card = document.querySelector(`.athlete-card[data-registration="${registrationId}"]`);
        if (!card) return;
        const firstInput = card.querySelector('.score-input');
        const inputIndex = inputs.indexOf(firstInput);
        switchTab('scoring');
        if (inputIndex >= 0) selectInput(inputIndex);
        requestAnimationFrame(() => card.scrollIntoView({behavior: 'smooth', block: 'center'}));
    };
    document.querySelectorAll('[data-overview-registration]').forEach(card => {
        const activate = () => jumpToAthlete(card.dataset.overviewRegistration);
        card.addEventListener('click', activate);
        card.addEventListener('keydown', event => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                activate();
            }
        });
    });
    const valueOf = value => value === 'X' ? 10 : (value === 'M' || !value ? 0 : Number(value));
    const selectInput = index => {
        activeIndex = Math.max(0, Math.min(inputs.length - 1, index));
        inputs.forEach(input => input.classList.remove('ring-2', 'ring-indigo-500', 'bg-indigo-50'));
        const input = inputs[activeIndex];
        input.classList.add('ring-2', 'ring-indigo-500', 'bg-indigo-50');
        const card = input.closest('.athlete-card');
        const athlete = card?.querySelector('p')?.textContent?.trim() || '選手';
        const arrowIndex = Array.from(card.querySelectorAll('.score-input')).indexOf(input) + 1;
        activeLabel.textContent = `${athlete} · 第 ${arrowIndex} 箭`;
        activeValue.textContent = input.value || '—';
    };
    const recalc = () => {
        document.querySelectorAll('.athlete-card').forEach(card => {
            card.querySelector('.end-total').textContent = Array.from(card.querySelectorAll('.score-input')).reduce((sum, input) => sum + valueOf(input.value), 0);
        });
        activeValue.textContent = inputs[activeIndex]?.value || '—';
    };
    const save = () => {
        localStorage.setItem(storageKey, JSON.stringify(inputs.map(input => input.value)));
        status.textContent = '已暫存在這台設備 · ' + new Date().toLocaleTimeString();
        recalc();
    };
    try {
        const saved = JSON.parse(localStorage.getItem(storageKey) || 'null');
        if (Array.isArray(saved)) inputs.forEach((input, index) => input.value = saved[index] || '');
    } catch (error) {}
    inputs.forEach((input, index) => {
        input.addEventListener('pointerdown', event => { event.preventDefault(); selectInput(index); });
        input.addEventListener('click', event => event.preventDefault());
    });
    document.querySelectorAll('.score-key').forEach(key => key.addEventListener('click', () => {
        const action = key.dataset.key;
        const input = inputs[activeIndex];
        if (!input) return;
        if (action === 'SUBMIT') { form.requestSubmit(); return; }
        if (action === 'PREV') {
            selectInput(Math.max(0, activeIndex - 1));
            return;
        }
        if (action === 'NEXT') {
            selectInput(Math.min(inputs.length - 1, activeIndex + 1));
            return;
        }
        if (action === 'BKSP') {
            const indexToClear = input.value ? activeIndex : Math.max(0, activeIndex - 1);
            inputs[indexToClear].value = '';
            selectInput(indexToClear);
            save();
            return;
        }
        input.value = action;
        save();
        if (activeIndex < inputs.length - 1) selectInput(activeIndex + 1);
    }));
    recalc();
    const firstEmpty = inputs.findIndex(input => !input.value);
    selectInput(firstEmpty === -1 ? inputs.length - 1 : firstEmpty);
    form.addEventListener('submit', event => {
        if (!confirm('請確認同靶所有選手都已核對本趟箭值。送出後一般計分台不能修改，確定送出？')) {
            event.preventDefault();
            return;
        }
        inputs.forEach(input => {
            if (!input.value) input.value = 'M';
        });
        save();
        localStorage.removeItem(storageKey);
    });
})();
</script>
@endsection
