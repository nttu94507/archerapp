@extends('layouts.app')

@section('title', 'Admin / 使用者列表')

@section('content')
    <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        @if(session('success'))<div class="rounded-xl bg-emerald-50 p-4 text-sm text-emerald-700">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="rounded-xl bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>@endif
        <div>
            <p class="text-xs uppercase tracking-widest text-indigo-600 font-semibold">Admin</p>
            <h1 class="text-2xl font-bold text-gray-900">使用者列表</h1>
            <p class="text-sm text-gray-500">查看使用者資料，並人工啟用或停止主辦方訂閱。</p>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-gray-500 uppercase tracking-widest text-xs">
                    <tr>
                        <th class="px-6 py-3 text-left font-semibold">名稱</th>
                        <th class="px-6 py-3 text-left font-semibold">Email</th>
                        <th class="px-6 py-3 text-left font-semibold">最後一次練習</th>
                        <th class="px-6 py-3 text-left font-semibold">主辦方訂閱</th>
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
                            <td class="min-w-72 px-6 py-4 text-gray-700">
                                @php($subscription = $user->organizerSubscription)
                                @if($subscription?->isActive())
                                    <div class="mb-3 flex flex-wrap items-center gap-2">
                                        <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">訂閱中</span>
                                        <span class="text-xs text-gray-500">{{ $subscription->ends_at ? '至 '.$subscription->ends_at->format('Y-m-d H:i') : '無到期日' }}</span>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                    <form method="POST" action="{{ route('admin.users.subscription.update', $user) }}">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="action" value="sync">
                                        <button class="min-h-10 rounded-xl border border-indigo-200 px-3 text-xs font-semibold text-indigo-700">同步既有賽事權益</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.users.subscription.update', $user) }}" onsubmit="return confirm('停止後，這個帳號新建立的賽事將使用免費方案；既有賽事不受影響。確定停止？')">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="action" value="cancel">
                                        <button class="min-h-10 rounded-xl border border-red-200 px-3 text-xs font-semibold text-red-600">停止訂閱</button>
                                    </form>
                                    </div>
                                @else
                                    <form method="POST" action="{{ route('admin.users.subscription.update', $user) }}" class="space-y-2">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="action" value="activate">
                                        <label class="block text-xs text-gray-500">到期時間（留空代表無期限）
                                            <input type="datetime-local" name="ends_at" class="mt-1 min-h-10 w-full rounded-lg border-gray-300 text-xs focus:border-indigo-500 focus:ring-indigo-500">
                                        </label>
                                        <button class="min-h-10 rounded-xl bg-indigo-600 px-3 text-xs font-semibold text-white">啟用訂閱</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-10 text-center text-gray-500">目前沒有使用者資料。</td>
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
