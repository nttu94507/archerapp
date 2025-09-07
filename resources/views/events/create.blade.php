{{-- resources/views/events/create.blade.php --}}
@extends('layouts.app')

@section('title', '新增賽事')

@section('content')
    <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8 py-8">

        {{-- Page Header --}}
        <div class="mb-6">
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">新增賽事</h1>
            <p class="text-sm text-gray-500 mt-1">填寫以下欄位以建立一個新賽事。</p>
        </div>

        {{-- Form --}}
        <form action="{{ route('events.store') }}" method="POST" class="space-y-8">
            @csrf

            {{-- 基本資訊 --}}
            <div class="rounded-2xl border border-gray-200 p-6 space-y-5 shadow-sm bg-white">
                <h2 class="text-lg font-semibold text-gray-900">基本資訊</h2>

                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">賽事名稱 *</label>
                    <input type="text" name="name" id="name" value="{{ old('name') }}"
                           class="block w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                           required>
                    @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="date" class="block text-sm font-medium text-gray-700 mb-1">賽事日期 *</label>
                    <input type="date" name="date" id="date" value="{{ old('date') }}"
                           class="block w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                           required>
                    @error('date') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="mode" class="block text-sm font-medium text-gray-700 mb-1">比賽類型 *</label>
                        <select name="mode" id="mode"
                                class="block w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                required>
                            <option value="">請選擇</option>
                            <option value="indoor" @selected(old('mode')==='indoor')>室內</option>
                            <option value="outdoor" @selected(old('mode')==='outdoor')>室外</option>
                        </select>
                    </div>
                    <div>
                        <label for="verified" class="block text-sm font-medium text-gray-700 mb-1">是否驗證</label>
                        <select name="verified" id="verified"
                                class="block w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="1" @selected(old('verified','1')==='1')>是</option>
                            <option value="0" @selected(old('verified')==='0')>否</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label for="level" class="block text-sm font-medium text-gray-700 mb-1">等級</label>
                    <input type="text" name="level" id="level" value="{{ old('level') }}"
                           placeholder="例如：local / regional / national"
                           class="block w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                <div>
                    <label for="organizer" class="block text-sm font-medium text-gray-700 mb-1">主辦單位 *</label>
                    <input type="text" name="organizer" id="organizer" value="{{ old('organizer') }}"
                           class="block w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                           required>
                </div>
            </div>

            {{-- 報名時間 --}}
            <div class="rounded-2xl border border-gray-200 p-6 space-y-5 shadow-sm bg-white">
                <h2 class="text-lg font-semibold text-gray-900">報名資訊</h2>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="reg_start" class="block text-sm font-medium text-gray-700 mb-1">報名開始</label>
                        <input type="datetime-local" name="reg_start" id="reg_start" value="{{ old('reg_start') }}"
                               class="block w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label for="reg_end" class="block text-sm font-medium text-gray-700 mb-1">報名截止</label>
                        <input type="datetime-local" name="reg_end" id="reg_end" value="{{ old('reg_end') }}"
                               class="block w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                </div>
            </div>

            {{-- 場地資訊 --}}
            <div class="rounded-2xl border border-gray-200 p-6 space-y-5 shadow-sm bg-white">
                <h2 class="text-lg font-semibold text-gray-900">場地資訊</h2>
                <div>
                    <label for="venue" class="block text-sm font-medium text-gray-700 mb-1">場地名稱</label>
                    <input type="text" name="venue" id="venue" value="{{ old('venue') }}"
                           class="block w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label for="map_link" class="block text-sm font-medium text-gray-700 mb-1">Google 地圖連結</label>
                    <input type="url" name="map_link" id="map_link" value="{{ old('map_link') }}"
                           placeholder="https://maps.google.com/..."
                           class="block w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="lat" class="block text-sm font-medium text-gray-700 mb-1">緯度</label>
                        <input type="text" name="lat" id="lat" value="{{ old('lat') }}"
                               class="block w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label for="lng" class="block text-sm font-medium text-gray-700 mb-1">經度</label>
                        <input type="text" name="lng" id="lng" value="{{ old('lng') }}"
                               class="block w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                </div>
            </div>

            {{-- Submit --}}
            <div class="flex justify-end gap-3">
                <a href="{{ route('events.index') }}"
                   class="inline-flex items-center justify-center rounded-xl border px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                    取消
                </a>
                <button type="submit"
                        class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-6 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                    建立賽事
                </button>
            </div>
        </form>
    </div>
@endsection
