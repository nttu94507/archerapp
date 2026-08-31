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

            <div class="space-y-5 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:p-6">
                <h2 class="text-lg font-semibold text-gray-900">基本資訊</h2>

                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">賽事名稱 *</label>
                    <input type="text" name="name" id="name" value="{{ old('name', $existing?->name) }}"
                           class="block min-h-12 w-full rounded-xl border border-gray-300 bg-gray-50 px-3 text-base focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                           required>
                    @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 sm:gap-4">
                    <div>
                        <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">開始日期 *</label>
                        <input type="date" name="start_date" id="start_date" value="{{ $startDateValue }}"
                               class="block min-h-12 w-full min-w-0 rounded-xl border border-gray-300 bg-gray-50 px-3 text-base focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                               required>
                        @error('start_date') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <div class="flex items-baseline justify-between">
                            <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">結束日期 *</label>
                            <label class="inline-flex min-h-11 items-center gap-2 text-sm text-gray-600">
                                <input type="checkbox" id="single-day" class="h-5 w-5 rounded">
                                單日賽
                            </label>
                        </div>
                        <input type="date" name="end_date" id="end_date" value="{{ $endDateValue }}"
                               class="block min-h-12 w-full min-w-0 rounded-xl border border-gray-300 bg-gray-50 px-3 text-base focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                               required>
                        @error('end_date') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 sm:gap-4">
                    <div>
                        <label for="mode" class="block text-sm font-medium text-gray-700 mb-1">比賽類型 *</label>
                        <select name="mode" id="mode"
                                class="block min-h-12 w-full rounded-xl border border-gray-300 bg-gray-50 px-3 text-base focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                required>
                            <option value="">請選擇</option>
                            <option value="indoor" @selected(old('mode', $existing?->mode)==='indoor')>室內</option>
                            <option value="outdoor" @selected(old('mode', $existing?->mode)==='outdoor')>室外</option>
                        </select>
                        @error('mode') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    @if($showVerification ?? false)
                    <div>
                        <label for="verified" class="block text-sm font-medium text-gray-700 mb-1">是否驗證</label>
                        <select name="verified" id="verified"
                                class="block min-h-12 w-full rounded-xl border border-gray-300 bg-gray-50 px-3 text-base focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <option value="1" @selected(old('verified', strval($existing?->verified ?? '1'))==='1')>是</option>
                            <option value="0" @selected(old('verified', strval($existing?->verified ?? '1'))==='0')>否</option>
                        </select>
                        @error('verified') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>@endif
                </div>

                <div>
                    <label for="level" class="block text-sm font-medium text-gray-700 mb-1">等級</label>
                    <input type="text" name="level" id="level" value="{{ old('level', $existing?->level) }}"
                           placeholder="例如：local / regional / national"
                           class="block min-h-12 w-full rounded-xl border border-gray-300 bg-gray-50 px-3 text-base focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('level') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                @php($canUseUnlisted = auth()->user()?->isAdmin() || ($existing && $existing->hasPlanFeature('unlisted_visibility')))
                <div>
                    <p class="block text-sm font-medium text-gray-700">賽事可見度</p>
                    <input type="hidden" name="visibility" value="public">
                    @if($canUseUnlisted)
                        <label for="event-unlisted" class="mt-2 flex min-h-14 cursor-pointer items-center gap-3 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 transition hover:border-indigo-300 hover:bg-indigo-50">
                            <input id="event-unlisted" type="checkbox" name="visibility" value="unlisted" @checked(old('visibility', $existing?->visibility ?? 'public') === 'unlisted') class="h-6 w-6 shrink-0 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span><span class="block text-sm font-semibold text-gray-800">不顯示於公開賽事列表</span><span class="mt-0.5 block text-xs text-gray-500">僅持 UUID 網址或 QR Code 的人可以進入、報名及查看戰況。</span></span>
                        </label>
                    @else
                        <div class="mt-2 rounded-xl bg-gray-50 px-4 py-3 text-sm text-gray-600">免費賽事會顯示於公開列表；升級後可設定為不公開。</div>
                    @endif
                    @error('visibility') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="organizer" class="block text-sm font-medium text-gray-700 mb-1">主辦單位 *</label>
                    <input type="text" name="organizer" id="organizer" value="{{ old('organizer', $existing?->organizer) }}"
                           class="block min-h-12 w-full rounded-xl border border-gray-300 bg-gray-50 px-3 text-base focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                           required>
                    @error('organizer') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="space-y-5 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:p-6">
                <div><h2 class="text-lg font-semibold text-gray-900">報名資訊</h2><p class="mt-1 text-xs text-gray-500">可稍後設定</p></div>
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 sm:gap-4">
                    <div>
                        <label for="reg_start" class="block text-sm font-medium text-gray-700 mb-1">報名開始</label>
                        <input type="datetime-local" name="reg_start" id="reg_start" value="{{ $regStartValue }}"
                               class="block min-h-12 w-full min-w-0 rounded-xl border border-gray-300 bg-gray-50 px-3 text-base focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        @error('reg_start') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="reg_end" class="block text-sm font-medium text-gray-700 mb-1">報名截止</label>
                        <input type="datetime-local" name="reg_end" id="reg_end" value="{{ $regEndValue }}"
                               class="block min-h-12 w-full min-w-0 rounded-xl border border-gray-300 bg-gray-50 px-3 text-base focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        @error('reg_end') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <div class="space-y-5 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:p-6">
                <h2 class="text-lg font-semibold text-gray-900">場地資訊</h2>
                <div>
                    <label for="venue" class="block text-sm font-medium text-gray-700 mb-1">場地名稱</label>
                    <input type="text" name="venue" id="venue" value="{{ old('venue', $existing?->venue) }}"
                           class="block min-h-12 w-full rounded-xl border border-gray-300 bg-gray-50 px-3 text-base focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('venue') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="map_link" class="block text-sm font-medium text-gray-700 mb-1">Google 地圖連結</label>
                    <input type="url" name="map_link" id="map_link" value="{{ old('map_link', $existing?->map_link) }}"
                           placeholder="https://maps.google.com/..."
                           class="block min-h-12 w-full rounded-xl border border-gray-300 bg-gray-50 px-3 text-base focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    @error('map_link') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 sm:gap-4">
                    <div>
                        <label for="lat" class="block text-sm font-medium text-gray-700 mb-1">緯度</label>
                        <input type="text" name="lat" id="lat" value="{{ old('lat', $existing?->lat) }}"
                               inputmode="decimal" class="block min-h-12 w-full rounded-xl border border-gray-300 bg-gray-50 px-3 text-base focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        @error('lat') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="lng" class="block text-sm font-medium text-gray-700 mb-1">經度</label>
                        <input type="text" name="lng" id="lng" value="{{ old('lng', $existing?->lng) }}"
                               inputmode="decimal" class="block min-h-12 w-full rounded-xl border border-gray-300 bg-gray-50 px-3 text-base focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        @error('lng') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <div class="sticky bottom-3 z-10 grid grid-cols-2 gap-3 rounded-2xl border bg-white/95 p-3 shadow-lg backdrop-blur sm:static sm:flex sm:justify-end sm:border-0 sm:bg-transparent sm:p-0 sm:shadow-none">
                <a href="{{ $cancelRoute ?? url()->previous() }}"
                   class="inline-flex min-h-12 items-center justify-center rounded-xl border px-4 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    取消
                </a>
                <button type="submit"
                        class="inline-flex min-h-12 items-center justify-center rounded-xl bg-indigo-600 px-6 text-sm font-medium text-white hover:bg-indigo-500">
                    {{ $existing ? '儲存變更' : '建立草稿' }}
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
