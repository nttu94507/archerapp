<?php

namespace App\Http\Controllers;

use App\Models\ArcherySession;
use App\Models\ArcheryShot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ScoreController extends Controller
{
    //
    public function index()
    {
        $query = \App\Models\ArcherySession::query();

        // 你的篩選（沿用原本）
        if ($q = request('q'))         $query->where('note', 'like', "%{$q}%");
        if ($score = request('score')) $query->where('score_total', '>=', "{$score}");
        if ($bt = request('bow_type')) $query->where('bow_type', $bt);
        if ($v = request('venue'))     $query->where('venue', $v);
        if ($df = request('date_from')) $query->whereDate('created_at', '>=', $df);
        if ($dt = request('date_to'))   $query->whereDate('created_at', '<=', $dt);

        // 排序（沿用你的選項）
        $sort = request('sort', 'created_at');
        $dir  = request('dir', 'desc');
        $allowed = ['created_at','score_total','distance_m'];
        if (!in_array($sort, $allowed, true)) $sort = 'created_at';
        if (!in_array($dir, ['asc','desc'], true)) $dir = 'desc';

        $sessions = $query
            ->with([
                'shots' => function ($q) {
                    $q->orderBy('end_seq')
                        ->orderBy('shot_seq', 'asc'); // 若你的欄位是 sequence/index，改這裡
                },
            ])
            ->orderBy($sort, $dir)
            ->paginate(10)
            ->withQueryString();

        return view('scores.index', compact('sessions'));
    }

    public function create(Request $request)
    {
        // 給前端預設值（若沒傳就用預設）
        $defaults = [
            'bow_type'       => $request->string('bow_type', 'recurve')->toString(),
            'venue'          => $request->string('venue', 'indoor')->toString(),
            'distance'       => (int) $request->input('distance', 18),
            'arrows_total'   => (int) $request->input('arrows_total', 30),
            'arrows_per_end' => (int) $request->input('arrows_per_end', 6),
        ];

        return view('scores.create', compact('defaults'));
    }

    public function store(Request $request)
    {
        // 1) 先驗 payload 存在
        $request->validate([
            'payload' => ['required', 'string'],
            'note'    => ['nullable', 'string', 'max:255'],
        ]);

        $payload = json_decode($request->input('payload'), true);

        if (!is_array($payload) || empty($payload['meta']) || empty($payload['scores'])) {
            throw ValidationException::withMessages(['payload' => '資料格式不正確']);
        }

        $meta   = $payload['meta'];
        $scores = $payload['scores'];
        $isMiss = $payload['isMiss'] ?? [];
        $isX    = $payload['isX']    ?? [];

        // 2) 驗基本欄位（bow/venue/distance/...）
        $bowWhitelist   = ['recurve','compound','barebow','yumi','longbow'];
        $venueWhitelist = ['indoor','outdoor'];

        $bow            = $meta['bow']             ?? null;
        $venue          = $meta['venue']           ?? null;
        $distance       = (int)($meta['distance']  ?? 0);
        $arrowsTotal    = (int)($meta['arrows_total']   ?? 0);
        $arrowsPerEnd   = (int)($meta['arrows_per_end'] ?? 0);

        if (!in_array($bow, $bowWhitelist, true)) {
            throw ValidationException::withMessages(['payload' => 'bow_type 不在允許清單']);
        }
        if (!in_array($venue, $venueWhitelist, true)) {
            throw ValidationException::withMessages(['payload' => 'venue 不在允許清單']);
        }
        if ($distance < 5 || $distance > 150) {
            throw ValidationException::withMessages(['payload' => 'distance 超出範圍']);
        }
        if ($arrowsTotal < 1 || $arrowsTotal > 300) {
            throw ValidationException::withMessages(['payload' => '總箭數超出範圍']);
        }
        if ($arrowsPerEnd < 1 || $arrowsPerEnd > 12) {
            throw ValidationException::withMessages(['payload' => '每趟箭數超出範圍']);
        }

        // 3) 彙總計算
        $scoreTotal = 0; $xCount = 0; $mCount = 0;

        // 建議把缺失的 isMiss / isX 補成 false，以避免索引錯位
        $endsCount = max(1, (int)ceil($arrowsTotal / $arrowsPerEnd));

        // 4) 寫入 DB（transaction）
        $session = DB::transaction(function () use (
            $request, $bow, $venue, $distance, $arrowsTotal, $arrowsPerEnd,
            $scores, $isMiss, $isX, $endsCount, &$scoreTotal, &$xCount, &$mCount
        ) {
            // 4-1) 建 session（先不填總分，最後再回寫）
            $session = ArcherySession::create([
                'user_id'        => $request->user()->id,
                'bow_type'       => $bow,
                'venue'          => $venue,
                'distance_m'     => $distance,
                'arrows_total'   => $arrowsTotal,
                'arrows_per_end' => $arrowsPerEnd,
                'note'           => $request->input('note'),
            ]);

            // 4-2) 展開每箭 → 準備 bulk insert
            $now = now();
            $toInsert = [];

            for ($e = 0; $e < $endsCount; $e++) {
                $rowScores = $scores[$e]  ?? [];
                $rowMiss   = $isMiss[$e]  ?? [];
                $rowX      = $isX[$e]     ?? [];

                // 此 end 的實際箭數（最後一回合可能不足 arrows_per_end）
                $shotsThisEnd = min($arrowsPerEnd, $arrowsTotal - $e * $arrowsPerEnd);
                if ($shotsThisEnd <= 0) break;

                for ($i = 0; $i < $shotsThisEnd; $i++) {
                    $v   = (int)($rowScores[$i] ?? 0);
                    $mx  = (bool)($rowMiss[$i]  ?? false);
                    $x10 = (bool)($rowX[$i]     ?? false);

                    // 正常化：分數 0..11、X 記 10 分、Miss 記 0 分
                    $v = max(0, min(11, $v));
                    if ($x10) $v = 10;
                    if ($mx)  $v = 0;

                    $scoreTotal += $v;
                    if ($x10 && $v === 10) $xCount++;
                    if ($mx  && $v === 0)  $mCount++;

                    $toInsert[] = [
                        'session_id' => $session->id,
                        'end_seq'    => $e + 1,    // 1-based
                        'shot_seq'   => $i + 1,    // 1-based
                        'score'      => $v,
                        'is_x'       => $x10,
                        'is_miss'    => $mx,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            // 4-3) 寫入 shots（一次 bulk insert 比逐筆快）
            if (!empty($toInsert)) {
                ArcheryShot::insert($toInsert);
            }

            // 4-4) 回寫 session 彙總
            $session->update([
                'score_total' => $scoreTotal,
                'x_count'     => $xCount,
                'm_count'     => $mCount,
            ]);

            return $session;
        });

        return redirect()
            ->route('scores.show', $session)
            ->with('success', '訓練成績已儲存');
    }


    public function show(ArcherySession $score)
    {
//        dd(123);
        // 依 end_seq、shot_seq 排好回傳
        $shots = $score->shots()->orderBy('end_seq')->orderBy('shot_seq')->get();

        // 也可以算每趟合計
        $endSums = $score->shots()
            ->selectRaw('end_seq, SUM(score) AS end_sum')
            ->groupBy('end_seq')
            ->orderBy('end_seq')
            ->get();

        $cumu = 0;
        $endRows = $endSums->map(function ($row) use (&$cumu) {
            $cumu += (int)$row->end_sum;
            return [
                'end_seq'    => (int)$row->end_seq,
                'end_sum'    => (int)$row->end_sum,
                'cumulative' => $cumu,
            ];
        });

        return view('scores.show', [
            'session' => $score,
            'shots'   => $shots,
            'ends'    => $endRows,
        ]);
    }

    public function setup()
    {
        return view('scores.setup');
    }
}
