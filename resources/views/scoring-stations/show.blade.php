@extends('layouts.app')

@section('title', '靶號 '.$target->target_number.' 計分台')

@section('content')
@php
    $session = $target->session;
    $totalEnds = $session->totalEnds();
    $isComplete = $target->status === 'completed';
    $isRoundBreak = $target->status === 'round_break';
    $isDnsTarget = $target->status === 'dns';
    $usesTwoRounds = $session->total_arrows === 72 && $session->arrows_per_end === 6;
    $roundNumber = $usesTwoRounds && $endNumber > 6 ? 2 : 1;
    $endInRound = $usesTwoRounds && $endNumber > 6 ? $endNumber - 6 : $endNumber;
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
                    <span class="h-2 w-2 rounded-full {{ $isRoundBreak || $isDnsTarget ? 'bg-amber-500' : 'bg-emerald-500' }}"></span>{{ $isComplete ? '計分完成' : ($isRoundBreak ? '上半局完成' : ($isDnsTarget ? '無需計分' : '計分中')) }}
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
                        $historyTen = $historyScores->filter(fn ($score) => (string) $score === '10')->count();
                        $entriesByEnd = $historyEntries->keyBy('end_number');
                    @endphp
                    <article role="button" tabindex="0" data-overview-registration="{{ $assignment->registration->id }}"
                             aria-label="前往 {{ $assignment->registration->name }} 的計分區域"
                             class="group grid cursor-pointer grid-cols-[2.25rem_minmax(0,1fr)_auto] gap-2 px-4 py-4 transition hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-indigo-500 sm:grid-cols-[3rem_minmax(0,1fr)_8rem] sm:gap-4">
                        <div class="pt-1 text-lg font-bold text-indigo-600">{{ $target->target_number.$assignment->position }}</div>
                        <div class="min-w-0">
                            <div>
                                <h2 class="text-lg font-bold text-gray-900">{{ $assignment->registration->name }} @if($assignment->registration->result_status === 'dns')<span class="ml-1 text-sm text-amber-700">DNS</span>@endif</h2>
                            </div>

                            <div class="mt-3 grid grid-cols-6 gap-x-1 gap-y-2">
                                @foreach(range(1, $totalEnds) as $historyEnd)
                                    @php($endEntry = $entriesByEnd->get($historyEnd))
                                    <div class="flex min-w-0 items-center">
                                        <span class="min-w-0 flex-1 text-center font-mono text-sm font-semibold {{ $endEntry ? 'text-gray-800' : 'text-gray-300' }}">{{ $endEntry?->end_total ?? '__' }}</span>
                                        @if($historyEnd % 6 !== 0 && $historyEnd !== $totalEnds)<span class="text-xs text-gray-300">/</span>@endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="pt-1 text-right">
                            <p class="text-2xl font-bold tabular-nums text-gray-900">{{ $historyTotal }}</p>
                            <p class="text-xs text-gray-400">目前總分</p>
                            <p class="mt-2 whitespace-nowrap text-xs text-gray-500">10：{{ $historyTen }} · X：{{ $historyX }}</p>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <section id="scoring-panel" data-tab-panel="scoring" class="hidden h-full bg-gray-50 p-3 sm:p-4">
            @if($isDnsTarget)
                <div class="rounded-2xl border border-amber-200 bg-white p-8 text-center shadow-sm">
                    <p class="text-4xl text-amber-500">DNS</p>
                    <h2 class="mt-3 text-xl font-bold">本靶選手均未報到</h2>
                    <p class="mt-2 text-sm text-gray-500">已保留原靶位供名單核對，但本靶無法輸入分數。</p>
                </div>
            @elseif($isComplete)
                <div class="rounded-2xl border bg-white p-8 text-center shadow-sm">
                    <p class="text-4xl text-emerald-500">✓</p>
                    <h2 class="mt-3 text-xl font-bold">本靶已完成全部計分</h2>
                    <p class="mt-2 text-sm text-gray-500">請保留紙本記分卡，並交由主辦方進行最終核對。</p>
                </div>
            @elseif($isRoundBreak)
                <div class="mx-auto max-w-xl rounded-2xl border border-amber-200 bg-white p-8 text-center shadow-sm">
                    <p class="text-4xl text-amber-500">Ⅱ</p>
                    <h2 class="mt-3 text-xl font-bold">上半局 36 箭已完成</h2>
                    <p class="mt-2 text-sm leading-6 text-gray-500">前 6 趟成績已保存。休息與核對完成後，再由本設備開始下半局，接續記錄第 7～12 趟。</p>
                    <form method="POST" action="{{ route('scoring-stations.second-round.start', $target->access_token) }}" class="mt-6" onsubmit="return confirm('確定開始下半局？開始後將進入第 7 趟計分。')">
                        @csrf
                        <button class="min-h-12 w-full rounded-xl bg-indigo-600 px-5 text-sm font-semibold text-white">開始下半局</button>
                    </form>
                </div>
            @else
                <form method="POST" action="{{ route('scoring-stations.ends.store', $target->access_token) }}" id="station-form" class="mx-auto flex max-w-5xl flex-col gap-3">
                    @csrf
                    <input type="hidden" name="end_number" value="{{ $endNumber }}">

                    @if($usesTwoRounds)
                        <div class="rounded-xl bg-indigo-50 px-4 py-2 text-center text-sm font-semibold text-indigo-700">
                            {{ $roundNumber === 1 ? '上半局' : '下半局' }}・第 {{ $endInRound }} / 6 趟
                        </div>
                    @endif

                    <div class="rounded-2xl border bg-white p-3 shadow-sm">
                        <div class="divide-y rounded-xl border">
                            @foreach($target->assignments as $assignment)
                                @continue($assignment->registration?->result_status === 'dns' || $assignment->registration?->status !== 'checked_in')
                                <div class="athlete-card grid grid-cols-[2rem_4.5rem_minmax(0,1fr)_2.5rem] items-center gap-1.5 px-2 py-1.5 sm:grid-cols-[3rem_8rem_minmax(0,1fr)_3.5rem] sm:gap-2" data-registration="{{ $assignment->registration->id }}">
                                    <span class="font-bold text-indigo-600">{{ $target->target_number.$assignment->position }}</span>
                                    <div class="min-w-0">
                                        <p class="truncate text-xs font-semibold sm:text-sm">{{ $assignment->registration->name }}</p>
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
                            ] as [$key, $label, $keyColor])
                                <button type="button" data-key="{{ $key }}"
                                        class="score-key min-h-14 touch-manipulation select-none rounded-xl border px-2 text-lg font-bold active:brightness-95 {{ $keyColor }}">{{ $label }}</button>
                            @endforeach
                            <button type="button" data-key="M" class="score-key col-span-2 min-h-14 touch-manipulation select-none rounded-xl border border-green-200 bg-green-50 px-2 text-lg font-bold text-green-800 active:bg-green-100">M</button>
                            <button type="button" data-key="SUBMIT" class="score-key col-span-2 min-h-14 touch-manipulation select-none rounded-xl bg-indigo-600 px-3 text-base font-semibold text-white active:bg-indigo-700">送出</button>
                        </div>
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
    };
    const recalc = () => {
        document.querySelectorAll('.athlete-card').forEach(card => {
            card.querySelector('.end-total').textContent = Array.from(card.querySelectorAll('.score-input')).reduce((sum, input) => sum + valueOf(input.value), 0);
        });
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
