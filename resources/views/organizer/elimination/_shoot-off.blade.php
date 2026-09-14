@php
    $teamMatch = in_array($match->bracket->category, ['team', 'mixed_team'], true);
    $participantOneName = $teamMatch ? $match->participantOneTeam?->name : $match->participantOneEntry?->athlete_name;
    $participantTwoName = $teamMatch ? $match->participantTwoTeam?->name : $match->participantTwoEntry?->athlete_name;
    $shootArrowCount = $teamMatch ? ($match->bracket->category === 'mixed_team' ? 2 : 3) : 1;
@endphp
<section class="space-y-4 rounded-2xl border border-amber-200 bg-amber-50 p-4 sm:p-5">
    <div><h2 class="font-bold text-amber-950">{{ $reason }}・加射</h2><p class="mt-1 text-sm text-amber-800">{{ $teamMatch ? '每位正式隊員各射 1 箭' : '雙方各射 1 箭' }}；同分時依現場裁判判定，在本設備選擇箭孔較接近靶心的一方。</p></div>

    @if($match->shootOffs->isNotEmpty())
    <div class="overflow-hidden rounded-xl border border-amber-200 bg-white">
        @foreach($match->shootOffs as $shootOff)
        <div class="grid grid-cols-[3rem_1fr_1fr] items-center gap-2 border-b px-3 py-3 text-center text-sm last:border-b-0">
            <strong>#{{ $shootOff->attempt_number }}</strong><span>{{ $participantOneName }}：<strong>{{ $teamMatch ? implode('・', $shootOff->participant_one_arrows ?? []) : $shootOff->participant_one_arrow }}</strong></span><span>{{ $participantTwoName }}：<strong>{{ $teamMatch ? implode('・', $shootOff->participant_two_arrows ?? []) : $shootOff->participant_two_arrow }}</strong></span>
            <p class="col-span-3 text-xs text-gray-500">{{ ['pending_judge'=>'同分，等待現場判定','resolved'=>'判定完成','re_shoot'=>'同距離，重新加射'][$shootOff->status] ?? $shootOff->status }}@if($shootOff->decision_note)・{{ $shootOff->decision_note }}@endif</p>
        </div>
        @endforeach
    </div>
    @endif

    @if($match->status === 'awaiting_shoot_off')
    @if($deviceMode ?? false)
    <form method="POST" action="{{ route('elimination-stations.shoot-offs.store', $stationToken) }}" id="shoot-off-form" class="space-y-3">@csrf
        <div class="grid grid-cols-2 gap-3">@foreach([['participant_one', $participantOneName], ['participant_two', $participantTwoName]] as [$side,$name])<div class="text-center text-sm font-semibold">{{ $name }}<div class="mt-1 grid gap-2" style="grid-template-columns: repeat({{ $shootArrowCount }}, minmax(0, 1fr))">@for($arrow=0;$arrow<$shootArrowCount;$arrow++)<input readonly inputmode="none" name="{{ $side }}_{{ $teamMatch ? 'arrows[]' : 'arrow' }}" placeholder="＿" class="shoot-input h-12 min-w-0 cursor-pointer rounded-xl border-amber-300 bg-white text-center text-xl font-bold placeholder:text-gray-300">@endfor</div></div>@endforeach</div>
        <div class="grid grid-cols-7 gap-2">@foreach(['X','10','9','8','7','6','5','4','3','2','1','M'] as $arrow)<button type="button" data-shoot-key="{{ $arrow }}" class="min-h-11 rounded-lg border border-amber-200 bg-white font-bold">{{ $arrow }}</button>@endforeach<button type="button" data-shoot-key="BKSP" class="min-h-11 rounded-lg border bg-white font-bold">⌫</button><button class="min-h-11 rounded-lg bg-amber-600 font-semibold text-white">送出</button></div>
    </form>
    <script>(()=>{const form=document.getElementById('shoot-off-form');if(!form)return;const inputs=[...form.querySelectorAll('.shoot-input')];const submit=form.querySelector('button:not([type])');let active=0;let submitting=false;const select=i=>{active=Math.max(0,Math.min(inputs.length-1,i));inputs.forEach(x=>x.classList.remove('ring-2','ring-amber-500'));inputs[active].classList.add('ring-2','ring-amber-500')};inputs.forEach((x,i)=>x.addEventListener('pointerdown',e=>{e.preventDefault();select(i)}));form.querySelectorAll('[data-shoot-key]').forEach(key=>key.addEventListener('click',()=>{const value=key.dataset.shootKey;if(value==='BKSP'){const i=inputs[active].value?active:Math.max(0,active-1);inputs[i].value='';select(i);return}inputs[active].value=value;if(active<inputs.length-1)select(active+1)}));form.addEventListener('submit',e=>{if(submitting||inputs.some(x=>!x.value)||!confirm('確認雙方加射箭值？同分時將在本設備繼續確認現場裁判結果。')){e.preventDefault();return}submitting=true;if(submit){submit.disabled=true;submit.textContent='送出中…'}});select(0)})();</script>
    @endif
    @elseif($match->status === 'awaiting_judge')
    @if($deviceMode ?? false)
    <form method="POST" action="{{ route('elimination-stations.shoot-offs.adjudicate', $stationToken) }}" class="space-y-3">@csrf
        <fieldset><legend class="mb-2 text-sm font-bold text-amber-950">同分判定</legend><div class="grid gap-2 sm:grid-cols-3"><label class="flex min-h-14 cursor-pointer items-center gap-3 rounded-xl border border-amber-300 bg-white px-4 font-semibold"><input type="radio" name="decision" value="participant_one" required class="text-amber-600"> {{ $participantOneName }}較近</label><label class="flex min-h-14 cursor-pointer items-center gap-3 rounded-xl border border-amber-300 bg-white px-4 font-semibold"><input type="radio" name="decision" value="participant_two" required class="text-amber-600"> {{ $participantTwoName }}較近</label><label class="flex min-h-14 cursor-pointer items-center gap-3 rounded-xl border border-gray-300 bg-white px-4 font-semibold"><input type="radio" name="decision" value="re_shoot" required class="text-gray-700"> 無法判定，重新加射</label></div></fieldset>
        <button class="min-h-12 w-full rounded-xl bg-gray-900 font-semibold text-white" onclick="return confirm('請依現場裁判結果確認選項，送出後將決定是否晉級。')">確認加射結果</button>
    </form>
    @else<p class="rounded-xl bg-white p-4 text-center text-sm text-amber-800">加射同分，請由該場綁定的計分設備依現場裁判結果確認。</p>@endif
    @endif
</section>
