@extends('layouts.app')

@section('title', $event->name . ' | 計分表')

@section('content')
    <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 py-8">
        <div class="mb-6 flex items-start justify-between gap-3">
            <div>
                <p class="text-xs text-gray-500">我的賽事</p>
                <h1 class="text-2xl font-bold text-gray-900">{{ $event->name }}</h1>
                <p class="text-sm text-gray-600">{{ $event->start_date }} ~ {{ $event->end_date }}</p>
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
                    <p class="text-xs text-gray-500">點擊下一趟開始填寫，每趟 6 支箭，填滿自動送出。</p>
                </div>
                @if($scoreable)
                    <button id="open-sheet" data-next="{{ $nextEnd }}"
                            class="inline-flex items-center rounded-xl bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                        新增第 {{ $nextEnd }} 趟
                    </button>
                @endif
            </div>

            <div class="divide-y divide-gray-100">
                <div class="grid grid-cols-12 bg-gray-50 px-4 py-2 text-xs font-semibold uppercase text-gray-500">
                    <div class="col-span-2">趟次</div>
                    <div class="col-span-8">箭序</div>
                    <div class="col-span-2 text-right">合計</div>
                </div>

                @forelse($entries as $entry)
                    <button type="button"
                            class="grid w-full grid-cols-12 px-4 py-3 text-left hover:bg-gray-50"
                            data-end="{{ $entry->end_number }}" data-can-open="{{ $scoreable ? '1' : '0' }}">
                        <div class="col-span-2 text-sm font-semibold text-gray-900">第 {{ $entry->end_number }} 趟</div>
                        <div class="col-span-8 text-sm text-gray-700 flex flex-wrap gap-2">
                            @foreach($entry->scores as $score)
                                <span class="inline-flex h-7 w-10 items-center justify-center rounded-lg bg-gray-100 text-sm font-semibold text-gray-800">{{ $score === '' ? '—' : $score }}</span>
                            @endforeach
                        </div>
                        <div class="col-span-2 text-right text-sm font-semibold text-gray-900">{{ $entry->end_total }}</div>
                    </button>
                @empty
                    <div class="px-4 py-6 text-center text-sm text-gray-500">尚無計分紀錄，點擊右上角新增第 {{ $nextEnd }} 趟開始。</div>
                @endforelse

                @if($scoreable)
                    <button id="add-row" data-next="{{ $nextEnd }}" type="button" class="grid w-full grid-cols-12 items-center px-4 py-4 text-left text-sm font-semibold text-indigo-600 hover:bg-indigo-50">
                        <div class="col-span-2">第 {{ $nextEnd }} 趟</div>
                        <div class="col-span-8 text-gray-500">點擊開始填寫本趟 6 支箭分數</div>
                        <div class="col-span-2 text-right">開始</div>
                    </button>
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

            function openSheet(end){
                title.textContent = `第 ${end} 趟`;
                endField.value = end;
                inputs.forEach(i => { i.value=''; i.classList.remove('ring-2','ring-indigo-500'); });
                sheet.classList.remove('hidden');
                sheet.classList.add('flex');
                inputs[0]?.focus();
            }

            function closeSheet(){
                sheet.classList.add('hidden');
                sheet.classList.remove('flex');
            }

            function activeIndex(){
                return inputs.findIndex(i => i === document.activeElement);
            }

            function focusNext(idx){
                const next = Math.min(inputs.length -1, idx +1);
                inputs[next]?.focus();
            }

            function focusPrev(idx){
                const prev = Math.max(0, idx -1);
                inputs[prev]?.focus();
            }

            function trySubmit(){
                if (inputs.every(i => i.value.trim() !== '')){
                    form.submit();
                }
            }

            document.getElementById('open-sheet')?.addEventListener('click', (e)=>{
                const end = Number(e.currentTarget.dataset.next || '1');
                openSheet(end);
            });

            document.getElementById('add-row')?.addEventListener('click', (e)=>{
                const end = Number(e.currentTarget.dataset.next || '1');
                openSheet(end);
            });

            document.querySelectorAll('[data-end]').forEach(btn => {
                btn.addEventListener('click', (e)=>{
                    if (e.currentTarget.dataset.canOpen === '1'){
                        const end = Number(e.currentTarget.dataset.end);
                        openSheet(end);
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
                input.addEventListener('input', () => trySubmit());
            });

            document.querySelectorAll('.nkey').forEach(btn => {
                btn.addEventListener('click', ()=>{
                    const key = btn.dataset.key;
                    let idx = activeIndex();
                    if (idx === -1) idx = 0;

                    if (key === 'NEXT') return focusNext(idx);
                    if (key === 'PREV') return focusPrev(idx);
                    if (key === 'BKSP') { inputs[idx].value=''; return; }
                    if (key === 'CLR') { inputs.forEach(i=> i.value=''); inputs[0]?.focus(); return; }

                    inputs[idx].value = key;
                    focusNext(idx);
                    trySubmit();
                });
            });
        })();
    </script>
@endsection
