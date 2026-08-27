@php
    $management = $management ?? false;
@endphp
<article class="w-full overflow-hidden rounded-xl border bg-white shadow-sm">
    <div class="flex items-center justify-between bg-gray-50 px-3 py-2 text-xs text-gray-500"><span>#{{ $match->position }}</span><span>{{ $statusNames[$match->status] ?? $match->status }}</span></div>
    @foreach([[1,$match->participant_one_seed,$match->participantOneEntry],[2,$match->participant_two_seed,$match->participantTwoEntry]] as [$slot,$seed,$entry])
    <div class="grid min-h-12 grid-cols-[2rem_minmax(0,1fr)_auto] items-center gap-2 border-t px-3"><span class="text-xs font-bold text-gray-400">{{ $seed ?? '—' }}</span><span class="truncate text-sm font-semibold">{{ $entry?->athlete_name ?? ($match->round_number === 1 ? '輪空' : '等待前場勝者') }}</span>@if($entry)<strong class="text-lg text-indigo-700">{{ $bracket->scoring_mode === 'set' ? ($slot === 1 ? $match->participant_one_set_points : $match->participant_two_set_points) : ($slot === 1 ? $match->participant_one_total : $match->participant_two_total) }}</strong>@endif</div>
    @endforeach
    @if($bracket->scoring_mode === 'set' && $match->sets->isNotEmpty())<div class="border-t bg-gray-50 px-3 py-2 text-xs text-gray-500">@foreach($match->sets as $set)<span class="mr-2">第{{ $set->set_number }}局 {{ $set->participant_one_total }}–{{ $set->participant_two_total }}</span>@endforeach</div>@elseif($bracket->scoring_mode === 'cumulative' && $match->ends->isNotEmpty())<div class="border-t bg-gray-50 px-3 py-2 text-xs text-gray-500">已完成 {{ $match->ends->count() }} / 5 趟</div>@endif
    @if($match->shootOffs->isNotEmpty())<div class="border-t bg-amber-50 px-3 py-2 text-xs font-medium text-amber-800">加射：@foreach($match->shootOffs as $shootOff)<span class="mr-2">#{{ $shootOff->attempt_number }} {{ $shootOff->participant_one_arrow }}–{{ $shootOff->participant_two_arrow }} {{ $shootOff->status === 're_shoot' ? '重射' : ($shootOff->status === 'pending_judge' ? '待判定' : '已判定') }}</span>@endforeach</div>@endif
    @if($management && $match->participant_one_registration_id && $match->participant_two_registration_id)<a href="{{ route('organizer.events.elimination.matches.show', [$event, $match]) }}" class="flex min-h-11 items-center justify-center border-t bg-indigo-50 text-sm font-semibold text-indigo-700">{{ $match->status === 'completed' ? '查看完整成績' : '管理場次' }}</a>@endif
</article>
