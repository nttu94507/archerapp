@php
    $existing = $event ?? null;

    $startDateValue = old('start_date');
    if (!$startDateValue && $existing && $existing->start_date) {
        $startDateValue = \Illuminate\Support\Carbon::parse($existing->start_date)->format('Y-m-d');
    }

    $endDateValue = old('end_date');
    if (!$endDateValue && $existing && $existing->end_date) {
        $endDateValue = \Illuminate\Support\Carbon::parse($existing->end_date)->format('Y-m-d');
    }

    $regStartValue = old('reg_start');
    if (!$regStartValue && $existing && $existing->reg_start) {
        $regStartValue = \Illuminate\Support\Carbon::parse($existing->reg_start)->format('Y-m-d\TH:i');
    }

    $regEndValue = old('reg_end');
    if (!$regEndValue && $existing && $existing->reg_end) {
        $regEndValue = \Illuminate\Support\Carbon::parse($existing->reg_end)->format('Y-m-d\TH:i');
    }
@endphp

            <div class="rounded-2xl border border-gray-200 p-6 space-y-5 shadow-sm bg-white">
                <h2 class="text-lg font-semibold text-gray-900">基本資訊</h2>

                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">賽事名稱 *</label>
                    <input type="text" name="name" id="name" value="{{ old('name', $existing?->name) }}"
                           class="block w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                           required>
                    @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">開始日期 *</label>
                        <input type="date" name="start_date" id="start_date" value="{{ $startDateValue }}"
                               class="block w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                               required>
                        @error('start_date') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <div class="flex items-baseline justify-between">
                            <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">結束日期 *</label>
                            <label class="inline-flex items-center gap-2 text-xs text-gray-600">
                                <input type="checkbox" id="single-day" class="rounded">
                                單日賽
                            </label>
                        </div>
                        <input type="date" name="end_date" id="end_date" value="{{ $endDateValue }}"
                               class="block w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                               required>
                        @error('end_date') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="mode" class="block text-sm font-medium text-gray-700 mb-1">比賽類型 *</label>
                        <select name="mode" id="mode"
                                class="block w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                required>
                            <option value="">請選擇</option>
                            <option value="indoor" @selected(old('mode', $existing?->mode)==='indoor')>室內</option>
                            <option value="outdoor" @selected(old('mode', $existing?->mode)==='outdoor')>室外</option>
                        </select>
                        @error('mode') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="verified" class="block text-sm font-medium text-gray-700 mb-1">是否驗證</label>
                        <select name="verified" id="verified"
                                class="block w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="1" @selected(old('verified', strval($existing?->verified ?? '1'))==='1')>是</option>
                            <option value="0" @selected(old('verified', strval($existing?->verified ?? '1'))==='0')>否</option>
                        </select>
                        @error('verified') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label for="level" class="block text-sm font-medium text-gray-700 mb-1">等級</label>
                    <input type="text" name="level" id="level" value="{{ old('level', $existing?->level) }}"
                           placeholder="例如：local / regional / national"
                           class="block w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('level') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="organizer" class="block text-sm font-medium text-gray-700 mb-1">主辦單位 *</label>
                    <input type="text" name="organizer" id="organizer" value="{{ old('organizer', $existing?->organizer) }}"
                           class="block w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                           required>
                    @error('organizer') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 p-6 space-y-5 shadow-sm bg-white">
                <h2 class="text-lg font-semibold text-gray-900">報名資訊</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="reg_start" class="block text-sm font-medium text-gray-700 mb-1">報名開始</label>
                        <input type="datetime-local" name="reg_start" id="reg_start" value="{{ $regStartValue }}"
                               class="block w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('reg_start') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="reg_end" class="block text-sm font-medium text-gray-700 mb-1">報名截止</label>
                        <input type="datetime-local" name="reg_end" id="reg_end" value="{{ $regEndValue }}"
                               class="block w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('reg_end') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 p-6 space-y-5 shadow-sm bg-white">
                <h2 class="text-lg font-semibold text-gray-900">場地資訊</h2>
                <div>
                    <label for="venue" class="block text-sm font-medium text-gray-700 mb-1">場地名稱</label>
                    <input type="text" name="venue" id="venue" value="{{ old('venue', $existing?->venue) }}"
                           class="block w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('venue') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="map_link" class="block text-sm font-medium text-gray-700 mb-1">Google 地圖連結</label>
                    <input type="url" name="map_link" id="map_link" value="{{ old('map_link', $existing?->map_link) }}"
                           placeholder="https://maps.google.com/..."
                           class="block w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('map_link') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="lat" class="block text-sm font-medium text-gray-700 mb-1">緯度</label>
                        <input type="text" name="lat" id="lat" value="{{ old('lat', $existing?->lat) }}"
                               class="block w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('lat') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="lng" class="block text-sm font-medium text-gray-700 mb-1">經度</label>
                        <input type="text" name="lng" id="lng" value="{{ old('lng', $existing?->lng) }}"
                               class="block w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('lng') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <a href="{{ $cancelRoute ?? url()->previous() }}"
                   class="inline-flex items-center justify-center rounded-xl border px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                    取消
                </a>
                <button type="submit"
                        class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-6 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                    儲存
                </button>
            </div>

            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    const start = document.getElementById('start_date');
                    const end   = document.getElementById('end_date');
                    const single = document.getElementById('single-day');

                    function syncEnd() {
                        if (single.checked) {
                            end.value = start.value || end.value;
                            end.readOnly = true;
                            end.classList.add('bg-gray-100');
                        } else {
                            end.readOnly = false;
                            end.classList.remove('bg-gray-100');
                        }
                    }

                    if (!end.value && start.value) {
                        single.checked = true;
                    }
                    syncEnd();

                    single.addEventListener('change', syncEnd);
                    start.addEventListener('change', () => {
                        if (single.checked) {
                            end.value = start.value;
                        }
                    });
                });
            </script>
