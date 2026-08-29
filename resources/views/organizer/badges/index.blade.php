@extends('layouts.app')

@section('title', $event->name.' Badge 管理')

@section('content')
<div class="mx-auto max-w-6xl space-y-7 px-4 py-8 sm:px-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <a href="{{ route('organizer.events.show', $event) }}" class="text-sm text-indigo-600">← 返回賽事工作台</a>
            <h1 class="mt-2 text-2xl font-bold">Badge 發放管理</h1>
            <p class="mt-1 text-sm text-gray-500">{{ $event->name }}</p>
        </div>
    </div>

    @if(session('success'))<div class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-700">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>@endif

    <section class="rounded-2xl border bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold">建立 Badge</h2>
        <form method="POST" action="{{ route('organizer.events.badges.store', $event) }}" enctype="multipart/form-data" class="mt-5 grid gap-4 md:grid-cols-2" x-data="{ type: '{{ old('type', 'participant') }}', rule: '{{ old('award_rule', $event->hasPlanFeature('check_in') ? 'attendance' : 'manual') }}', roles: {{ Illuminate\Support\Js::from(old('staff_roles', ['owner','manager','staff'])) }}, counts: {{ Illuminate\Support\Js::from($teamCounts) }}, changeType(){ if(this.type==='staff') this.rule='staff'; else if(this.type==='volunteer') this.rule='volunteer'; else if(['staff','volunteer'].includes(this.rule)) this.rule=this.type==='special'?'manual':'{{ $event->hasPlanFeature('check_in') ? 'attendance' : 'manual' }}' }, get previewCount(){ if(this.type==='volunteer') return Number(this.counts.volunteer||0); if(this.type==='staff') return this.roles.reduce((sum,role)=>sum+Number(this.counts[role]||0),0); return 0 } }">
            @csrf
            <div><label class="text-sm font-medium">名稱 *</label><input name="name" required value="{{ old('name') }}" class="mt-1 w-full rounded-xl border-gray-300" placeholder="例：2026 台北公開賽參賽者"></div>
            <div><label class="text-sm font-medium">類型 *</label><select name="type" x-model="type" @change="changeType()" class="mt-1 w-full rounded-xl border-gray-300"><option value="participant">參賽</option><option value="finisher">完賽</option><option value="staff">工作人員</option><option value="volunteer">志工</option><option value="special">特別 Badge</option></select></div>
            <div x-show="rule !== 'staff' && rule !== 'volunteer'"><label class="text-sm font-medium">適用條件</label><select name="eligibility" class="mt-1 w-full rounded-xl border-gray-300"><option value="registered">已有有效報名</option>@if($event->hasPlanFeature('check_in'))<option value="checked_in">已完成報到</option>@endif<option value="scored">已有有效成績</option><option value="any">不限資格</option></select></div>
            <div><label class="text-sm font-medium">取得方式 *</label><template x-if="type==='staff'"><p class="mt-1 rounded-xl bg-indigo-50 p-3 text-sm text-indigo-700">加入指定工作身分後自動取得</p></template><template x-if="type==='volunteer'"><p class="mt-1 rounded-xl bg-indigo-50 p-3 text-sm text-indigo-700">成為賽事志工後自動取得</p></template><select x-show="type!=='staff' && type!=='volunteer'" :disabled="type==='staff' || type==='volunteer'" x-model="rule" class="mt-1 w-full rounded-xl border-gray-300">@if($event->hasPlanFeature('check_in'))<option value="attendance">繳費並報到後自動取得</option>@endif<option value="placement">正式成績名次自動取得</option><option value="manual">主辦方賽後授予</option></select><input type="hidden" name="award_rule" :value="rule"></div>
            <div x-show="type==='staff'" class="rounded-xl border p-4"><label class="text-sm font-medium">適用身分</label><div class="mt-3 grid gap-2">@foreach(['owner'=>'擁有者','manager'=>'管理者','staff'=>'工作人員'] as $value=>$label)<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="staff_roles[]" value="{{ $value }}" x-model="roles" class="rounded"> {{ $label }} <span class="text-xs text-gray-400">({{ $teamCounts[$value] ?? 0 }} 人)</span></label>@endforeach</div></div>
            <div x-show="type==='staff' || type==='volunteer'" class="rounded-xl bg-green-50 p-4 text-sm text-green-700"><span x-text="previewCount"></span> 位現有成員會在建立後立即取得，之後新加入者也會自動取得。</div>
            <div x-show="rule === 'attendance' || rule === 'placement'"><label class="text-sm font-medium">適用組別</label><select name="event_group_id" class="mt-1 w-full rounded-xl border-gray-300"><option value="">全部組別</option>@foreach($groups as $group)<option value="{{ $group->id }}">{{ $group->name }}</option>@endforeach</select></div>
            <div x-show="rule === 'placement'"><label class="text-sm font-medium">名次</label><select name="placement" class="mt-1 w-full rounded-xl border-gray-300"><option value="1">金牌（第 1 名）</option><option value="2">銀牌（第 2 名）</option><option value="3">銅牌（第 3 名）</option></select></div>
            <div x-data="{ fileName: '尚未選擇', preview: null }"><label class="text-sm font-medium">圖示</label><div class="mt-1 flex items-center gap-3"><img x-show="preview" :src="preview" alt="預覽" class="h-12 w-12 rounded-xl object-cover"><label class="inline-flex min-h-11 cursor-pointer items-center rounded-xl border border-indigo-200 bg-indigo-50 px-4 text-sm font-medium text-indigo-700 hover:bg-indigo-100">選擇圖片<input type="file" name="icon" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="const file = $event.target.files[0]; fileName = file ? file.name : '尚未選擇'; preview = file ? URL.createObjectURL(file) : null"></label><span class="min-w-0 truncate text-xs text-gray-500" x-text="fileName"></span></div><p class="mt-1 text-xs text-gray-500">JPG、PNG、WebP，最大 10MB</p></div>
            <div x-show="rule === 'manual'" class="flex items-end"><p class="text-sm text-gray-500">建立後可從參賽名單批次授予。</p></div>
            <div class="md:col-span-2"><label class="text-sm font-medium">說明</label><textarea name="description" rows="2" class="mt-1 w-full rounded-xl border-gray-300" placeholder="取得條件與 Badge 說明">{{ old('description') }}</textarea></div>
            <div class="md:col-span-2 text-right"><button class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-indigo-500">建立 Badge</button></div>
        </form>
    </section>

    <section>
        <h2 class="mb-4 text-lg font-semibold">目前的 Badge</h2>
        <div class="grid gap-4 md:grid-cols-2">
            @forelse($badges as $badge)
                <a href="{{ route('organizer.events.badges.show', [$event, $badge]) }}" class="rounded-2xl border bg-white p-5 shadow-sm hover:border-indigo-300">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-3"><img src="{{ $badge->icon_url }}" alt="" class="h-14 w-14 shrink-0 rounded-xl object-cover"><div class="min-w-0"><p class="break-words font-semibold">{{ $badge->name }}</p>@if($badge->description)<p class="mt-1 line-clamp-2 text-sm text-gray-500">{{ $badge->description }}</p>@endif</div></div>
                        <span class="rounded-full bg-indigo-50 px-2 py-1 text-xs text-indigo-700">{{ ['attendance'=>'報到自動發放','placement'=>'名次自動發放','staff'=>'工作人員自動發放','volunteer'=>'志工自動發放','manual'=>'主辦方授予'][$badge->award_rule] ?? '舊版申請' }}</span>
                    </div>
                    <div class="mt-4 flex gap-4 border-t pt-3 text-sm text-gray-600"><span>待審 {{ $badge->pending_claims_count }}</span><span>申請 {{ $badge->claims_count }}</span><span>已授予 {{ $badge->active_awards_count }}</span></div>
                </a>
            @empty
                <p class="text-sm text-gray-500">尚未建立 Badge。</p>
            @endforelse
        </div>
    </section>
</div>
@endsection
