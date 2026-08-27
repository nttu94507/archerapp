@php
    $mainRounds = $bracket->matches->where('match_type', 'main')->groupBy('round_number')->sortKeys();
    $bronze = $bracket->matches->firstWhere('match_type', 'bronze');
    $firstRoundCount = max(1, $mainRounds->first()?->count() ?? 1);
    $gridRows = $firstRoundCount * 2;
@endphp

<div class="elimination-tree overflow-x-auto p-4 sm:p-5">
    <div class="grid min-w-max grid-flow-col auto-cols-[18rem] gap-x-12" style="grid-template-rows:2rem repeat({{ $gridRows }}, minmax(2.75rem, auto));">
        @foreach($mainRounds as $round => $matches)
            @php
                $roundColumn = $loop->iteration;
            @endphp
            <h3 class="row-start-1 text-center text-sm font-semibold text-gray-600" style="grid-column:{{ $roundColumn }};">
                {{ $matches->first()->label }}
            </h3>

            @foreach($matches as $match)
                @php
                    $rowSpan = 2 ** (int) $round;
                    $rowStart = 2 + (($match->position - 1) * $rowSpan);
                @endphp
                <div
                    class="elimination-node relative flex items-center {{ (int) $round > 1 ? 'elimination-node-linked' : '' }}"
                    style="grid-column:{{ $roundColumn }};grid-row:{{ $rowStart }} / span {{ $rowSpan }};"
                >
                    @include('events._elimination-match-card', ['match'=>$match, 'bracket'=>$bracket, 'statusNames'=>$statusNames])
                </div>
            @endforeach
        @endforeach
    </div>

    @if($bronze)
        <div class="mt-6 ml-auto w-[18rem]">
            <h3 class="mb-3 text-center text-sm font-semibold text-amber-700">季軍賽</h3>
            @include('events._elimination-match-card', ['match'=>$bronze, 'bracket'=>$bracket, 'statusNames'=>$statusNames])
        </div>
    @endif
</div>

@once
<style>
    .elimination-node-linked::before {
        content: '';
        position: absolute;
        right: 100%;
        top: 50%;
        width: 3rem;
        border-top: 2px solid rgb(203 213 225);
    }
    .elimination-node-linked::after {
        content: '';
        position: absolute;
        right: calc(100% + 3rem);
        top: 25%;
        height: 50%;
        border-left: 2px solid rgb(203 213 225);
    }
    .elimination-node > article {
        position: relative;
        z-index: 1;
    }
</style>
@endonce
