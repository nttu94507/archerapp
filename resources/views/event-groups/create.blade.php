{{-- resources/views/event-groups/create.blade.php --}}
@extends('layouts.app')

@section('title', '新增賽事組別')

@section('content')
    <div class="mx-auto max-w-5xl px-4 py-8">
        <div class="mb-6">
            <h1 class="text-2xl font-bold">新增組別 — {{ $event->name }}</h1>
            <p class="text-sm text-gray-500 mt-1">一次新增多個組別，提交後可再編輯。</p>
            @if($maxArrows === 36)<p class="mt-2 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800">免費方案僅支援單局最多 36 箭。</p>@endif
        </div>

        <form method="POST" action="{{ route('events.groups.store', $event) }}" id="group-form">
            @csrf
            <input type="hidden" name="use_first_group_fee" value="1">

            <section class="mb-5 rounded-2xl border border-indigo-100 bg-indigo-50 p-4 sm:p-5">
                <div class="flex flex-wrap items-start justify-between gap-3"><div><h2 class="font-semibold text-indigo-950">快速套用預設組別</h2><p class="mt-1 text-xs text-indigo-700">選取後批次帶入，送出前仍可逐組修改。</p></div><button type="button" id="apply-presets" class="min-h-10 rounded-xl bg-indigo-600 px-4 text-sm font-semibold text-white">套用選取組別</button></div>
                <div class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach([
                        ['recurve','70m','open','反曲弓 70 公尺公開組'],['recurve','70m','male','反曲弓 70 公尺男子組'],['recurve','70m','female','反曲弓 70 公尺女子組'],
                        ['recurve','30m','open','反曲弓 30 公尺公開組'],['recurve','30m','male','反曲弓 30 公尺男子組'],['recurve','30m','female','反曲弓 30 公尺女子組'],
                        ['compound','50m','open','複合弓 50 公尺公開組'],['compound','50m','male','複合弓 50 公尺男子組'],['compound','50m','female','複合弓 50 公尺女子組'],
                    ] as [$bow,$distance,$gender,$name])
                        <label class="flex min-h-12 cursor-pointer items-center gap-3 rounded-xl border border-indigo-100 bg-white px-3 text-sm transition has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-100"><input type="checkbox" class="preset-choice h-5 w-5 rounded text-indigo-600" data-bow="{{ $bow }}" data-distance="{{ $distance }}" data-gender="{{ $gender }}" data-name="{{ $name }}" data-arrows="{{ $distance === '30m' ? 36 : ($maxArrows > 36 ? 72 : 36) }}">{{ $name }}</label>
                    @endforeach
                </div>
                @if($maxNewGroups !== null)<p class="mt-3 text-xs font-medium text-amber-700">目前方案本次最多可新增 {{ $maxNewGroups }} 組。</p>@endif
            </section>

            <div id="group-list" class="space-y-4"></div>

            @error('groups')<p class="text-sm text-red-600 mb-2">{{ $message }}</p>@enderror

            <div class="flex items-center justify-between mt-4">
                <button type="button" id="add-group" @if($maxNewGroups !== null && $maxNewGroups <= 1) hidden @endif
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
                    <input type="text" class="group-name-field w-full rounded-lg border px-3 py-2 text-sm"
                           name="groups[__INDEX__][name]" placeholder="例如：男子反曲 70m" required>
                </div>

                <div>
                    <label class="block text-xs text-gray-600 mb-1">弓種</label>
                    <select class="group-bow-field w-full rounded-lg border px-3 py-2 text-sm" name="groups[__INDEX__][bow_type]">
                        <option value="">—</option>
                        <option value="recurve">反曲</option>
                        <option value="compound">複合</option>
                        <option value="barebow">光裸</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs text-gray-600 mb-1">性別</label>
                    <select class="group-gender-field w-full rounded-lg border px-3 py-2 text-sm" name="groups[__INDEX__][gender]">
                        <option value="open">不限</option>
                        <option value="male">男</option>
                        <option value="female">女</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs text-gray-600 mb-1">年齡組</label>
                    <input type="text" class="group-distance-field w-full rounded-lg border px-3 py-2 text-sm"
                           name="groups[__INDEX__][age_class]" placeholder="U18 / OPEN">
                </div>

                <div>
                    <label class="block text-xs text-gray-600 mb-1">距離</label>
                    <input type="text" class="w-full rounded-lg border px-3 py-2 text-sm"
                           name="groups[__INDEX__][distance]" placeholder="70m / 50m">
                </div>

                <div>
                    <label class="block text-xs text-gray-600 mb-1">排名賽局數 *</label>
                    <select class="w-full rounded-lg border px-3 py-2 text-sm round-format-select">
                        <option value="single">單局（{{ $event->mode === 'indoor' ? '30' : '36' }} 箭）</option>
                        @if($maxArrows > 36)<option value="double">雙局（{{ $event->mode === 'indoor' ? '60' : '72' }} 箭）</option>@endif
                    </select>
                    <input type="hidden" name="groups[__INDEX__][arrow_count]" class="arrow-count-field" value="{{ $event->mode === 'indoor' ? 30 : 36 }}">
                    @if($maxArrows === 36)<p class="text-xs text-amber-700 mt-1">免費方案僅支援單局。</p>@endif
                </div>

                <input type="hidden" name="groups[__INDEX__][arrows_per_end]" value="{{ $event->mode === 'indoor' ? 3 : 6 }}">

                <div>
                    <label class="block text-xs text-gray-600 mb-1">名額</label>
                    <input type="number" min="1" class="w-full rounded-lg border px-3 py-2 text-sm"
                           name="groups[__INDEX__][quota]">
                </div>

                <div>
                    <label class="fee-label block text-xs text-gray-600 mb-1">報名費</label>
                    <input type="number" min="0" class="group-fee-field w-full rounded-lg border px-3 py-2 text-sm"
                           name="groups[__INDEX__][fee]">
                    <p class="fee-sync-note mt-1 hidden text-[11px] text-gray-500">沿用第一組報名費</p>
                </div>

                @if($event->hasPlanFeature('team_competition'))<div class="md:col-span-2"><p class="block text-xs text-gray-600 mb-1">團體形式（可複選）</p><div class="flex flex-wrap gap-3"><label class="inline-flex items-center gap-2 text-xs"><input type="checkbox" class="rounded" name="groups[__INDEX__][standard_team_enabled]" value="1">三人團體（登記4人）</label><label class="inline-flex items-center gap-2 text-xs"><input type="checkbox" class="rounded" name="groups[__INDEX__][mixed_team_enabled]" value="1">男女混雙（2人）</label></div><p class="mt-1 text-[11px] text-gray-500">同一選手只能擇一參加。</p></div>
                <div><label class="block text-xs text-gray-600 mb-1">組隊截止</label><input type="datetime-local" name="groups[__INDEX__][team_formation_end]" class="w-full rounded-lg border px-3 py-2 text-sm"><p class="mt-1 text-xs text-gray-500">未填則沿用報名截止</p></div>@else<input type="hidden" name="groups[__INDEX__][is_team]" value="0">@endif
                <div class="md:col-span-7"><label class="inline-flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" name="groups[__INDEX__][use_custom_reg_window]" value="1" class="custom-reg-toggle h-5 w-5 rounded">自訂此組報名時間</label><p class="text-xs text-gray-500">未勾選時沿用賽事設定</p></div>
                <div class="custom-reg-window hidden md:col-span-7 grid-cols-1 gap-3 sm:grid-cols-2"><div><label class="block text-xs text-gray-600 mb-1">報名開始</label><input type="datetime-local" name="groups[__INDEX__][reg_start]" class="custom-reg-input w-full rounded-lg border px-3 py-2 text-sm"></div><div><label class="block text-xs text-gray-600 mb-1">報名截止</label><input type="datetime-local" name="groups[__INDEX__][reg_end]" class="custom-reg-input w-full rounded-lg border px-3 py-2 text-sm"></div></div>
            </div>
        </div>
    </template>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const list = document.getElementById('group-list');
            const tpl = document.getElementById('group-item-template').innerHTML;
            const addBtn = document.getElementById('add-group');
            const maxNewGroups = @js($maxNewGroups);

            function syncGroupFees() {
                const feeFields = [...list.querySelectorAll('.group-fee-field')];
                const firstFee = feeFields[0]?.value ?? '';
                feeFields.forEach((field, index) => {
                    field.readOnly = index > 0;
                    field.classList.toggle('bg-gray-100', index > 0);
                    if (index > 0) field.value = firstFee;
                    const item = field.closest('.group-item');
                    item?.querySelector('.fee-sync-note')?.classList.toggle('hidden', index === 0);
                    const label = item?.querySelector('.fee-label');
                    if (label) label.textContent = index === 0 ? '所有組別報名費' : '報名費';
                });
            }

            function renumber() {
                // 更新「組別 n」顯示
                list.querySelectorAll('.group-item').forEach((el, i) => {
                    const no = el.querySelector('.group-no');
                    if (no) no.textContent = i + 1;
                });
            }

            function addGroup(preset = null) {
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

                const roundFormat = node.querySelector('.round-format-select');
                const arrowHidden = node.querySelector('.arrow-count-field');
                const regToggle = node.querySelector('.custom-reg-toggle');
                const regWindow = node.querySelector('.custom-reg-window');
                const regInputs = node.querySelectorAll('.custom-reg-input');

                function syncArrowCount() {
                    const singleArrows = {{ $event->mode === 'indoor' ? 30 : 36 }};
                    arrowHidden.value = roundFormat.value === 'double' ? singleArrows * 2 : singleArrows;
                }

                roundFormat.addEventListener('change', syncArrowCount);

                // 初始同步
                syncArrowCount();

                function syncRegWindow() {
                    regWindow.classList.toggle('hidden', !regToggle.checked);
                    regWindow.classList.toggle('grid', regToggle.checked);
                    regInputs.forEach(input => {
                        input.required = regToggle.checked;
                        if (!regToggle.checked) input.value = '';
                    });
                }
                regToggle.addEventListener('change', syncRegWindow);
                syncRegWindow();

                if (preset) {
                    node.querySelector('.group-name-field').value = preset.name;
                    node.querySelector('.group-bow-field').value = preset.bow;
                    node.querySelector('.group-gender-field').value = preset.gender;
                    node.querySelector('.group-distance-field').value = preset.distance;
                    syncArrowCount();
                }

                list.appendChild(node);
                renumber();
                syncGroupFees();
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
                syncGroupFees();
            }

            list.addEventListener('input', event => {
                if (event.target.matches('.group-fee-field') && event.target === list.querySelector('.group-fee-field')) {
                    syncGroupFees();
                }
            });

            addBtn.addEventListener('click', () => addGroup());

            document.getElementById('apply-presets').addEventListener('click', () => {
                let selected = [...document.querySelectorAll('.preset-choice:checked')].map(input => ({
                    name: input.dataset.name, bow: input.dataset.bow, gender: input.dataset.gender,
                    distance: input.dataset.distance, arrows: input.dataset.arrows,
                }));
                if (!selected.length) return alert('請先選擇至少一個預設組別。');
                if (maxNewGroups !== null && selected.length > maxNewGroups) {
                    return alert(`目前方案最多可新增 ${maxNewGroups} 組。`);
                }
                list.innerHTML = '';
                selected.forEach(addGroup);
            });

            // 預設先放一組
            addGroup();
        });
    </script>
@endsection
