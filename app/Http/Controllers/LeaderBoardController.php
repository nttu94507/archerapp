<?php

namespace App\Http\Controllers;

use App\Models\Round;
use App\Models\Score;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LeaderBoardController extends Controller
{
    //
    public function index(Request $request)
    {
        // Filters
        $bow   = $request->string('bow_type')->toString();
        $mode  = $request->string('mode')->toString(); // indoor / outdoor
        $round = $request->integer('round_id');
        $range = $request->string('range')->toString() ?: '90d'; // 30d/90d/180d/365d/all

        // Date range
        $dateFrom = null;
        if ($range !== 'all') {
            $days = match ($range) {
                '30d'  => 30,
                '90d'  => 90,
                '180d' => 180,
                '365d' => 365,
                default => 90,
            };
            $dateFrom = Carbon::now()->subDays($days)->startOfDay();
        }

        // Base query: aggregate各射手在期間內的成績（僅 verified）
        $q = Score::query()
            ->selectRaw('
                archers.id as archer_id,
                archers.name as archer_name,
                archers.bow_type,
                events.mode,
                rounds.name as round_name,
                SUM(scores.total_score) as sum_total,
                SUM(scores.arrow_count) as sum_arrows,
                SUM(scores.x_count) as sum_x,
                SUM(scores.ten_count) as sum_ten,
                -- 加權平均 sigma（用箭數當權重）
                CASE WHEN SUM(scores.arrow_count) > 0
                     THEN SUM(scores.stdev * scores.arrow_count) / SUM(scores.arrow_count)
                     ELSE 0 END as sigma,
                MAX(scores.total_score) as best_all_time,
                MAX(CASE WHEN scores.scored_at >= DATE_SUB(NOW(), INTERVAL 90 DAY) THEN scores.total_score ELSE 0 END) as best_90d,
                MAX(scores.scored_at) as last_active,
                COALESCE(ratings.elo, 1200) as Elo
            ')
            ->join('archers', 'archers.id', '=', 'scores.archer_id')
            ->join('events', 'events.id', '=', 'scores.event_id')
            ->join('rounds', 'rounds.id', '=', 'scores.round_id')
            ->leftJoin('ratings', 'ratings.archer_id', '=', 'archers.id')
            ->when($bow,  fn($q) => $q->where('archers.bow_type', $bow))
            ->when($mode, fn($q) => $q->where('events.mode', $mode))
            ->when($round, fn($q) => $q->where('rounds.id', $round))
            ->where('events.verified', true)
            ->when($dateFrom, fn($q) => $q->whereDate('scores.scored_at', '>=', $dateFrom))
            ->groupBy('archers.id', 'archers.name', 'archers.bow_type', 'events.mode', 'rounds.name', 'ratings.elo');

        // 包一層做衍生欄位：AAE、X_rate、ten_rate、PI、R
        $leaders = DB::query()
            ->fromSub($q, 't')
            ->selectRaw('
                t.archer_id,
                t.archer_name,
                t.bow_type,
                t.mode,
                t.round_name,
                t.sum_total,
                t.sum_arrows,
                t.sum_x,
                t.sum_ten,
                t.sigma,
                t.best_90d,
                t.last_active,
                t.Elo,
                -- 平均箭值（假設10環制；若你有11環規則，改成除以 per-arrow max）
                CASE WHEN t.sum_arrows > 0 THEN t.sum_total / t.sum_arrows ELSE 0 END as AAE,
                CASE WHEN t.sum_arrows > 0 THEN t.sum_x     / t.sum_arrows ELSE 0 END as X_rate,
                CASE WHEN t.sum_arrows > 0 THEN t.sum_ten   / t.sum_arrows ELSE 0 END as ten_rate
            ')
            ->selectRaw('
                -- PI：簡化版（0~1 左右）：0.55*AAE_norm + 0.25*X_rate + 0.20*ten_rate
                ( (CASE WHEN t.sum_arrows > 0 THEN (t.sum_total / t.sum_arrows) / 10 ELSE 0 END) * 0.55
                + (CASE WHEN t.sum_arrows > 0 THEN (t.sum_x     / t.sum_arrows)     ELSE 0 END) * 0.25
                + (CASE WHEN t.sum_arrows > 0 THEN (t.sum_ten   / t.sum_arrows)     ELSE 0 END) * 0.20
                ) as PI
            ')
            ->selectRaw('
                -- 綜合 R（延續我們前一則說明）：R = 0.7*PI + 0.3*Elo
                (0.7 * (
                    (CASE WHEN t.sum_arrows > 0 THEN (t.sum_total / t.sum_arrows) / 10 ELSE 0 END) * 0.55
                  + (CASE WHEN t.sum_arrows > 0 THEN (t.sum_x     / t.sum_arrows)     ELSE 0 END) * 0.25
                  + (CASE WHEN t.sum_arrows > 0 THEN (t.sum_ten   / t.sum_arrows)     ELSE 0 END) * 0.20
                ) + 0.3 * t.Elo) as R
            ');

        // 排序
        $sort = $request->string('sort')->toString() ?: 'R_desc';
        [$sortKey, $dir] = array_pad(explode('_', $sort), 2, 'desc');
        $sortable = ['R', 'PI', 'Elo', 'AAE', 'X_rate', 'ten_rate', 'sigma', 'best_90d', 'last_active'];
        if (!in_array($sortKey, $sortable, true)) $sortKey = 'R';
        $dir = strtolower($dir) === 'asc' ? 'asc' : 'desc';

        $leaders = $leaders->orderBy($sortKey, $dir)->orderBy('archer_name');

        // 分頁（或改成 ->get()）
        $leaders = $leaders->paginate(20);

        // Rounds 下拉用
        $rounds = Round::query()
            ->select('id', 'name', 'distance', 'target_face')
            ->orderBy('distance')->get();

        return view('leaderboards.index', [
            'leaders' => $leaders,
            'rounds'  => $rounds,
        ]);
    }

}
