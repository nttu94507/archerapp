@extends('layouts.app')

@section('title', 'Admin / 使用者列表')

@section('content')
    <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <div>
            <p class="text-xs uppercase tracking-widest text-indigo-600 font-semibold">Admin</p>
            <h1 class="text-2xl font-bold text-gray-900">使用者列表</h1>
            <p class="text-sm text-gray-500">查看所有使用者名稱、Email 與最後一次練習日期。</p>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-gray-500 uppercase tracking-widest text-xs">
                    <tr>
                        <th class="px-6 py-3 text-left font-semibold">名稱</th>
                        <th class="px-6 py-3 text-left font-semibold">Email</th>
                        <th class="px-6 py-3 text-left font-semibold">最後一次練習</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                    @forelse($users as $user)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 font-medium text-gray-900">{{ $user->display_name }}</td>
                            <td class="px-6 py-4 text-gray-700">{{ $user->email }}</td>
                            <td class="px-6 py-4 text-gray-700">
                                @if($user->archery_sessions_max_created_at)
                                    {{ \Illuminate\Support\Carbon::parse($user->archery_sessions_max_created_at)->format('Y-m-d H:i') }}
                                @else
                                    <span class="text-gray-400">尚無練習紀錄</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-6 py-10 text-center text-gray-500">目前沒有使用者資料。</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div class="px-6 py-4 border-t border-gray-100">
                {{ $users->links() }}
            </div>
        </div>
    </div>
@endsection
