<?php

namespace App\Http\Controllers;

use App\Models\ArcherySession;
use App\Models\ArcheryShot;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;


class DashboardController extends Controller
{

    public function index()
    {
        $userId = auth()->id();

        [$cmStart, $cmEnd] = $this->monthWindow('current');
        [$pmStart, $pmEnd] = $this->monthWindow('prev');

        $curr = $this->monthAgg($cmStart, $cmEnd);
        $prev = $this->monthAgg($pmStart, $pmEnd);

        // 月結指標打包
        $monthlyIndex = [
            // 量：用「% 變化」
            'arrows' => ['label' => '練習量（箭）', 'cur' => $curr['arrows'], 'prev' => $prev['arrows'], 'mode' => 'pct'],

            'sigma' => ['label' => 'σ（穩定度）', 'cur' => $curr['sigma'], 'prev' => $prev['sigma'], 'mode' => 'abs', 'invert' => true, 'fmt' => 3],
//            'hours' => ['label' => '總時長（h）', 'cur' => $curr['hours'], 'prev' => $prev['hours'], 'mode' => 'pct', 'fmt' => 1],

            // 品質：AAE、X%、10%（顯示「百分點」/ 同時附上 % 變化）
            'aae' => ['label' => '單箭均分', 'cur' => $curr['aae'], 'prev' => $prev['aae'], 'mode' => 'both', 'fmt' => 2],
            'x_rate' => ['label' => 'X 命中率', 'cur' => $curr['x_rate'], 'prev' => $prev['x_rate'], 'mode' => 'pp'],   // 百分點
            'ten_rate' => ['label' => '10 命中率', 'cur' => $curr['ten_rate'], 'prev' => $prev['ten_rate'], 'mode' => 'pp'],
            'active_days' => ['label' => '本月練習', 'cur' => $curr['active_days'], 'prev' => $prev['active_days'], 'mode' => 'pct'],

            // 穩定度：σ 越低越好 → 良性方向相反（invert）

        ];

        // 其餘你原本塞給 view 的資料...
        return view('dashboard.index', [
            'monthlyIndex' => $monthlyIndex,
        ]);
    }
    private function monthAgg(Carbon $from, Carbon $to): array
    {
        $userId = auth()->id();

        // 1) shots：跨所有場次的整體統計
        $shotAgg = ArcheryShot::query()
            ->whereHas('session', fn ($q) =>
            $q->where('user_id', $userId)
                ->whereBetween('created_at', [$from, $to])
            )
            ->selectRaw("
            COUNT(*) AS arrows,
            SUM(CASE WHEN is_x = 1 AND score = 10 THEN 1 ELSE 0 END) AS x_cnt,
            SUM(CASE WHEN score = 10 AND (is_x IS NULL OR is_x = 0) THEN 1 ELSE 0 END) AS ten_only,
            SUM(score) AS score_sum,
            STDDEV_SAMP(score) AS sigma
        ")
            ->first();

        // 2) sessions：活躍天數（以場次開始日期去重）
        //   若你要用 shots 的時間去重，把 DATE(created_at) 改為 shots 的欄位並 join
        $sessAgg = ArcherySession::query()
            ->where('user_id', $userId)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('COUNT(DISTINCT DATE(created_at)) AS active_days')
            ->first();

        // 3) 取值與安全轉型
        $arrows   = (int) ($shotAgg->arrows     ?? 0);
        $scoreSum = (int) ($shotAgg->score_sum  ?? 0);
        $x        = (int) ($shotAgg->x_cnt      ?? 0);
        $tenOnly  = (int) ($shotAgg->ten_only   ?? 0);
         $sigma = is_null($shotAgg->sigma) ? 0.0 : (float) $shotAgg->sigma;

        // 4) 指標
        $avgPerArrow = $arrows > 0 ? $scoreSum / $arrows : 0.0;  // AAE
        $xRate       = $arrows > 0 ? $x / $arrows         : 0.0;  // X%
        $tenRate     = $arrows > 0 ? $tenOnly / $arrows   : 0.0;  // 10%（不含X）

        return [
            'arrows'      => $arrows,
            'active_days' => (int) ($sessAgg->active_days ?? 0),
            'aae'         => $avgPerArrow,
            'x_rate'      => $xRate,
            'ten_rate'    => $tenRate,
             'sigma'     => $sigma,
        ];
    }


    private function monthWindow(string $rel = 'current'): array
    {
        $curStart = now()->startOfMonth();
        $curEnd = now()->copy()->endOfMonth();          // 含當月今天之後也OK
        $prevStart = now()->subMonthNoOverflow()->startOfMonth();
        $prevEnd = now()->subMonthNoOverflow()->endOfMonth();

        return $rel === 'prev'
            ? [$prevStart, $prevEnd]
            : [$curStart, $curEnd];
    }


}

