<?php

namespace App\Services;

use App\Models\{BracketMatch as M, Tournament};

class BracketService
{
    public function build(Tournament $tournament)
    {
        $participants = $tournament->participants()->orderByRaw('COALESCE(seed, 9999) ASC, id ASC')->get()->all();
        $n = count($participants);
        $size = 1;
        while ($size < $n) $size <<= 1; // 最近 2 次方
        $tournament->update(['size_power_of_two' => $size]);

// 補 bye（以 null 代表空位，或建立一個虛擬 participant 亦可）
        while (count($participants) < $size) {
            $participants[] = null;
        }

// 典型種子對位法（1 vs last, 2 vs last-1, ... serpent 亦可）
        $ordered = $this->seedPairing($participants);

// 建立第一輪 matches
        $round = 1;
        $positions = intdiv($size, 2);
        $matchesByRound = [];

        for ($i = 0; $i < $positions; $i++) {
            [$p1, $p2] = [$ordered[$i * 2] ?? null, $ordered[$i * 2 + 1] ?? null];
            $match = M::create([
                'tournament_id' => $tournament->id,
                'round' => $round,
                'position' => $i + 1,
                'p1_id' => $p1?->id,
                'p2_id' => $p2?->id,
            ]);
            $matchesByRound[$round][] = $match;
        }

// 後續回合（只定義空位，等上一輪決出勝者回填）
        $prev = $matchesByRound[$round];
        while (count($prev) > 1) {
            $round++;
            $curr = [];
            for ($i = 0; $i < count($prev); $i += 2) {
                $m = M::create([
                    'tournament_id' => $tournament->id,
                    'round' => $round,
                    'position' => ($i / 2) + 1,
                ]);
// 將上一輪兩場的勝者連到這一場
                $prev[$i]->update(['next_match_id' => $m->id, 'next_slot' => 1]);
                $prev[$i + 1]->update(['next_match_id' => $m->id, 'next_slot' => 2]);
                $curr[] = $m;
            }
            $matchesByRound[$round] = $curr;
            $prev = $curr;
        }

// bye 自動晉級（若 p1/p2 有一方為 null，直接設定 winner 並推進）
        foreach ($matchesByRound[1] as $m) {
            if ($m->p1_id && !$m->p2_id) $this->setWinner($m, $m->p1_id);
            if ($m->p2_id && !$m->p1_id) $this->setWinner($m, $m->p2_id);
        }
    }

    private function seedPairing(array $participants)
    {
// 簡單的 1 vs last, 2 vs last-1 ...（可換蛇形）
        $res = [];
        $l = 0;
        $r = count($participants) - 1;
        while ($l <= $r) {
            $res[] = $participants[$l++] ?? null;
            if ($l <= $r) $res[] = $participants[$r--] ?? null;
        }
        return $res;
    }

    public function setWinner(M $match, int $winnerId)
    {
        $match->update(['winner_id' => $winnerId]);
// 推進到下一輪
        if ($match->next_match_id && $match->next_slot) {
            $next = M::find($match->next_match_id);
            if ($match->next_slot === 1) $next->update(['p1_id' => $winnerId]);
            else $next->update(['p2_id' => $winnerId]);
        }
    }
}
