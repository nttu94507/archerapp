{{-- resources/views/profile/complete.blade.php --}}
@extends('layouts.app')

@section('title', '完成個人資料')

@section('content')
    <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8 py-8">

        {{-- Page Header --}}
        <div class="mb-6">
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">完成個人資料</h1>
            <p class="text-sm text-gray-500 mt-1">請填寫以下資訊，以便完成報名與保險登記。</p>
        </div>

        {{-- Form --}}
        <form method="POST" action="{{ route('user.profile.completion.update') }}" class="space-y-8">
            @csrf

            {{-- 錯誤總覽（可選） --}}
            @if ($errors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                    <strong>請修正以下欄位：</strong>
                    <ul class="list-disc pl-5 mt-2 space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- 聯絡資訊 --}}
            <div class="rounded-2xl border border-gray-200 p-6 space-y-5 shadow-sm bg-white">
                <h2 class="text-lg font-semibold text-gray-900">聯絡資訊</h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">手機 *</label>
                        <input type="text" id="phone" name="phone" required
                               value="{{ old('phone', optional($profile)->phone) }}"
                               class="block w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('phone') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="city" class="block text-sm font-medium text-gray-700 mb-1">城市 / 縣市</label>
                        <input type="text" id="city" name="city"
                               value="{{ old('city', optional($profile)->city) }}"
                               class="block w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('city') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            {{-- 緊急聯絡資訊 --}}
            <div class="rounded-2xl border border-gray-200 p-6 space-y-5 shadow-sm bg-white">
                <h2 class="text-lg font-semibold text-gray-900">緊急聯絡資訊</h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="emergency_contact_name" class="block text-sm font-medium text-gray-700 mb-1">聯絡人姓名 *</label>
                        <input type="text" id="emergency_contact_name" name="emergency_contact_name" required
                               value="{{ old('emergency_contact_name', optional($profile)->emergency_contact_name) }}"
                               class="block w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('emergency_contact_name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="emergency_contact_phone" class="block text-sm font-medium text-gray-700 mb-1">聯絡人電話 *</label>
                        <input type="text" id="emergency_contact_phone" name="emergency_contact_phone" required
                               value="{{ old('emergency_contact_phone', optional($profile)->emergency_contact_phone) }}"
                               class="block w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('emergency_contact_phone') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            {{-- 競賽相關 --}}
            <div class="rounded-2xl border border-gray-200 p-6 space-y-5 shadow-sm bg-white">
                <h2 class="text-lg font-semibold text-gray-900">選手資料</h2>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="birthdate" class="block text-sm font-medium text-gray-700 mb-1">生日 *</label>
                        <input type="date" id="birthdate" name="birthdate" required
                               value="{{ old('birthdate', optional($profile?->birthdate)->format('Y-m-d')) }}"
                               class="block w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('birthdate') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="handedness" class="block text-sm font-medium text-gray-700 mb-1">慣用手</label>
                        <select id="handedness" name="handedness"
                                class="block w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">未指定</option>
                            @foreach(['left'=>'左手','right'=>'右手','both'=>'皆可'] as $k=>$v)
                                <option value="{{ $k }}" @selected(old('handedness', optional($profile)->handedness) === $k)>{{ $v }}</option>
                            @endforeach
                        </select>
                        @error('handedness') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="bow_type" class="block text-sm font-medium text-gray-700 mb-1">弓種</label>
                        <select id="bow_type" name="bow_type"
                                class="block w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">未指定</option>
                            @foreach(['recurve'=>'反曲','compound'=>'複合弓','barebow'=>'光弓','traditional'=>'傳統弓'] as $k=>$v)
                                <option value="{{ $k }}" @selected(old('bow_type', optional($profile)->bow_type) === $k)>{{ $v }}</option>
                            @endforeach
                        </select>
                        @error('bow_type') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            {{-- 俱樂部  等俱樂部加入後解除註解--}}
{{--            <div class="rounded-2xl border border-gray-200 p-6 space-y-5 shadow-sm bg-white">--}}
{{--                <h2 class="text-lg font-semibold text-gray-900">俱樂部</h2>--}}

{{--                <div>--}}
{{--                    <label for="club_name" class="block text-sm font-medium text-gray-700 mb-1">所屬俱樂部（選填）</label>--}}
{{--                    <input type="text" id="club_name" name="club_name"--}}
{{--                           value="{{ old('club_name', optional($profile)->club_name) }}"--}}
{{--                           class="block w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">--}}
{{--                    @error('club_name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror--}}
{{--                </div>--}}
{{--            </div>--}}

            {{-- Submit --}}
            <div class="flex justify-end gap-3">
                <a href="{{ url()->previous() }}"
                   class="inline-flex items-center justify-center rounded-xl border px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                    取消
                </a>
                <button type="submit"
                        class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-6 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                    送出
                </button>
            </div>
        </form>
    </div>
@endsection
