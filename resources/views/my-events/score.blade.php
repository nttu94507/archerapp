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

        <div class="rounded-2xl border bg-white shadow-sm overflow-hidden">
            <div class="flex items-center justify-between border-b px-4 py-3">
                <div>
                    <p class="text-sm font-semibold text-gray-900">計分表</p>
                    <p class="text-xs text-gray-500">本組 {{ $arrowSettings['total_arrows'] }} 支 / {{ $arrowSettings['total_ends'] }} 趟，每趟 {{ $arrowSettings['arrows_per_end'] }} 支，超過 {{ $event->mode === 'indoor' ? 30 : 36 }} 支會分兩局。</p>
                </div>
                @if($scoreable)
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-gray-500">目前缺少第 {{ $nextEnd }} 趟</span>
                        <button id="open-next" data-next="{{ $nextEnd }}"
                                class="inline-flex items-center rounded-xl bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                            前往填寫
                        </button>
                    </div>
                @endif
            </div>

            <div class="divide-y divide-gray-100">
                <div class="grid grid-cols-12 bg-gray-50 px-4 py-2 text-xs font-semibold uppercase text-gray-500">
                    <div class="col-span-2">趟次</div>
                    <div class="col-span-8">箭序</div>
                    <div class="col-span-2 text-right">合計</div>
                </div>

                @foreach($segments as $segment)
                    <div class="bg-gray-50/70 px-4 py-2 text-xs font-semibold text-gray-600">{{ $segment['label'] }}</div>
                    @for($end = $segment['start']; $end <= $segment['end']; $end++)
                        @php
                            $entry = $entries->get($end);
                            $scores = $entry?->scores ?? array_fill(0, $arrowSettings['arrows_per_end'], '');
                        @endphp
                        <button type="button"
                                class="end-row grid w-full grid-cols-12 items-center px-4 py-3 text-left {{ $scoreable ? 'hover:bg-gray-50' : 'cursor-not-allowed' }} {{ $nextEnd === $end ? 'bg-indigo-50/50' : '' }}"
                                data-end="{{ $end }}" data-scores='@json($scores)' data-can-open="{{ $scoreable ? '1' : '0' }}">
                            <div class="col-span-2 text-sm font-semibold text-gray-900 flex items-center gap-2">
                                <span>第 {{ $end }} 趟</span>
                                @if(!$entry)
                                    <span class="rounded-full bg-orange-50 px-2 py-0.5 text-[11px] font-medium text-orange-700">未填</span>
                                @else
                                    <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700">已填</span>
                                @endif
                            </div>
                            <div class="col-span-8 text-sm text-gray-700 flex flex-wrap gap-2">
                                @foreach($scores as $score)
                                    <span class="inline-flex h-7 w-10 items-center justify-center rounded-lg bg-gray-100 text-sm font-semibold text-gray-800">{{ $score === '' ? '—' : $score }}</span>
                                @endforeach
                            </div>
                            <div class="col-span-2 text-right text-sm font-semibold text-gray-900">{{ $entry?->end_total ?? '—' }}</div>
                        </button>
                    @endfor
                @endforeach
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
                        <input type="text" name="scores[]" inputmode="numeric" maxlength="2"
                               class="score-input h-12 rounded-xl border border-gray-200 text-center text-lg font-semibold text-gray-900 focus:border-indigo-500 focus:ring-indigo-500" autocomplete="off" />
                    @endfor
                </div>

                <div class="grid grid-cols-4 gap-2">
                    @foreach(['X','10','9','BKSP','8','7','6','PREV','5','4','3','NEXT','2','1','M','CLR'] as $key)
                        <button type="button" data-key="{{ $key }}" class="nkey rounded-xl border px-4 py-3 text-center text-base font-semibold text-gray-900 hover:bg-gray-50">{{ $key === 'BKSP' ? '⌫' : ($key === 'PREV' ? '←' : ($key === 'NEXT' ? '→' : ($key === 'CLR' ? '清除' : $key))) }}</button>
                    @endforeach
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
            let currentIndex = 0;

            function setFocus(idx){
                currentIndex = Math.max(0, Math.min(inputs.length - 1, idx));
                inputs[currentIndex]?.focus();
            }

            function openSheet(end, scores = []){
                title.textContent = `第 ${end} 趟`;
                endField.value = end;
                inputs.forEach((i, idx) => {
                    i.value = scores[idx] ?? '';
                    i.classList.remove('ring-2','ring-indigo-500');
                });
                sheet.classList.remove('hidden');
                sheet.classList.add('flex');
                const firstEmpty = inputs.findIndex(i => i.value.trim() === '');
                setFocus(firstEmpty === -1 ? 0 : firstEmpty);
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

            function trySubmit(){
                if (inputs.every(i => i.value.trim() !== '')){
                    form.submit();
                }
            }

            document.getElementById('open-next')?.addEventListener('click', (e)=>{
                const end = Number(e.currentTarget.dataset.next || '1');
                const target = document.querySelector(`[data-end="${end}"]`);
                const scores = target?.dataset.scores ? JSON.parse(target.dataset.scores) : [];
                openSheet(end, scores);
            });

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
                input.addEventListener('input', () => trySubmit());
            });

            document.querySelectorAll('.nkey').forEach(btn => {
                btn.addEventListener('click', ()=>{
                    const key = btn.dataset.key;
                    let idx = currentIndex;

                    if (key === 'NEXT') return focusNext(idx);
                    if (key === 'PREV') return focusPrev(idx);
                    if (key === 'BKSP') { inputs[idx].value=''; return; }
                    if (key === 'CLR') { inputs.forEach(i=> i.value=''); setFocus(0); return; }

                    inputs[idx].value = key;
                    focusNext(idx);
                    trySubmit();
                });
            });
        })();
    </script>
@endsection
