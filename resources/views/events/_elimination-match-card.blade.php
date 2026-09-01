<article class="w-full overflow-hidden rounded-xl border bg-white shadow-sm">
    <div class="flex items-center justify-between bg-gray-50 px-3 py-2 text-xs text-gray-500"><span>#{{ $match->position }}</span><span>{{ $match->match_type === 'bronze' && $match->status === 'walkover' ? '輪空取得季軍' : ($statusNames[$match->status] ?? $match->status) }}</span></div>
    @php
        $teamMatch = in_array($bracket->category, ['team', 'mixed_team'], true);
    @endphp
    @foreach([[1,$match->participant_one_seed,$teamMatch?$match->participantOneTeam:$match->participantOneEntry],[2,$match->participant_two_seed,$teamMatch?$match->participantTwoTeam:$match->participantTwoEntry]] as [$slot,$seed,$entry])
    @php
        $roundTotals = ($bracket->scoring_mode === 'set' ? $match->sets : $match->ends)
            ->take(5)
            ->map(function ($round) use ($slot, $bracket): array {
                $oneTotal = $bracket->scoring_mode === 'set'
                    ? $round->participant_one_total
                    : $round->participant_one_end_total;
                $twoTotal = $bracket->scoring_mode === 'set'
                    ? $round->participant_two_total
                    : $round->participant_two_end_total;
                return [
                    'total'=>$slot === 1 ? $oneTotal : $twoTotal,
                    'won'=>$slot === 1 ? $oneTotal > $twoTotal : $twoTotal > $oneTotal,
                    'tied'=>$oneTotal === $twoTotal,
                ];
            });
        $latestShootOff = $match->shootOffs->last();
        $shootOffArrow = $latestShootOff
            ? ($teamMatch
                ? implode('・', ($slot === 1 ? $latestShootOff->participant_one_arrows : $latestShootOff->participant_two_arrows) ?? [])
                : ($slot === 1 ? $latestShootOff->participant_one_arrow : $latestShootOff->participant_two_arrow))
            : null;
    @endphp
    <div class="grid min-h-14 grid-cols-[2rem_minmax(0,1fr)_auto] items-center gap-2 border-t px-3 py-2">
        <span class="text-xs font-bold text-gray-400">{{ $seed ?? '—' }}</span>
        <div class="min-w-0">
            @php
                $entryName = $teamMatch ? $entry?->name : $entry?->athlete_name;
            @endphp
            <p class="truncate text-sm font-semibold">{{ $entryName ?? ($match->round_number === 1 ? '輪空' : '等待前場勝者') }}</p>
            @if($entry && ($roundTotals->isNotEmpty() || $shootOffArrow !== null))
                <div class="mt-1 flex flex-wrap gap-1" aria-label="{{ $entryName }}各輪分數">
                    @foreach($roundTotals as $roundIndex => $roundResult)
                        <span title="第 {{ $roundIndex + 1 }} 輪三箭總分 {{ $roundResult['total'] }}{{ $roundResult['won'] ? '，本輪勝出' : ($roundResult['tied'] ? '，本輪同分' : '') }}" class="inline-flex min-w-6 items-center justify-center rounded px-1.5 py-0.5 text-[10px] tabular-nums {{ $roundResult['won'] ? 'bg-indigo-600 font-black text-white ring-1 ring-indigo-700' : ($roundResult['tied'] ? 'bg-violet-200 font-black text-violet-900 ring-1 ring-violet-300' : 'bg-slate-100 font-semibold text-slate-600') }}">{{ $roundResult['total'] }}</span>
                    @endforeach
                    @if($shootOffArrow !== null)
                        <span title="加射箭值 {{ $shootOffArrow }}" class="inline-flex min-w-6 items-center justify-center rounded bg-amber-200 px-1.5 py-0.5 text-[10px] font-black text-amber-900 ring-1 ring-amber-300">{{ $shootOffArrow }}</span>
                    @endif
                </div>
            @endif
        </div>
        @if($entry)<strong class="text-lg text-indigo-700">{{ $bracket->scoring_mode === 'set' ? ($slot === 1 ? $match->participant_one_set_points : $match->participant_two_set_points) : ($slot === 1 ? $match->participant_one_total : $match->participant_two_total) }}</strong>@endif
    </div>
    @endforeach
    @if($bracket->scoring_mode === 'set' && $match->sets->isNotEmpty())<div class="border-t bg-gray-50 px-3 py-2 text-xs text-gray-500">@foreach($match->sets as $set)<span class="mr-2">第{{ $set->set_number }}局 {{ $set->participant_one_total }}–{{ $set->participant_two_total }}</span>@endforeach</div>@elseif($bracket->scoring_mode === 'cumulative' && $match->ends->isNotEmpty())<div class="border-t bg-gray-50 px-3 py-2 text-xs text-gray-500">已完成 {{ $match->ends->count() }} / {{ $teamMatch ? 4 : 5 }} 趟</div>@endif
    @if(in_array($match->status, ['awaiting_shoot_off', 'awaiting_judge'], true) && $match->shootOffs->isNotEmpty())
        @php
            $pendingShootOff = $match->shootOffs->last();
        @endphp
        <div class="border-t bg-amber-50 px-3 py-2 text-xs font-medium text-amber-800">
            加射：#{{ $pendingShootOff->attempt_number }} {{ $teamMatch ? implode('・', $pendingShootOff->participant_one_arrows ?? []) : $pendingShootOff->participant_one_arrow }}–{{ $teamMatch ? implode('・', $pendingShootOff->participant_two_arrows ?? []) : $pendingShootOff->participant_two_arrow }}
            {{ $match->status === 'awaiting_judge' ? '等待主裁判判定' : '同距離，等待重新加射' }}
        </div>
    @endif
</article>
