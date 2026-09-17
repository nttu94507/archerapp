<article class="w-full overflow-hidden rounded-xl border bg-white shadow-sm">
    <div class="flex items-center justify-between bg-gray-50 px-3 py-2 text-xs text-gray-500"><span>#{{ $match->position }}</span><span>{{ $statusNames[$match->status] ?? $match->status }}</span></div>
    @php
        $teamMatch = in_array($bracket->category, ['team', 'mixed_team'], true);
    @endphp
    @foreach([[1,$match->participant_one_seed,$teamMatch?$match->participantOneTeam:$match->participantOneEntry],[2,$match->participant_two_seed,$teamMatch?$match->participantTwoTeam:$match->participantTwoEntry]] as [$slot,$seed,$entry])
    @php
        $targetNumber = $slot === 1
            ? ($match->participant_one_target_number ?? $match->target_number)
            : ($match->participant_two_target_number ?? $match->target_number);
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
    <div class="grid min-h-14 grid-cols-[3rem_minmax(0,1fr)_auto] items-center gap-2 border-t px-3 py-2">
        <span class="truncate text-center text-xs font-bold text-gray-500" title="{{ $targetNumber ? '靶號 '.$targetNumber : '靶號未設定' }}">{{ $targetNumber ?? '—' }}</span>
        <div class="min-w-0">
            @php
                $entryName = $teamMatch ? $entry?->name : $entry?->athlete_name;
            @endphp
            <p class="truncate text-sm font-semibold">{{ $entryName ?? ($match->round_number === 1 ? '輪空' : '等待前一輪勝者') }} @if($entry && $seed)<span class="text-xs font-medium text-gray-400">#{{ $seed }}</span>@endif</p>
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
    @if(in_array($match->status, ['awaiting_shoot_off', 'awaiting_judge'], true) && $match->shootOffs->isNotEmpty())
        @php
            $pendingShootOff = $match->shootOffs->last();
        @endphp
        <div class="border-t bg-amber-50 px-3 py-2 text-xs font-medium text-amber-800">
            加射：#{{ $pendingShootOff->attempt_number }} {{ $teamMatch ? implode('・', $pendingShootOff->participant_one_arrows ?? []) : $pendingShootOff->participant_one_arrow }}–{{ $teamMatch ? implode('・', $pendingShootOff->participant_two_arrows ?? []) : $pendingShootOff->participant_two_arrow }}
            {{ $match->status === 'awaiting_judge' ? '等待現場判定' : '同距離，等待重新加射' }}
        </div>
    @endif
</article>
