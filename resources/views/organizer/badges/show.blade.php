@extends('layouts.app')

@section('title', $badge->name.' 管理')

@section('content')
<div class="mx-auto max-w-7xl space-y-7 px-4 py-8 sm:px-6">
    <div>
        <a href="{{ route('organizer.events.badges.index', $event) }}" class="text-sm text-indigo-600">← 返回 Badge 一覽</a>
        <div class="mt-2 flex items-center gap-3"><img src="{{ $badge->icon_url }}" alt="" class="h-16 w-16 rounded-2xl object-cover"><h1 class="text-2xl font-bold">{{ $badge->name }}</h1></div>
        <p class="mt-1 text-sm text-gray-500">{{ $event->name }}・{{ $badge->description }}</p>
    </div>

    @if(session('success'))<div class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-700">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>@endif

    <div class="grid gap-6 lg:grid-cols-[320px_1fr]">
        <section class="rounded-2xl border bg-white p-5 shadow-sm">
            <h2 class="font-semibold">發放方式</h2>
            <p class="mt-3 rounded-xl bg-indigo-50 p-3 text-sm text-indigo-700">{{ ['attendance'=>'完成繳費並報到後自動發放','placement'=>'正式成績發布後依名次自動發放','staff'=>'加入工作團隊後自動發放','volunteer'=>'成為賽事志工後自動發放','manual'=>'由主辦方從參賽名單授予'][$badge->award_rule] ?? '主辦方授予' }}</p>
            @if($badge->eventGroup)<p class="mt-2 text-sm text-gray-600">組別：{{ $badge->eventGroup->name }}</p>@endif
            @if($badge->placement)<p class="mt-1 text-sm text-gray-600">名次：第 {{ $badge->placement }} 名</p>@endif
            <form method="POST" action="{{ route('organizer.events.badges.update', [$event, $badge]) }}" enctype="multipart/form-data" class="mt-5 space-y-3 border-t pt-4">
                @csrf @method('PATCH')
                <div x-data="{ fileName: '尚未選擇', preview: null }"><label class="text-xs text-gray-600">更換圖示</label><div class="mt-1 flex items-center gap-3"><img :src="preview || '{{ $badge->icon_url }}'" alt="預覽" class="h-12 w-12 rounded-xl object-cover"><label class="inline-flex min-h-11 cursor-pointer items-center rounded-xl border border-indigo-200 bg-indigo-50 px-4 text-sm font-medium text-indigo-700 hover:bg-indigo-100">選擇圖片<input type="file" name="icon" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="const file = $event.target.files[0]; fileName = file ? file.name : '尚未選擇'; preview = file ? URL.createObjectURL(file) : null"></label><span class="min-w-0 truncate text-xs text-gray-500" x-text="fileName"></span></div></div>
                <button class="w-full rounded-xl bg-indigo-600 px-4 py-2 text-sm text-white">儲存圖示</button>
            </form>
        </section>

        <section class="min-w-0 rounded-2xl border bg-white shadow-sm">
            @if($badge->award_rule === 'manual')
            <div class="border-b p-5"><h2 class="font-semibold">授予特殊 Badge</h2><p class="mt-1 text-sm text-gray-500">選擇本賽事選手，可一次發放給多人。</p></div>
            <form method="POST" action="{{ route('organizer.events.badges.award', [$event, $badge]) }}">@csrf
                <div class="grid max-h-[32rem] gap-2 overflow-y-auto p-4 sm:grid-cols-2">
                    @forelse($participants as $participant)<label class="flex min-h-12 items-center gap-3 rounded-xl border p-3"><input type="checkbox" name="user_ids[]" value="{{ $participant->user_id }}" class="rounded"><span class="min-w-0"><span class="block truncate font-medium">{{ $participant->name }}</span><span class="block text-xs text-gray-500">{{ $participant->event_group?->name }}</span></span></label>@empty<p class="text-sm text-gray-500">尚無可授予的參賽者。</p>@endforelse
                </div>
                @if($participants->isNotEmpty())<div class="flex flex-col gap-2 border-t p-4 sm:flex-row"><input name="award_note" class="min-h-11 flex-1 rounded-xl border-gray-300 text-sm" placeholder="授予原因（選填）"><button class="min-h-11 rounded-xl bg-indigo-600 px-5 text-sm text-white">批次授予</button></div>@endif
            </form>
            @else
            <div class="p-6"><h2 class="font-semibold">系統自動發放</h2><p class="mt-2 text-sm text-gray-500">條件成立時會自動處理，同一位會員不會重複取得。</p></div>
            @endif
            @if($claims->isNotEmpty())
            <div class="border-t">
            <div class="border-b p-5"><h2 class="font-semibold">申請審核</h2><p class="mt-1 text-sm text-gray-500">符合資格者會預先標示，仍由主辦方批次確認。</p></div>
            <form method="POST" action="{{ route('organizer.events.badges.review', [$event, $badge]) }}">@csrf
                <div class="overflow-x-auto"><table class="min-w-full text-sm"><thead class="bg-gray-50 text-left text-xs text-gray-500"><tr><th class="p-3"><input type="checkbox" onclick="document.querySelectorAll('.claim-check').forEach(el => el.checked = this.checked)"></th><th class="p-3">會員</th><th class="p-3">資格判斷</th><th class="p-3">狀態</th><th class="p-3">申請時間</th></tr></thead>
                    <tbody class="divide-y">@forelse($claims as $claim)<tr><td class="p-3">@if(in_array($claim->status, ['pending', 'needs_review']))<input class="claim-check rounded" type="checkbox" name="claim_ids[]" value="{{ $claim->id }}">@else<span class="text-gray-300">—</span>@endif</td><td class="p-3 font-medium">{{ $claim->user->display_name }}</td><td class="p-3"><span class="{{ $claim->is_eligible ? 'text-green-700' : 'text-orange-700' }}">{{ $claim->eligibility_note }}</span></td><td class="p-3">{{ ['pending'=>'待審核','needs_review'=>'需人工確認','approved'=>'已通過','rejected'=>'已拒絕'][$claim->status] ?? $claim->status }}</td><td class="p-3 text-gray-500">{{ $claim->created_at->format('Y-m-d H:i') }}</td></tr>@empty<tr><td colspan="5" class="p-8 text-center text-gray-500">尚無申請</td></tr>@endforelse</tbody>
                </table></div>
                @if($claims->whereIn('status', ['pending', 'needs_review'])->isNotEmpty())<div class="flex flex-wrap items-center gap-3 border-t p-4"><input name="review_note" class="min-w-56 flex-1 rounded-xl border-gray-300 text-sm" placeholder="審核備註（選填）"><button name="action" value="approve" class="rounded-xl bg-green-600 px-4 py-2 text-sm text-white">批次通過</button><button name="action" value="reject" class="rounded-xl border border-red-200 px-4 py-2 text-sm text-red-600">批次拒絕</button></div>@endif
            </form>
            </div>
            @endif
        </section>
    </div>

    <section class="rounded-2xl border bg-white p-5 shadow-sm"><h2 class="font-semibold">已授予紀錄</h2><div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">@forelse($awards as $award)<div class="rounded-xl border p-3"><p class="font-medium">{{ $award->user->display_name }}</p><p class="mt-1 text-xs text-gray-500">{{ $award->revoked_at ? '已撤銷 '.$award->revoked_at->format('Y-m-d H:i') : '授予於 '.$award->awarded_at->format('Y-m-d H:i') }}</p>@if(auth()->user()->isAdmin() && !$award->revoked_at)<form method="POST" action="{{ route('admin.badge-awards.revoke', $award) }}" class="mt-3 flex gap-2" onsubmit="return confirm('確定撤銷這筆 Badge？')">@csrf @method('PATCH')<input name="reason" required class="min-w-0 flex-1 rounded-lg border-gray-300 text-xs" placeholder="撤銷原因"><button class="rounded-lg border border-red-200 px-2 py-1 text-xs text-red-600">撤銷</button></form>@endif</div>@empty<p class="text-sm text-gray-500">尚未授予任何會員。</p>@endforelse</div></section>
</div>
@endsection
