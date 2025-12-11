{{-- resources/views/event-groups/create.blade.php --}}
@extends('layouts.app')

@section('title', '新增賽事組別')

@section('content')
    <div class="mx-auto max-w-5xl px-4 py-8">
        <div class="mb-6">
            <h1 class="text-2xl font-bold">新增組別 — {{ $event->name }}</h1>
            <p class="text-sm text-gray-500 mt-1">一次新增多個組別，提交後可再編輯。</p>
        </div>

        <form method="POST" action="{{ route('events.groups.store', $event) }}" id="group-form">
            @csrf

            <div id="group-list" class="space-y-4"></div>

            @error('groups')<p class="text-sm text-red-600 mb-2">{{ $message }}</p>@enderror

            <div class="flex items-center justify-between mt-4">
                <button type="button" id="add-group"
                        class="rounded-xl border px-3 py-2 text-sm hover:bg-gray-50">+ 新增一組</button>
                <div class="flex gap-2">
                    <a href="{{ route('events.groups.index', $event) }}"
                       class="rounded-xl px-3 py-2 text-sm border hover:bg-gray-50">取消</a>
                    <button type="submit"
                            class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                        儲存
                    </button>
                </div>
            </div>
        </form>
    </div>

    {{-- 用 <template> 當原型，透過 __INDEX__ 佔位，動態替換成 0,1,2,... --}}
    <template id="group-item-template">
        <div class="rounded-2xl border bg-white p-4 shadow-sm group-item" data-index="__INDEX__">
            <div class="flex items-center justify-between mb-3">
                <div class="text-sm font-semibold">組別 <span class="group-no"></span></div>
                <button type="button" class="text-sm text-red-600 hover:underline remove-group">移除</button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-7 gap-3">
                <div class="md:col-span-2">
                    <label class="block text-xs text-gray-600 mb-1">名稱 *</label>
                    <input type="text" class="w-full rounded-lg border px-3 py-2 text-sm"
                           name="groups[__INDEX__][name]" placeholder="例如：男子反曲 70m" required>
                </div>

                <div>
                    <label class="block text-xs text-gray-600 mb-1">弓種</label>
                    <select class="w-full rounded-lg border px-3 py-2 text-sm" name="groups[__INDEX__][bow_type]">
                        <option value="">—</option>
                        <option value="recurve">反曲</option>
                        <option value="compound">複合</option>
                        <option value="barebow">光裸</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs text-gray-600 mb-1">性別</label>
                    <select class="w-full rounded-lg border px-3 py-2 text-sm" name="groups[__INDEX__][gender]">
                        <option value="open">不限</option>
                        <option value="male">男</option>
                        <option value="female">女</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs text-gray-600 mb-1">年齡組</label>
                    <input type="text" class="w-full rounded-lg border px-3 py-2 text-sm"
                           name="groups[__INDEX__][age_class]" placeholder="U18 / OPEN">
                </div>

                <div>
                    <label class="block text-xs text-gray-600 mb-1">距離</label>
                    <input type="text" class="w-full rounded-lg border px-3 py-2 text-sm"
                           name="groups[__INDEX__][distance]" placeholder="70m / 50m">
                </div>

                <div>
                    <label class="block text-xs text-gray-600 mb-1">箭數 *</label>
                    <div class="flex items-center gap-2">
                        <select class="w-full rounded-lg border px-3 py-2 text-sm arrow-select">
                            <option value="{{ $event->mode === 'indoor' ? 30 : 36 }}">{{ $event->mode === 'indoor' ? '30 支' : '36 支' }}</option>
                            <option value="{{ $event->mode === 'indoor' ? 60 : 72 }}">{{ $event->mode === 'indoor' ? '60 支' : '72 支' }}</option>
                            <option value="custom">自訂</option>
                        </select>
                        <input type="number" min="6" step="6" class="hidden w-28 rounded-lg border px-3 py-2 text-sm custom-arrow-input" placeholder="6 的倍數">
                    </div>
                    <input type="hidden" name="groups[__INDEX__][arrow_count]" class="arrow-count-field" value="{{ $event->mode === 'indoor' ? 30 : 36 }}">
                    <p class="text-xs text-gray-500 mt-1">室{{ $event->mode === 'indoor' ? '內' : '外' }}預設 {{ $event->mode === 'indoor' ? '30/60' : '36/72' }} 箭，可改自訂（6 的倍數）。</p>
                </div>

                <div>
                    <label class="block text-xs text-gray-600 mb-1">名額</label>
                    <input type="number" min="1" class="w-full rounded-lg border px-3 py-2 text-sm"
                           name="groups[__INDEX__][quota]">
                </div>

                <div>
                    <label class="block text-xs text-gray-600 mb-1">報名費</label>
                    <input type="number" min="0" class="w-full rounded-lg border px-3 py-2 text-sm"
                           name="groups[__INDEX__][fee]">
                </div>

                <div class="flex items-center gap-2">
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">隊制</label>
                        <input type="checkbox" class="rounded"
                               name="groups[__INDEX__][is_team]" value="1">
                    </div>
                    <span class="text-xs text-gray-500 mt-5">此組別為隊際</span>
                </div>
            </div>
        </div>
    </template>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const list = document.getElementById('group-list');
            const tpl = document.getElementById('group-item-template').innerHTML;
            const addBtn = document.getElementById('add-group');

            function renumber() {
                // 更新「組別 n」顯示
                list.querySelectorAll('.group-item').forEach((el, i) => {
                    const no = el.querySelector('.group-no');
                    if (no) no.textContent = i + 1;
                });
            }

            function addGroup() {
                const index = list.querySelectorAll('.group-item').length;
                const html = tpl.replace(/__INDEX__/g, index);
                const wrapper = document.createElement('div');
                wrapper.innerHTML = html.trim();
                const node = wrapper.firstElementChild;

                // 綁定移除
                node.querySelector('.remove-group').addEventListener('click', () => {
                    node.remove();
                    // 重新索引 name 屬性（確保連續 0..n-1）
                    rebuildIndexes();
                });

                const arrowSelect = node.querySelector('.arrow-select');
                const arrowHidden = node.querySelector('.arrow-count-field');
                const customInput = node.querySelector('.custom-arrow-input');

                function syncArrowCount() {
                    const value = arrowSelect.value;
                    if (value === 'custom') {
                        customInput.classList.remove('hidden');
                        customInput.focus();
                        if (customInput.value) {
                            arrowHidden.value = customInput.value;
                        }
                    } else {
                        customInput.classList.add('hidden');
                        customInput.value = '';
                        arrowHidden.value = value;
                    }
                }

                arrowSelect.addEventListener('change', syncArrowCount);
                customInput.addEventListener('input', () => {
                    arrowHidden.value = customInput.value;
                });

                // 初始同步
                syncArrowCount();

                list.appendChild(node);
                renumber();
            }

            function rebuildIndexes() {
                const items = Array.from(list.querySelectorAll('.group-item'));
                items.forEach((item, newIdx) => {
                    item.dataset.index = newIdx;
                    // 調整所有 input/select 的 name 屬性
                    item.querySelectorAll('input[name^="groups["], select[name^="groups["]').forEach(el => {
                        el.name = el.name.replace(/groups\[\d+]/, `groups[${newIdx}]`);
                    });
                });
                renumber();
            }

            addBtn.addEventListener('click', addGroup);

            // 預設先放一組
            addGroup();
        });
    </script>
@endsection
