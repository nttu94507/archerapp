@php
    // 若外層變數名不同，做容錯統一成 $s
    $s = $s ?? $session ?? $archerySession ?? null;

    // 以 end_seq 分組、每組內依 shot_seq 排序
    $grouped = $s->shots->groupBy('end_seq')->sortKeys()->map(function ($shotsOfEnd) {
        return $shotsOfEnd->sortBy('shot_seq')->values();
    });

    // 每 End 箭數：優先用模型欄位，其次用第一組數量，最後預設 6
    $arrowsPerEnd = (int) ($s->arrows_per_end ?? optional($grouped->first())->count() ?? 6);

    // 箭值顯示（若你的欄位不是 value，改成 score/ring 等）
    $formatArrow = function($shot) {
        if (isset($shot->is_miss) && $shot->is_miss) return 'M';
        if (isset($shot->is_x) && $shot->is_x) return 'X';
        return (string) ($shot->value ?? $shot->score ?? '');
    };

    // 計算該 End 小計（X=10、M=0；需要 11 分可自行調整此處）
    $scoreOfEnd = function($shots) {
        $sum = 0;
        foreach ($shots as $a) {
            if (($a->is_m ?? false)) { $sum += 0; continue; }
            if (($a->is_x ?? false)) { $sum += 10; continue; }
            $sum += (int) ($a->value ?? $a->score ?? 0);
        }
        return $sum;
    };

    // 總分 / X / M（若模型已有欄位，用模型；否則 fallback 計算）
    $totalScore = $s->score_total ?? $s->shots->sum(function($a){
        if (($a->is_m ?? false)) return 0;
        if (($a->is_x ?? false)) return 10;
        return (int) ($a->value ?? $a->score ?? 0);
    });
    $totalX = $s->x_count ?? $s->shots->where('is_x', true)->count();
    $totalM = $s->m_count ?? $s->shots->where('is_miss', true)->count();
@endphp

<div class="overflow-auto ">
    <tr class="hover:bg-gray-50/60 md:hover:bg-gray-50/60 cursor-pointer md:cursor-default"
        data-mobile-link="{{ $mobileUrl }}"
        role="link" tabindex="0">
        <td class="px-3 py-2 align-top text-sm">
            <div class="flex items-center justify-between gap-3 w-full whitespace-nowrap">
                <span class="font-mono tabular-nums text-lg font-bold">{{ $s->score_total }}/{{ $s->distance_m }}公尺</span>

                <span class="font-mono tabular-nums">{{ $s->created_at->format('Y-m-d H:i') }}</span>

            </div>
        </td>

        <td class="px-3 py-2 align-top">
            <div class="flex flex-wrap justify-end gap-1 w-full">
                <span class="font-mono tabular-nums text-sm">{{ $s->arrows_total }}箭 / {{ $s->shots_max_end_seq }}回合 </span>
                <span class="font-mono tabular-nums text-sm">{{ ucfirst($s->bow_type) }} </span>

            </div>
        </td>
    </tr>
    <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-gray-50 text-[11px] uppercase text-gray-500">
        <tr>
{{--            <th class="px-3 py-2 text-left">End</th>--}}
            @for($i=1; $i<=$arrowsPerEnd; $i++)
                <th class="px-2 py-2 text-center">A{{ $i }}</th>
            @endfor
            <th class="px-3 py-2 text-right">小計</th>
            <th class="px-3 py-2 text-right">X</th>
            <th class="px-3 py-2 text-right">M</th>
        </tr>
        </thead>

        <tbody class="text-sm  divide-y divide-gray-100">
        @foreach($grouped as $endSeq => $shots)
            @php
                $endScore = $scoreOfEnd($shots);
                $xCount   = $shots->where('is_x', true)->count();
                $mCount   = $shots->where('is_m', true)->count();
            @endphp
            <tr class="hover:bg-gray-50">

                {{-- 該 End 各箭；不足補「—」 --}}
                @for($i=0; $i<$arrowsPerEnd; $i++)
                    @php $shot = $shots[$i] ?? null; @endphp
                    <td class="px-2 py-2 text-center font-mono tabular-nums">
                        @if($shot)
                            <span class="
                                {{ ($shot->is_x ?? false) ? 'text-rose-900 ' : '' }}
                                {{ ($shot->is_m ?? false) ? 'text-gray-400' : '' }}">
                                {{ $formatArrow($shot) }}
                            </span>
                        @else
                            <span class="text-gray-300">—</span>
                        @endif
                    </td>
                @endfor

                <td class="px-3 py-2 text-right font-mono font-semibold tabular-nums">{{ $endScore }}</td>
                <td class="px-3 py-2 text-right font-mono tabular-nums">{{ $xCount }}</td>
                <td class="px-3 py-2 text-right font-mono tabular-nums">{{ $mCount }}</td>
            </tr>
        @endforeach
        </tbody>

        <tfoot class="bg-gray-50">
        <tr>
            <td class="px-3 py-2 text-left font-medium">總計</td>
            <td colspan="5" class="px-2 py-2"></td>
            <td class="px-3 py-2 text-right font-mono font-semibold tabular-nums">{{ $totalScore }}</td>
            <td class="px-3 py-2 text-right font-mono tabular-nums">{{ $totalX }}</td>
            <td class="px-3 py-2 text-right font-mono tabular-nums">{{ $totalM }}</td>
        </tr>
        </tfoot>
    </table>
</div>
