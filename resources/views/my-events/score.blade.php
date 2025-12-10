@extends('layouts.app')

@section('title', $event->name . ' | 計分表')

@section('content')
    <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 py-8">
        <div class="mb-6 flex items-start justify-between gap-3">
            <div>
                <p class="text-xs text-gray-500">我的賽事</p>
                <h1 class="text-2xl font-bold text-gray-900">{{ $event->name }}</h1>
                <p class="text-sm text-gray-600">{{ $event->start_date }} ~ {{ $event->end_date }}</p>
                <p class="text-sm text-gray-600">{{ optional($registration->event_group)->name }} · {{ optional($registration->event_group)->distance }}</p>
            </div>
            <div class="flex items-center gap-2">
                @if($scoreable)
                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">目前可計分</span>
                @else
                    <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-600">不在計分期間</span>
                @endif
                <a href="{{ route('my-events.index') }}" class="inline-flex items-center rounded-xl border px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">返回列表</a>
            </div>
        </div>

        @if($finalized)
            <div class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                整局成績已送出，無法再修改。
            </div>
        @endif

        <div class="rounded-2xl border bg-white shadow-sm overflow-hidden">
            <div class="flex items-center justify-between border-b px-4 py-3">
                <div>
                    <p class="text-sm font-semibold text-gray-900">計分表</p>
                    <p class="text-xs text-gray-500">本組 {{ $arrowSettings['total_arrows'] }} 支 / {{ $arrowSettings['total_ends'] }} 趟，每趟 {{ $arrowSettings['arrows_per_end'] }} 支，超過 {{ $event->mode === 'indoor' ? 30 : 36 }} 支會分兩局。</p>
                </div>
                <div class="flex items-center gap-2">
                    @if($scoreable && !$finalized)
                        <span class="text-xs text-gray-500">目前缺少第 {{ $nextEnd }} 趟</span>
                        <span class="text-xs text-gray-400">整局送出前都可修改已填寫的趟次</span>
                    @elseif($finalized)
                        <span class="text-xs text-gray-400">已送出整局成績</span>
                    @else
                        <span class="text-xs text-gray-400">不在計分時間內</span>
                    @endif
                </div>
            </div>

            <div class="divide-y divide-gray-100">
                <div class="hidden grid-cols-[120px_1fr_90px_80px_80px] bg-gray-50 px-4 py-2 text-xs font-semibold uppercase text-gray-500 sm:grid">
                    <div>趟次</div>
                    <div>箭序</div>
                    <div class="text-right">小計</div>
                    <div class="text-center">X + 10</div>
                    <div class="text-center">X</div>
                </div>

                @foreach($segments as $segment)
                    <div class="bg-gray-50/70 px-4 py-2 text-xs font-semibold text-gray-600">{{ $segment['label'] }}</div>
                    @for($end = $segment['start']; $end <= $segment['end']; $end++)
                        @php
                            $entry = $entries->get($end);
                            $scores = $entry?->scores ?? array_fill(0, $arrowSettings['arrows_per_end'], '');
                            $endStats = $entryStats[$end] ?? null;
                        @endphp
                        <button type="button"
                                class="end-row w-full px-4 py-3 text-left {{ $scoreable && !$finalized ? 'hover:bg-gray-50' : 'cursor-not-allowed opacity-60' }} {{ !$entry && $nextEnd === $end ? 'bg-indigo-50/50' : '' }}"
                                data-end="{{ $end }}" data-scores='@json($scores)' data-can-open="{{ $scoreable && !$finalized ? '1' : '0' }}">
                            <div class="grid gap-3 sm:grid-cols-[120px_1fr_90px_80px_80px] sm:items-center">
                                <div class="hidden items-center gap-2 text-sm font-semibold text-gray-900 sm:flex">
                                    <span>第 {{ $end }} 趟</span>
                                    @if(!$entry)
                                        <span class="rounded-full bg-orange-50 px-2 py-0.5 text-[11px] font-medium text-orange-700">未填</span>
                                    @else
                                        <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700">已填</span>
                                    @endif
                                </div>
                                <div class="space-y-2">
                                    <div class="grid grid-cols-3 gap-2 text-sm text-gray-700">
                                        @foreach(array_chunk($scores, 3) as $chunk)
                                            @foreach($chunk as $score)
                                                <span class="inline-flex h-9 items-center justify-center rounded-lg bg-gray-100 text-base font-semibold text-gray-800">{{ $score === '' ? '—' : $score }}</span>
                                            @endforeach
                                            @if(count($chunk) < 3)
                                                @for($i = 0; $i < 3 - count($chunk); $i++)
                                                    <span class="inline-flex h-9 items-center justify-center rounded-lg bg-gray-50 text-base font-semibold text-gray-300">—</span>
                                                @endfor
                                            @endif
                                        @endforeach
                                    </div>
                                    <div class="sm:hidden space-y-2">
                                        <div class="flex items-center gap-2 text-xs font-semibold text-gray-900">
                                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px]">#{{ $end }}</span>
                                            @if(!$entry)
                                                <span class="rounded-full bg-orange-50 px-2 py-0.5 text-[11px] font-medium text-orange-700">未填</span>
                                            @else
                                                <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700">已填</span>
                                            @endif
                                        </div>
                                        <div class="flex flex-wrap items-center gap-2 text-sm font-semibold text-gray-900">
                                            <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-1 leading-none">
                                                <span class="text-xs text-gray-600">小計</span>
                                                <span class="text-base text-gray-900">{{ $entry?->end_total ?? '—' }}</span>
                                            </span>
                                            <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2.5 py-1 leading-none text-indigo-700">
                                                <span class="text-xs">X+10</span>
                                                <span class="text-base">{{ $endStats['ten_plus'] ?? '—' }}</span>
                                            </span>
                                            <span class="inline-flex items-center gap-1 rounded-full bg-purple-50 px-2.5 py-1 leading-none text-purple-700">
                                                <span class="text-xs">X</span>
                                                <span class="text-base">{{ $endStats['x_count'] ?? '—' }}</span>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="hidden text-right text-sm font-semibold text-gray-900 sm:block">{{ $entry?->end_total ?? '—' }}</div>
                                <div class="hidden text-center text-sm font-semibold text-gray-900 sm:block">{{ $endStats['ten_plus'] ?? '—' }}</div>
                                <div class="hidden text-center text-sm font-semibold text-gray-900 sm:block">{{ $endStats['x_count'] ?? '—' }}</div>
                            </div>
                        </button>
                    @endfor
                @endforeach

                <div class="hidden grid-cols-[120px_1fr_90px_80px_80px] items-center bg-gray-50 px-4 py-3 text-sm font-semibold text-gray-900 sm:grid">
                    <div class="flex items-center gap-2">總計</div>
                    <div></div>
                    <div class="text-right">{{ $stats['total_score'] }}</div>
                    <div class="text-center">{{ $stats['ten_plus'] }}</div>
                    <div class="text-center">{{ $stats['x_count'] }}</div>
                </div>
                <div class="flex items-center justify-between gap-3 bg-gray-50 px-4 py-3 text-sm font-semibold text-gray-900 sm:hidden">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-700">總計</span>
                        <span>分數 {{ $stats['total_score'] }}</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <span>X+10 {{ $stats['ten_plus'] }}</span>
                        <span>X {{ $stats['x_count'] }}</span>
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-2 border-t bg-gray-50 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="text-xs text-gray-500">
                    @if($finalized)
                        整局成績已送出。
                    @elseif($allComplete)
                        所有趟次已填寫，請送出整局成績。送出後將無法修改。
                    @else
                        尚有趟次未填寫，填完後才能送出整局成績。
                    @endif
                </div>
                @if(!$finalized)
                    <form method="POST" action="{{ route('my-events.score.submit', $event) }}">
                        @csrf
                        <button type="submit"
                                {{ $allComplete ? '' : 'disabled' }}
                                onclick="return confirm('確認送出整局成績？') && confirm('送出後無法修改，是否確定？')"
                                class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:cursor-not-allowed disabled:bg-gray-300">
                            送出整局成績
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    <div id="sheet" class="fixed inset-0 z-50 hidden items-end justify-center bg-black/30 px-4 py-6 sm:items-center">
        <div class="w-full max-w-md rounded-2xl bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b px-4 py-3">
                <div>
                    <p class="text-xs text-gray-500">計分輸入</p>
                    <p id="sheet-title" class="text-lg font-semibold text-gray-900">第 1 趟</p>
                </div>
                <button id="close-sheet" class="inline-flex items-center rounded-lg p-2 text-gray-500 hover:bg-gray-50" aria-label="關閉">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>

            <form id="score-form" method="POST" action="{{ route('my-events.score.store', $event) }}" class="space-y-4 px-4 py-5">
                @csrf
                <input type="hidden" name="end_number" id="end_number" value="1">
                <div class="grid grid-cols-6 gap-2" id="inputs">
                    @for($i=0; $i<6; $i++)
                        <input type="text" name="scores[]" inputmode="none" maxlength="2" readonly
                               class="score-input h-12 rounded-xl border border-gray-200 text-center text-lg font-semibold text-gray-900 focus:border-indigo-500 focus:ring-indigo-500" autocomplete="off" />
                    @endfor
                </div>

                <div class="grid grid-cols-4 gap-2">
                    @foreach(['X','10','9','BKSP','8','7','6','PREV','5','4','3','NEXT','2','1','M','CLR'] as $key)
                        <button type="button" data-key="{{ $key }}" class="nkey rounded-xl border px-4 py-3 text-center text-base font-semibold text-gray-900 hover:bg-gray-50">{{ $key === 'BKSP' ? '⌫' : ($key === 'PREV' ? '←' : ($key === 'NEXT' ? '→' : ($key === 'CLR' ? '清除' : $key))) }}</button>
                    @endforeach
                </div>

                <div class="flex flex-col gap-2">
                    <p class="text-xs text-gray-500">可在整局送出前再次開啟此趟修改內容。</p>
                    <button type="button" id="submit-score" class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:cursor-not-allowed disabled:bg-gray-300">
                        儲存此趟
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('js')
    <script>
        (function(){
            const sheet = document.getElementById('sheet');
            const inputs = Array.from(document.querySelectorAll('.score-input'));
            const title = document.getElementById('sheet-title');
            const endField = document.getElementById('end_number');
            const form = document.getElementById('score-form');
            const submitBtn = document.getElementById('submit-score');
            let currentIndex = 0;
            let autoSubmitting = false;

            trySubmit({ shouldAuto: false });

            function setFocus(idx){
                currentIndex = Math.max(0, Math.min(inputs.length - 1, idx));
                inputs[currentIndex]?.focus();
            }

            function openSheet(end, scores = []){
                title.textContent = `第 ${end} 趟`;
                endField.value = end;
                autoSubmitting = false;
                inputs.forEach((i, idx) => {
                    i.value = scores[idx] ?? '';
                    i.classList.remove('ring-2','ring-indigo-500');
                });
                sheet.classList.remove('hidden');
                sheet.classList.add('flex');
                const firstEmpty = inputs.findIndex(i => i.value.trim() === '');
                setFocus(firstEmpty === -1 ? 0 : firstEmpty);
                trySubmit({ shouldAuto: false });
            }

            function closeSheet(){
                sheet.classList.add('hidden');
                sheet.classList.remove('flex');
            }

            function focusNext(idx){
                setFocus(Math.min(inputs.length -1, idx +1));
            }

            function focusPrev(idx){
                setFocus(Math.max(0, idx -1));
            }

            function autoSubmit(){
                if (autoSubmitting) return;
                autoSubmitting = true;
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                } else {
                    form.submit();
                }
            }

            function trySubmit({ shouldAuto = true } = {}){
                if (inputs.every(i => i.value.trim() !== '')){
                    submitBtn?.removeAttribute('disabled');
                    if (shouldAuto) autoSubmit();
                } else {
                    autoSubmitting = false;
                    submitBtn?.setAttribute('disabled', 'disabled');
                }
            }

            document.querySelectorAll('.end-row').forEach(btn => {
                btn.addEventListener('click', (e)=>{
                    if (e.currentTarget.dataset.canOpen === '1'){
                        const end = Number(e.currentTarget.dataset.end);
                        const scores = e.currentTarget.dataset.scores ? JSON.parse(e.currentTarget.dataset.scores) : [];
                        openSheet(end, scores);
                    }
                });
            });

            document.getElementById('close-sheet')?.addEventListener('click', closeSheet);
            sheet?.addEventListener('click', (e)=>{
                if (e.target === sheet){
                    closeSheet();
                }
            });

            inputs.forEach(input => {
                input.addEventListener('focus', (e)=>{
                    const idx = inputs.indexOf(e.currentTarget);
                    if (idx !== -1) currentIndex = idx;
                });
                input.addEventListener('keydown', (e)=>{
                    if (e.key === 'Backspace'){
                        e.preventDefault();
                        inputs[currentIndex].value = '';
                        focusPrev(currentIndex);
                        trySubmit();
                    }
                });
                input.addEventListener('input', () => trySubmit());
            });

            document.querySelectorAll('.nkey').forEach(btn => {
                btn.addEventListener('click', ()=>{
                    const key = btn.dataset.key;
                    let idx = currentIndex;

                    if (key === 'NEXT') return focusNext(idx);
                    if (key === 'PREV') return focusPrev(idx);
                    if (key === 'BKSP') { inputs[idx].value=''; focusPrev(idx); trySubmit(); return; }
                    if (key === 'CLR') { inputs.forEach(i=> i.value=''); setFocus(0); trySubmit(); return; }

                    inputs[idx].value = key;
                    focusNext(idx);
                    trySubmit();
                });
            });

            form?.addEventListener('submit', closeSheet);

            submitBtn?.addEventListener('click', () => {
                if (!inputs.every(i => i.value.trim() !== '')) return;
                autoSubmit();
            });
        })();
    </script>
@endsection
