{{-- ===== On-screen Numpad ===== --}}
<div id="numpad"
     class="fixed inset-x-0 bottom-0 z-40 border-t border-gray-200 bg-white shadow-2xl
            sm:static sm:rounded-2xl sm:border sm:shadow mt-4
            [padding-bottom:env(safe-area-inset-bottom)]">

    {{-- 拖拉/標題列（行動裝置抓握用） --}}
    <div class="sm:hidden flex items-center justify-center py-2">
        <div class="h-1.5 w-10 rounded-full bg-gray-300"></div>
    </div>

    <div class="px-3 py-2 sm:p-4">
        {{-- 第一列：7 8 9 ⌫ --}}
        <div class="grid grid-cols-4 gap-2 mb-2">
            <button type="button" data-key="7"  class="nkey">7</button>
            <button type="button" data-key="8"  class="nkey">8</button>
            <button type="button" data-key="9"  class="nkey">9</button>
            <button type="button" data-key="BKSP" class="nkey nkey-muted">⌫</button>
        </div>

        {{-- 第二列：4 5 6 ← --}}
        <div class="grid grid-cols-4 gap-2 mb-2">
            <button type="button" data-key="4" class="nkey">4</button>
            <button type="button" data-key="5" class="nkey">5</button>
            <button type="button" data-key="6" class="nkey">6</button>
            <button type="button" data-key="PREV" class="nkey nkey-muted">←</button>
        </div>

        {{-- 第三列：1 2 3 → --}}
        <div class="grid grid-cols-4 gap-2 mb-2">
            <button type="button" data-key="1" class="nkey">1</button>
            <button type="button" data-key="2" class="nkey">2</button>
            <button type="button" data-key="3" class="nkey">3</button>
            <button type="button" data-key="NEXT" class="nkey nkey-accent">→</button>
        </div>

        {{-- 第四列：M 0 10 11 --}}
        <div class="grid grid-cols-4 gap-2">
            <button type="button" data-key="M"  class="nkey nkey-miss">M</button>
            <button type="button" data-key="0"  class="nkey">0</button>
            <button type="button" data-key="10" class="nkey">10</button>
            <button type="button" data-key="11" class="nkey">11</button>
        </div>

        {{-- 功能列：清除／收起（可選） --}}
        <div class="mt-3 flex items-center justify-between">
            <button type="button" data-key="CLR" class="rounded-xl border px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">清除此格</button>
            {{-- 若想收合可加一顆切換鈕；預設不做收合 --}}
        </div>
    </div>
</div>

{{-- 簡易樣式（沿用 Tailwind） --}}
<style>
    #numpad .nkey{
        @apply rounded-xl border px-4 py-3 text-base font-medium text-gray-900 bg-white hover:bg-gray-50 active:scale-95 transition;
    }
    #numpad .nkey-muted{
        @apply text-gray-600 border-gray-300;
    }
    #numpad .nkey-accent{
        @apply bg-indigo-600 text-white border-indigo-600 hover:bg-indigo-500;
    }
    #numpad .nkey-miss{
        @apply bg-rose-50 text-rose-700 border-rose-200 hover:bg-rose-100;
    }
</style>
