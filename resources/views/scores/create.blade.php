<!-- resources/views/scores/create.blade.php -->
@extends('layouts.app')

@section('content')
    <div class="max-w-3xl mx-auto p-4" x-data="scoreForm()">
        <h1 class="text-2xl font-bold mb-4">輸入成績</h1>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
            <!-- Event -->
            <div>
                <label class="block text-sm mb-1">賽事 (event)</label>
                <select class="w-full border rounded p-2" x-model="event_id">
                    <option value="">-- 請選擇 --</option>
                    @foreach($events as $e)
                        <option value="{{ $e->id }}">{{ $e->name }}（{{ $e->date }}）</option>
                    @endforeach
                </select>
            </div>

            <!-- Round -->
            <div>
                <label class="block text-sm mb-1">回合 (round)</label>
                <select class="w-full border rounded p-2" x-model="round_id" @change="loadRound()">
                    <option value="">-- 請選擇 --</option>
                    @foreach($rounds as $r)
                        <option value="{{ $r->id }}">{{ $r->name }}｜{{ $r->distance }}m｜{{ $r->arrow_count }}箭</option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-500 mt-1" x-show="arrow_count">本回合應輸入 <b x-text="arrow_count"></b> 支箭</p>
            </div>

            <!-- Archer -->
            <div>
                <label class="block text-sm mb-1">射手 (archer)</label>
                <select class="w-full border rounded p-2" x-model="archer_id">
                    <option value="">-- 請選擇 --</option>
                    @foreach($archers as $a)
                        <option value="{{ $a->id }}">{{ $a->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <!-- 進度與統計 -->
        <div class="mb-4 p-3 border rounded bg-gray-50">
            <div class="flex items-center justify-between">
                <div>已輸入：<b x-text="arrows.length"></b> / <b x-text="arrow_count || '?'"></b> 支</div>
                <div>剩餘：<b x-text="remaining()"></b></div>
            </div>
            <div class="mt-2">總分：<b x-text="sum()"></b> ｜ X：<b x-text="xCount()"></b> ｜ 10：<b x-text="tenCount()"></b></div>
        </div>

        <!-- 已輸入箭序列 -->
        <div class="mb-4">
            <label class="block text-sm mb-1">箭序列</label>
            <div class="grid grid-cols-6 gap-2">
                <template x-for="(v, idx) in arrows" :key="idx">
                    <div class="border rounded p-2 text-center bg-white">
                        <div class="text-xs text-gray-500">#<span x-text="idx+1"></span></div>
                        <div class="text-lg font-semibold" x-text="v"></div>
                    </div>
                </template>
                <template x-if="arrows.length === 0">
                    <div class="col-span-6 text-sm text-gray-500">尚未輸入。可用下方鍵盤或貼上。</div>
                </template>
            </div>
        </div>

        <!-- 快速鍵盤 -->
        <div class="grid grid-cols-6 gap-2 mb-3">
            <template x-for="k in keypad" :key="k">
                <button type="button" class="border rounded p-2 bg-white hover:bg-gray-100"
                        @click="add(k)" :disabled="isFull()">
                    <span x-text="k"></span>
                </button>
            </template>
            <button type="button" class="border rounded p-2 bg-white hover:bg-gray-100 col-span-2" @click="remove()">退格</button>
            <button type="button" class="border rounded p-2 bg-white hover:bg-gray-100 col-span-2" @click="clearAll()">清空</button>
        </div>

        <!-- 貼上模式 -->
        <div class="mb-4">
            <label class="block text-sm mb-1">貼上箭值（以空白/逗號分隔；支援 X/M），例如：<code>X 10 9 9 10 M ...</code></label>
            <textarea class="w-full border rounded p-2 h-20" placeholder="X 10 9 9 10 M ..."
                      @paste.prevent="pasteHandler($event)"></textarea>
            <p class="text-xs text-gray-500 mt-1">貼上會自動解析並覆蓋目前輸入。</p>
        </div>

        <div class="flex items-center gap-2">
            <button type="button" class="px-4 py-2 rounded bg-blue-600 text-white disabled:opacity-40"
                    :disabled="!canSubmit()" @click="submit()">
                送出成績
            </button>
            <span class="text-sm" x-text="msg"></span>
        </div>
    </div>

    <script>
        function scoreForm() {
            return {
                event_id: '', round_id: '', archer_id: '',
                arrow_count: null, // 從 round 取回
                arrows: [],
                msg: '',
                keypad: ['X', 10, 9, 8, 7, 6, 5, 4, 3, 2, 1, 'M'],

                async loadRound() {
                    this.arrows = [];
                    this.msg = '';
                    if (!this.round_id) { this.arrow_count = null; return; }
                    // 你可以做一個最簡單的 Round 顯示 API：GET /api/rounds/{id}
                    const res = await fetch(`/api/rounds/${this.round_id}`);
                    const r = await res.json();
                    this.arrow_count = r.arrow_count;
                },

                add(v) {
                    if (this.isFull()) return;
                    this.arrows.push(v);
                },
                remove() {
                    this.arrows.pop();
                },
                clearAll() {
                    this.arrows = [];
                },
                isFull() {
                    return this.arrow_count && this.arrows.length >= this.arrow_count;
                },
                remaining() {
                    return this.arrow_count ? Math.max(this.arrow_count - this.arrows.length, 0) : '?';
                },

                sum() {
                    return this.arrows.reduce((s, v) => s + (v === 'X' ? 10 : (v === 'M' ? 0 : Number(v || 0))), 0);
                },
                xCount() {
                    return this.arrows.filter(v => String(v).toUpperCase() === 'X').length;
                },
                tenCount() {
                    return this.arrows.filter(v => String(v).toUpperCase() === 'X' || Number(v) === 10).length;
                },
                canSubmit() {
                    return this.event_id && this.round_id && this.archer_id &&
                        this.arrow_count && this.arrows.length === this.arrow_count;
                },

                async submit() {
                    this.msg = '送出中…';
                    try {
                        const payload = {
                            event_id: Number(this.event_id),
                            round_id: Number(this.round_id),
                            archer_id: Number(this.archer_id),
                            arrows: this.arrows, // 平面陣列，後端可直接吃
                        };
                        const res = await fetch('/api/scores/upsert', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(payload),
                        });
                        const data = await res.json();
                        if (!res.ok) throw data;
                        this.msg = '已儲存！總分 ' + (data.total_score ?? this.sum());
                    } catch (e) {
                        this.msg = '錯誤：' + (e?.message ?? JSON.stringify(e));
                    }
                },

                pasteHandler(ev) {
                    const text = (ev.clipboardData || window.clipboardData).getData('text');
                    const toks = text.split(/[\s,，]+/).filter(Boolean).map(t => {
                        t = t.toUpperCase();
                        if (t === 'X' || t === 'M') return t;
                        const n = Number(t);
                        if (!Number.isNaN(n) && n >= 0 && n <= 10) return n;
                        return null;
                    }).filter(v => v !== null);

                    if (!this.arrow_count) { this.arrows = toks; }
                    else this.arrows = toks.slice(0, this.arrow_count); // 超出就截斷
                },
            }
        }
    </script>
@endsection
