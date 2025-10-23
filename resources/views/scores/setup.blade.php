@extends('layouts.app')

@section('title','選擇計分模式')

@section('content')
    <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8 py-8">
        {{-- Header --}}
        <div class="mb-6">
            <h1 class="text-2xl font-semibold tracking-tight">選擇計分模式</h1>
            <p class="text-sm text-gray-500 mt-1">先選擇「訓練」或「比賽」。選訓練後可預先設定場地與距離等參數。</p>
        </div>

        {{-- Step 1: 模式選擇 --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" id="mode-cards">
            <button type="button" data-mode="training"
                    class="group rounded-2xl border p-4 text-left hover:border-indigo-400 hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-300">
                <div class="flex items-center justify-between">
                    <div class="text-base font-medium text-gray-900">訓練計分</div>
                    <span class="inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 text-xs text-indigo-700">推薦</span>
                </div>
                <p class="mt-1.5 text-sm text-gray-600">自由練習用。可自訂場地、距離、總箭數與每趟箭數。</p>
            </button>

{{--            <a href="{{ route('event.setup') }}"--}}
{{--               class="rounded-2xl border p-4 hover:border-emerald-400 hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-emerald-300 block">--}}
{{--                <div class="text-base font-medium text-gray-900">比賽計分</div>--}}
{{--                <p class="mt-1.5 text-sm text-gray-600">前往賽事列表，從已建立的賽事與組別進入比賽計分。</p>--}}
{{--            </a>--}}
        </div>

        {{-- Step 2: 訓練設定（選訓練後顯示） --}}
        <div id="training-form-wrap" class="mt-6 hidden">
            <div class="mb-3">
                <h2 class="text-lg font-semibold">訓練設定</h2>
                <p class="text-sm text-gray-500">這些設定將帶到計分頁並自動生成計分表。</p>
            </div>

            <form id="training-form" class="space-y-4" method="GET" action="{{ route('scores.create') }}">
                {{-- 場地 --}}
                <div>
                    <label for="venue" class="block text-xs font-medium text-gray-600 mb-1">場地</label>
                    <select id="venue" name="venue" class="w-full rounded-xl border-gray-300 bg-gray-50 px-3 py-2 text-sm">
                        <option value="indoor">室內</option>
                        <option value="outdoor">室外</option>
                    </select>
                </div>

                {{-- 距離 + 熱門 --}}
                <div>
                    <label for="distance" class="block text-xs font-medium text-gray-600 mb-1">距離（m）</label>
                    <div class="flex items-center gap-2 flex-wrap">
                        <input id="distance" name="distance" type="number" min="5" max="150" value="18"
                               class="w-24 rounded-xl border-gray-300 bg-white px-3 py-2 text-sm" />
                        <div id="distance-presets" class="flex items-center gap-1">
                            @foreach([18,20,30,50,70] as $d)
                                <button type="button" data-distance="{{ $d }}"
                                        class="px-3 py-1 rounded-xl border text-sm hover:bg-gray-50">{{ $d }}</button>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- 總箭數（依場地切換預設） --}}
                <div>
                    <label for="arrows_total" class="block text-xs font-medium text-gray-600 mb-1">總箭數</label>
                    <div class="flex items-center gap-2 flex-wrap">
                        <input id="arrows_total" name="arrows_total" type="number" min="1" max="300" value="30"
                               class="w-28 rounded-xl border-gray-300 bg-white px-3 py-2 text-sm" />
                        <div id="total-presets" class="flex items-center gap-1">
                            {{-- 依場地動態填入：室內 30/60；室外 36/72 --}}
                            <button type="button" data-total="30" class="px-3 py-1 rounded-xl border text-sm hover:bg-gray-50">30</button>
                            <button type="button" data-total="60" class="px-3 py-1 rounded-xl border text-sm hover:bg-gray-50">60</button>
                        </div>
                    </div>
                </div>

                {{-- 每趟箭數 --}}
                <div>
                    <label for="arrows_per_end" class="block text-xs font-medium text-gray-600 mb-1">每趟箭數</label>
                    <div class="flex items-center gap-2 flex-wrap">
                        <input id="arrows_per_end" name="arrows_per_end" type="number" min="1" max="12" value="6"
                               class="w-24 rounded-xl border-gray-300 bg-white px-3 py-2 text-sm" />
                        <div id="per-end-presets" class="flex items-center gap-1">
                            @foreach([3,6] as $p)
                                <button type="button" data-per-end="{{ $p }}"
                                        class="px-3 py-1 rounded-xl border text-sm hover:bg-gray-50">{{ $p }}</button>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- 弓種（先簡化，進入計分頁還是有完整選單） --}}
                <div>
                    <label for="bow_type" class="block text-xs font-medium text-gray-600 mb-1">弓種</label>
                    <select id="bow_type" name="bow_type" class="w-full rounded-xl border-gray-300 bg-gray-50 px-3 py-2 text-sm">
                        <option value="recurve">Recurve（反曲）</option>
                        <option value="compound">Compound（複合）</option>
                        <option value="barebow">Barebow（裸弓）</option>
                        <option value="yumi">Yumi（和弓）</option>
                        <option value="longbow">Longbow</option>
                    </select>
                </div>

                {{-- 送出：前往計分頁（GET 帶參數） --}}
                <div class="pt-2 flex items-center justify-end gap-2">
                    <a href="{{ route('scores.create') }}"
                       class="rounded-xl border px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">取消</a>
                    <button type="submit"
                            class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                        開始計分
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('js')
<script>
    // resources/js/pages/scores-setup.js
    (function(){
        const $ = (id) => document.getElementById(id);
        const modeCards = document.getElementById('mode-cards');
        const wrap = $('training-form-wrap');
        const form = $('training-form');

        const venue = $('venue');
        const distance = $('distance');
        const arrowsTotal = $('arrows_total');
        const perEnd = $('arrows_per_end');
        const bow = $('bow_type');

        function ready(fn){
            if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn, {once:true});
            else fn();
        }

        ready(() => {
            // Step 1: 點「訓練計分」→ 展開設定
            modeCards?.addEventListener('click', (e) => {
                const btn = e.target.closest('[data-mode="training"]');
                if (!btn) return;
                e.preventDefault();
                wrap?.classList.remove('hidden');
                // 滑到設定區
                wrap?.scrollIntoView({behavior:'smooth', block:'start'});
            });

            // 場地 → 預設總箭數
            function renderTotalPresets(){
                const box = document.getElementById('total-presets');
                if (!box) return;
                const presets = venue.value === 'indoor' ? [30,60] : [36,72];
                box.innerHTML = presets.map(n => (
                    `<button type="button" data-total="${n}" class="px-3 py-1 rounded-xl border text-sm hover:bg-gray-50">${n}</button>`
                )).join('');
            }
            renderTotalPresets();

            venue?.addEventListener('change', () => {
                renderTotalPresets();
                // 若目前值不在預設中，就帶第一個
                const presets = Array.from(document.querySelectorAll('#total-presets [data-total]')).map(b => +b.dataset.total);
                const curr = parseInt(arrowsTotal.value || '0', 10);
                if (!presets.includes(curr)) arrowsTotal.value = String(presets[0] || 30);
            });

            // 熱門距離/總箭數/每趟 按鈕
            document.addEventListener('click', (e) => {
                const dBtn = e.target.closest('[data-distance]');
                const tBtn = e.target.closest('[data-total]');
                const pBtn = e.target.closest('[data-per-end]');
                if (dBtn) { distance.value = dBtn.dataset.distance; }
                if (tBtn) { arrowsTotal.value = tBtn.dataset.total; }
                if (pBtn) { perEnd.value = pBtn.dataset.perEnd; }
            });

            // 送出前做一點基本驗證（以免打錯）
            form?.addEventListener('submit', (e) => {
                const dist = parseInt(distance.value||'0', 10);
                const tot  = parseInt(arrowsTotal.value||'0', 10);
                const per  = parseInt(perEnd.value||'0', 10);

                if (dist < 5 || dist > 150) { e.preventDefault(); alert('距離需介於 5~150 公尺'); return; }
                if (tot < 1 || tot > 300)   { e.preventDefault(); alert('總箭數需介於 1~300'); return; }
                if (per < 1 || per > 12)    { e.preventDefault(); alert('每趟箭數需介於 1~12'); return; }

                // 可選：記住上次設定（讓下次進來自動帶出）
                try {
                    localStorage.setItem('score_setup_pref', JSON.stringify({
                        mode: 'training',
                        venue: venue.value,
                        distance: dist,
                        total: tot,
                        per: per,
                        bow: bow.value,
                    }));
                } catch {}
            });

            //（可選）讀取上次設定
            try {
                const mem = JSON.parse(localStorage.getItem('score_setup_pref')||'null');
                if (mem?.mode === 'training') {
                    venue.value = mem.venue || venue.value;
                    distance.value = mem.distance ?? distance.value;
                    arrowsTotal.value = mem.total ?? arrowsTotal.value;
                    perEnd.value = mem.per ?? perEnd.value;
                    bow.value = mem.bow || bow.value;
                    renderTotalPresets();
                }
            } catch {}
        });
    })();

</script>
@endsection
