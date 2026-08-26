@extends('layouts.app')
@section('title', $event->name.' 工作台')
@section('content')
<div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 sm:py-8">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0"><a href="{{ route('organizer.events.index') }}" class="inline-flex min-h-11 items-center text-sm font-medium text-indigo-600">← 我的賽事</a><h1 class="break-words text-2xl font-bold">{{ $event->name }}</h1><p class="mt-1 text-sm text-gray-500">{{ $event->organizer }}・{{ $event->start_date->format('Y-m-d') }}～{{ $event->end_date->format('Y-m-d') }}</p></div>
        <div class="grid w-full grid-cols-2 gap-2 sm:flex sm:w-auto"><a href="{{ route('events.show',$event) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border px-4 text-sm">預覽公開頁</a>@can('update',$event)<a href="{{ route('organizer.events.edit',$event) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border px-4 text-sm">編輯資料</a>@endcan</div>
    </div>

    @if(session('success'))<div class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-700">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>@endif

    <section class="rounded-2xl border bg-white p-4 shadow-sm"><div class="flex items-center justify-between gap-3"><div class="min-w-0"><p class="text-xs text-gray-500">賽事狀態</p><p class="font-semibold">{{ ['draft'=>'草稿（尚未公開）','pending'=>'舊審核資料（可直接發布）','approved'=>'已發布','rejected'=>'已下架','archived'=>'已封存'][$event->status] ?? $event->status }}</p>@if($event->review_note)<p class="mt-1 text-xs text-red-600">平台介入備註：{{ $event->review_note }}</p>@endif</div>@can('update',$event)<div class="grid shrink-0 grid-cols-2 gap-2">@if(!$event->isPublished() && !$event->cancelled_at)<form method="POST" action="{{ route('organizer.events.submit',$event) }}" onsubmit="return confirm('發布後所有會員都能查看此賽事，確定發布？')">@csrf<button class="min-h-10 w-full rounded-xl bg-indigo-600 px-3 text-xs font-medium text-white sm:px-4 sm:text-sm">發布</button></form>@elseif($event->isPublished())<form method="POST" action="{{ route('organizer.events.unpublish',$event) }}" onsubmit="return confirm('下架後會員將無法查看與報名，確定下架？')">@csrf<button class="min-h-10 w-full rounded-xl border border-amber-300 px-3 text-xs font-medium text-amber-700 sm:px-4 sm:text-sm">下架</button></form>@endif @if(!$event->cancelled_at)<form method="POST" action="{{ route('organizer.events.cancel',$event) }}" onsubmit="return confirm('取消賽事後將停止公開報名，確定取消？')">@csrf<button class="min-h-10 w-full rounded-xl border border-red-200 px-3 text-xs text-red-600 sm:px-4 sm:text-sm">取消</button></form>@endif</div>@endcan</div></section>

    <section><h2 class="mb-3 text-lg font-semibold">賽事管理</h2><div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        @can('manageGroups',$event)<a href="{{ route('events.groups.index',$event) }}" class="flex min-h-28 flex-col justify-between rounded-2xl border bg-white p-4 hover:border-indigo-300 sm:p-5"><div><p class="text-2xl font-semibold">{{ $event->groups->count() }}</p><h3 class="mt-1 font-semibold">組別</h3></div><p class="mt-3 text-xs text-gray-500">設定組別與規則</p></a>@endcan
        @can('manageRegistrations',$event)<a href="{{ route('organizer.events.registrations.index',$event) }}" class="flex min-h-28 flex-col justify-between rounded-2xl border bg-white p-4 hover:border-indigo-300 sm:p-5"><div class="flex items-end gap-3"><div><p class="text-2xl font-semibold">{{ $event->active_registrations_count }}</p><p class="text-xs text-gray-500">有效報名</p></div><div><p class="text-lg font-semibold text-amber-700">{{ $event->pending_payments_count }}</p><p class="text-xs text-gray-500">待繳費</p></div></div><h3 class="mt-3 font-semibold">報名與繳費</h3></a>@endcan
        @can('manageRegistrations',$event)<a href="{{ route('organizer.events.check-in.index',$event) }}" class="flex min-h-28 flex-col justify-between rounded-2xl border border-emerald-200 bg-emerald-50 p-4 hover:border-emerald-400 sm:p-5"><div><p class="text-2xl font-semibold text-emerald-700">{{ $statusCounts['checked_in'] ?? 0 }}</p><h3 class="mt-1 font-semibold text-emerald-950">現場報到</h3></div><p class="mt-3 text-xs text-emerald-700">掃碼或搜尋選手完成報到</p></a>@endcan
        @can('manageScores',$event)<a href="{{ route('organizer.events.scoring.index',$event) }}" class="flex min-h-28 flex-col justify-between rounded-2xl border bg-white p-4 hover:border-indigo-300 sm:p-5"><div><p class="text-2xl font-semibold">{{ $event->verified_results_count }}</p><h3 class="mt-1 font-semibold">靶位計分</h3></div><p class="mt-3 text-xs text-gray-500">排靶、設備與計分進度</p></a>@endcan
        @can('viewResults',$event)<a href="{{ route('organizer.events.results.index',$event) }}" class="flex min-h-28 flex-col justify-between rounded-2xl border bg-white p-4 hover:border-indigo-300 sm:p-5"><div><p class="text-2xl font-semibold">{{ $event->verified_results_count }}</p><h3 class="mt-1 font-semibold">成績核對</h3></div><p class="mt-3 text-xs text-gray-500">查看、修正與發布成績</p></a>@endcan
        @can('manageJudging',$event)<a href="{{ route('organizer.events.judging.index',$event) }}" class="flex min-h-28 flex-col justify-between rounded-2xl border bg-white p-4 hover:border-indigo-300 sm:p-5"><div><p class="text-2xl font-semibold">⚖</p><h3 class="mt-1 font-semibold">裁判工作台</h3></div><p class="mt-3 text-xs text-gray-500">核對靶位、標記爭議與簽核</p></a>@endcan
        @if(auth()->user()->isAdmin() || auth()->user()->can('manageStaff',$event))<a href="{{ route('organizer.events.badges.index',$event) }}" class="flex min-h-28 flex-col justify-between rounded-2xl border bg-white p-4 hover:border-indigo-300 sm:p-5"><div><p class="text-2xl font-semibold">{{ $event->badges_count }}</p><h3 class="mt-1 font-semibold">Badge 管理</h3></div><p class="mt-3 text-xs text-gray-500">設定與發放</p></a>@endif
    </div></section>

    @can('manageStaff',$event)
    <section class="rounded-2xl border bg-white p-4 sm:p-5" x-data="{ inviteRole: 'staff' }">
        <h2 class="font-semibold">工作人員</h2>
        <div class="mt-4 rounded-2xl bg-indigo-50 p-4 sm:p-5">
            <div class="mx-auto max-w-md">
                <div class="text-center"><h3 class="font-semibold text-indigo-950">掃描 QR Code 加入</h3><p class="mt-1 text-sm text-indigo-700">選擇身分後，讓成員掃描並登入確認。邀請 24 小時後失效。</p></div>
                <label class="mt-4 block text-sm font-medium text-indigo-950">加入身分<select x-model="inviteRole" class="mt-1 min-h-11 w-full rounded-xl border-indigo-200 bg-white"><option value="manager">管理者</option><option value="staff">工作人員</option><option value="score_manager">成績管理員</option><option value="judge">裁判</option><option value="chief_judge">主裁判</option><option value="volunteer">志工</option><option value="viewer">只讀</option></select></label>
                <div class="mt-3 flex justify-center rounded-xl bg-white p-3">@foreach($staffInviteQrs as $role => $qr)<img x-show="inviteRole === '{{ $role }}'" src="{{ $qr }}" alt="{{ $role }} 工作團隊邀請 QR Code" class="h-52 w-52 max-w-full">@endforeach</div>
            </div>
        </div>

        <details class="group mt-4 rounded-xl border bg-gray-50 px-4 py-2">
            <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-3 text-sm font-medium"><span>手動輸入 Email 加入</span><span class="text-gray-400 transition group-open:rotate-180">⌄</span></summary>
            <form method="POST" action="{{ route('organizer.events.staff.store',$event) }}" class="grid gap-3 border-t py-4 sm:grid-cols-[minmax(0,1fr)_9rem_auto]">@csrf<input type="email" name="email" required class="min-h-11 min-w-0 w-full rounded-xl border-gray-300 text-base sm:text-sm" placeholder="已註冊會員 Email"><select name="role" class="min-h-11 w-full rounded-xl border-gray-300 text-base sm:text-sm"><option value="manager">管理者</option><option value="staff">工作人員</option><option value="score_manager">成績管理員</option><option value="judge">裁判</option><option value="chief_judge">主裁判</option><option value="volunteer">志工</option><option value="viewer">只讀</option></select><button class="min-h-11 rounded-xl bg-gray-900 px-4 text-sm text-white">加入</button></form>
        </details>

        <div class="mt-5 divide-y">@foreach($event->staff as $staff)<div class="flex items-center justify-between gap-3 py-3 text-sm"><div class="min-w-0"><p class="break-words font-medium">{{ $staff->user?->display_name }}</p><p class="text-xs text-gray-500">{{ ['owner'=>'擁有者','manager'=>'管理者','staff'=>'工作人員','score_manager'=>'成績管理員','judge'=>'裁判','chief_judge'=>'主裁判','volunteer'=>'志工','viewer'=>'只讀'][$staff->role] ?? $staff->role }}・{{ $staff->status }}</p></div>@if($staff->role!=='owner' && $staff->status==='active')<form method="POST" action="{{ route('organizer.events.staff.revoke',[$event,$staff]) }}">@csrf @method('PATCH')<button class="min-h-11 rounded-xl px-3 text-xs text-red-600">撤銷</button></form>@endif</div>@endforeach</div>
    </section>
    @endcan

    @if($event->isPublished())
    <section class="flex flex-col gap-3 rounded-2xl border border-indigo-200 bg-indigo-50 p-4 sm:flex-row sm:items-center sm:justify-between"
             x-data="{ copied: false, url: @js(route('events.show', $event)), title: @js($event->name) }">
        <div class="min-w-0"><h2 class="font-semibold text-indigo-950">分享賽事</h2><p class="mt-0.5 truncate text-xs text-indigo-700">讓選手開啟連結並選擇組別報名</p></div>
        <div class="grid shrink-0 grid-cols-3 gap-2">
            <button type="button" @click="navigator.clipboard.writeText(url); copied = true; setTimeout(() => copied = false, 1600)" class="min-h-10 rounded-xl bg-indigo-600 px-3 text-xs font-medium text-white sm:px-4"><span x-show="!copied">一鍵複製</span><span x-show="copied">已複製</span></button>
            <button type="button" @click="navigator.share ? navigator.share({ title, text: '查看賽事並完成報名', url }) : navigator.clipboard.writeText(url)" class="min-h-10 rounded-xl border border-indigo-200 bg-white px-3 text-xs font-medium text-indigo-700 sm:px-4">社群分享</button>
            <a href="{{ route('events.show', $event) }}" class="inline-flex min-h-10 items-center justify-center rounded-xl border border-indigo-200 bg-white px-3 text-xs font-medium text-indigo-700 sm:px-4">預覽</a>
        </div>
    </section>
    @endif

    <details class="group rounded-2xl border bg-white p-4 sm:p-5">
        <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-3 font-semibold">
            <span>最近操作紀錄</span>
            <span class="flex items-center gap-2 text-xs font-normal text-gray-500"><span>{{ $auditLogs->count() }} 筆</span><span class="transition group-open:rotate-180">⌄</span></span>
        </summary>
        <div class="mt-3 divide-y border-t pt-1">@forelse($auditLogs as $log)<div class="flex items-center justify-between gap-4 py-3 text-sm"><div><p class="font-medium">{{ $log->action }}</p><p class="text-xs text-gray-500">{{ $log->user?->display_name ?? '系統' }}</p></div><time class="shrink-0 text-xs text-gray-500">{{ $log->created_at->format('Y-m-d H:i') }}</time></div>@empty<p class="py-3 text-sm text-gray-500">尚無操作紀錄。</p>@endforelse</div>
    </details>
</div>
@endsection
