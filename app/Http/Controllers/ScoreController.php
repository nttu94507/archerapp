<?php

namespace App\Http\Controllers;

use App\Models\ArcherySession;
use App\Models\ArcheryShot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ScoreController extends Controller
{
    //
    public function index()
    {
        $query = \App\Models\ArcherySession::query()
            ->where('user_id', Auth::id());;

        // 你的篩選（沿用原本）
        if ($q = request('q')) $query->where('note', 'like', "%{$q}%");
        if ($score = request('score')) $query->where('score_total', '>=', "{$score}");
        if ($bt = request('bow_type')) $query->where('bow_type', $bt);
        if ($v = request('venue')) $query->where('venue', $v);
        if ($df = request('date_from')) $query->whereDate('created_at', '>=', $df);
        if ($dt = request('date_to')) $query->whereDate('created_at', '<=', $dt);

        // 排序（沿用你的選項）
        $sort = request('sort', 'created_at');
        $dir = request('dir', 'desc');
        $allowed = ['created_at', 'score_total', 'distance_m'];
        if (!in_array($sort, $allowed, true)) $sort = 'created_at';
        if (!in_array($dir, ['asc', 'desc'], true)) $dir = 'desc';

        $sessions = $query
            ->with([
                'shots' => function ($q) {
                    $q->orderBy('end_seq')
                        ->orderBy('shot_seq', 'asc'); // 若你的欄位是 sequence/index，改這裡
                },
            ])
            ->withMax('shots', 'end_seq')
            ->orderBy($sort, $dir)
            ->paginate(10)
            ->withQueryString();

        return view('scores.index', compact('sessions'));
    }

    public function create(Request $request)
    {
        // 給前端預設值（若沒傳就用預設）
        $defaults = [
            'bow_type' => $request->string('bow_type', 'recurve')->toString(),
            'venue' => $request->string('venue', 'indoor')->toString(),
            'distance' => (int)$request->input('distance', 18),
            'arrows_total' => (int)$request->input('arrows_total', 30),
            'arrows_per_end' => (int)$request->input('arrows_per_end', 6),
        ];

        return view('scores.create', compact('defaults'));
    }

    public function store(Request $request)
    {
        // 1) 先驗 payload 存在
        $request->validate([
            'payload' => ['required', 'string'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $payload = json_decode($request->input('payload'), true);

        if (!is_array($payload) || empty($payload['meta']) || empty($payload['scores'])) {
            throw ValidationException::withMessages(['payload' => '資料格式不正確']);
        }

        $meta = $payload['meta'];
        $scores = $payload['scores'];
        $isMiss = $payload['isMiss'] ?? [];
        $isX = $payload['isX'] ?? [];

        // 2) 驗基本欄位（bow/venue/distance/...）
        $bowWhitelist = ['recurve', 'compound', 'barebow', 'yumi', 'longbow'];
        $venueWhitelist = ['indoor', 'outdoor'];

        $bow = $meta['bow'] ?? null;
        $venue = $meta['venue'] ?? null;
        $distance = (int)($meta['distance'] ?? 0);
        $arrowsTotal = (int)($meta['arrows_total'] ?? 0);
        $arrowsPerEnd = (int)($meta['arrows_per_end'] ?? 0);

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
        $scoreTotal = 0;
        $xCount = 0;
        $mCount = 0;

        // 建議把缺失的 isMiss / isX 補成 false，以避免索引錯位
        $endsCount = max(1, (int)ceil($arrowsTotal / $arrowsPerEnd));

        // 4) 寫入 DB（transaction）
        $session = DB::transaction(function () use (
            $request, $bow, $venue, $distance, $arrowsTotal, $arrowsPerEnd,
            $scores, $isMiss, $isX, $endsCount, &$scoreTotal, &$xCount, &$mCount
        ) {
            // 4-1) 建 session（先不填總分，最後再回寫）
            $session = ArcherySession::create([
                'user_id' => $request->user()->id,
                'bow_type' => $bow,
                'venue' => $venue,
                'distance_m' => $distance,
                'arrows_total' => $arrowsTotal,
                'arrows_per_end' => $arrowsPerEnd,
                'note' => $request->input('note'),
            ]);

            // 4-2) 展開每箭 → 準備 bulk insert
            $now = now();
            $toInsert = [];

            for ($e = 0; $e < $endsCount; $e++) {
                $rowScores = $scores[$e] ?? [];
                $rowMiss = $isMiss[$e] ?? [];
                $rowX = $isX[$e] ?? [];

                // 此 end 的實際箭數（最後一回合可能不足 arrows_per_end）
                $shotsThisEnd = min($arrowsPerEnd, $arrowsTotal - $e * $arrowsPerEnd);
                if ($shotsThisEnd <= 0) break;

                for ($i = 0; $i < $shotsThisEnd; $i++) {
                    $v = (int)($rowScores[$i] ?? 0);
                    $mx = (bool)($rowMiss[$i] ?? false);
                    $x10 = (bool)($rowX[$i] ?? false);

                    // 正常化：分數 0..11、X 記 10 分、Miss 記 0 分
                    $v = max(0, min(11, $v));
                    if ($x10) $v = 10;
                    if ($mx) $v = 0;

                    $scoreTotal += $v;
                    if ($x10 && $v === 10) $xCount++;
                    if ($mx && $v === 0) $mCount++;

                    $toInsert[] = [
                        'session_id' => $session->id,
                        'end_seq' => $e + 1,    // 1-based
                        'shot_seq' => $i + 1,    // 1-based
                        'score' => $v,
                        'is_x' => $x10,
                        'is_miss' => $mx,
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
                'x_count' => $xCount,
                'm_count' => $mCount,
            ]);

            return $session;
        });

        return redirect()
            ->route('scores.show', $session)
            ->with('success', '訓練成績已儲存');
    }


    public function show(ArcherySession $score)
    {
        // 依 end_seq、shot_seq 排好回傳
        $shots = $score->shots()
            ->orderBy('end_seq')
            ->orderBy('shot_seq')
            ->get();

        // 每趟合計（你原本的）
        $endSums = $score->shots()
            ->selectRaw('end_seq, SUM(score) AS end_sum')
            ->groupBy('end_seq')
            ->orderBy('end_seq')
            ->get();

        // 累計
        $cumu = 0;
        $endRows = $endSums->map(function ($row) use (&$cumu) {
            $cumu += (int)$row->end_sum;
            return [
                'end_seq' => (int)$row->end_seq,
                'end_sum' => (int)$row->end_sum,
                'cumulative' => $cumu,
            ];
        });

        // ====== 新增：分析資料 ======
        $totalArrows = $shots->count();
        $per = (int)$score->arrows_per_end;

        // 分值統計（0~10）＋ X/M 計數（X: is_x 且 score=10；M: is_miss 且 score=0）
        $scoreDist = array_fill(0, 11, 0);
        $xCount = 0;
        $missCount = 0;
        foreach ($shots as $s) {
            $val = (int)$s->score;
            if (isset($scoreDist[$val])) $scoreDist[$val]++;
            if (($s->is_x ?? false) && $val === 10) $xCount++;
            if (($s->is_miss ?? false) && $val === 0) $missCount++;
        }

        // 9/10 命中
        $over9 = $shots->where('score', '>=', 9)->count();
        $avg = $totalArrows ? round($shots->avg('score'), 2) : 0;
        $xRate = $totalArrows ? round($xCount / $totalArrows * 100, 1) : 0.0;
        $mRate = $totalArrows ? round($missCount / $totalArrows * 100, 1) : 0.0;
        $nineUpRate = $totalArrows ? round($over9 / $totalArrows * 100, 1) : 0.0;
        // 標準差（母體與樣本）
        $scores = $shots->pluck('score')->map(fn($v) => (int)$v);
        $mean = $totalArrows ? $scores->avg() : 0.0;

// 母體標準差：sqrt( Σ(x-mean)^2 / n )
        $variancePop = $totalArrows > 0
            ? $scores->reduce(fn($carry, $v) => $carry + pow($v - $mean, 2), 0) / $totalArrows
            : 0.0;
        $stddevPop = round(sqrt($variancePop), 2);

// 樣本標準差：sqrt( Σ(x-mean)^2 / (n-1) )
        $varianceSample = $totalArrows > 1
            ? $scores->reduce(fn($carry, $v) => $carry + pow($v - $mean, 2), 0) / ($totalArrows - 1)
            : 0.0;
        $stddevSample = round(sqrt($varianceSample), 2);

        // 以 end_seq 分前半 / 後半
        $endsGrouped = $shots->groupBy('end_seq')->sortKeys();
        $endCount    = $endsGrouped->count();
        if ($endCount > 0) {
            $mid = (int) ceil($endCount / 2);

            $firstHalfShots = $shots->where('end_seq', '<=', $mid);
            $secondHalfShots = $shots->where('end_seq', '>',  $mid);

            $firstAvg  = $firstHalfShots->count()  ? round($firstHalfShots->avg('score'), 2)  : null; // 每箭平均
            $secondAvg = $secondHalfShots->count() ? round($secondHalfShots->avg('score'), 2) : null;

            $staminaDelta = (!is_null($firstAvg) && !is_null($secondAvg))
                ? round($secondAvg - $firstAvg, 2)
                : null;
        } else {
            $firstAvg = $secondAvg = $staminaDelta = null;
        }

        $analysis = [
            'avg' => $avg,
            'stddev'        => $stddevPop,      // 母體標準差
            'stddevSample'  => $stddevSample,   // 樣本標準差
            'staminaDelta' => $staminaDelta, // 後勁指數
            'firstHalfAvg' => $firstAvg,     // 前半平均
            'secondHalfAvg'=> $secondAvg,   // 後半平均
            'xCount' => $xCount,
            'missCount' => $missCount,
            'xRate' => $xRate,
            'mRate' => $mRate,
            'nineUpRate' => $nineUpRate,
            'scoreDist' => $scoreDist,          // 0..10
            'totalArrows' => $totalArrows,
            'per' => $per,
            // 給圖表用
            'perEnd' => $endRows->pluck('end_sum')->values(),      // [每趟合計...]
            'cumu' => $endRows->pluck('cumulative')->values(),   // [累計...]
        ];

        $summary = $this->analysis($analysis);

        return view('scores.show', [
            'session' => $score,
            'shots' => $shots,
            'ends' => $endRows,
            'analysis' => $analysis,   // 👈 新增傳到 view
            'summary' => $summary,
        ]);
    }


    public function setup()
    {
        return view('scores.setup');
    }

    /**
     * 依分析資料產生嘴砲總結
     * @param array $a 需含 keys: avg, xCount, missCount, xRate, mRate, nineUpRate, totalArrows, perEnd(Collection|array)
     * @return array{rule:string,text:string,level:string,stats:array}
     */
    private function analysis(array $a): array
    {
        $spicyMode = true;

        // 取值（並做安全預設）
        $avg = (float)($a['avg'] ?? 0);
        $xCount = (int)($a['xCount'] ?? 0);
        $missCount = (int)($a['missCount'] ?? 0);
        $xRate = (float)($a['xRate'] ?? 0);   // %
        $mRate = (float)($a['mRate'] ?? 0);   // %
        $nineUpRate = (float)($a['nineUpRate'] ?? 0);   // %
        $total = (int)($a['totalArrows'] ?? 0);
        $perEnd = $a['perEnd'] ?? [];

        // 穩定度：每趟合計的區間（max-min）
        if ($perEnd instanceof \Illuminate\Support\Collection) {
            $consistency = $perEnd->count() > 1 ? ((int)$perEnd->max() - (int)$perEnd->min()) : null;
        } else {
            $vals = array_values((array)$perEnd);
            $consistency = count($vals) > 1 ? (max($vals) - min($vals)) : null;
        }

        // 台詞庫
        $lines = [
            'tooManyMiss' => [
                '失誤太多啦～再多練練吧菜逼八 😈',
                '空氣切割大師認證 🥷（M 有點多）',
                '靶心：我在這裡；你：我在別處。M：我都在。🤡',
                'M 比靶還大，這弓是不是開錯方向了？🤡',
                '你不是在射箭，是在表演空氣劍術 🥷',
                '靶心在哭：「他根本沒看我一眼」😭',
                'M 比成績多，這局直接退賽重練吧 😭',
            ],
            'godLike' => [
                '今日弓神降臨，X 噴到停不下來！🔥',
                '你是來還債的吧？把 X 還太多了 😎',
                '穩到像掛，裁判都想盤你手！🧙‍♂️',
                '弓神降臨，連風都替你瞄準了 🔥',
                'X 多到靶紙都快報警了 😎',
                '你射的不是箭，是主宰命運的光 🧙‍♂️',
                '別射了，再射評審要檢舉你開外掛 ⚡',
            ],
            'solid' => [
                '表現穩健，漂亮～保持節奏就能一路起飛 ✈️',
                '這波很紮實，繼續維持就對了 💪',
                '節奏在線，細節再抹一點就更香 👌',
                '這波穩得像教科書，射箭科代表 🎯',
                '節奏漂亮，感覺你跟弓已經訂婚了 💍',
                '穩得我都想請你當代射顧問 😌',
                '沒什麼好說的，就是職業水準 👏',
            ],
            'unstable' => [
                '一會兒天神一會兒凡人，手感忽冷忽熱 🥶🥵',
                '波動略大，把呼吸與出手時機卡穩點 ⏱️',
                '穩定度不太行，讓節奏當你的朋友 📉',
                '一發神箭一發謎團，你是 RNG 附身嗎？🎲',
                '今天是靠手感決定命運的一天 🫠',
                '有時天神、有時凡人，射箭版雙重人格 🤯',
                '靶心看到你都懷疑人生：你到底要不要射我 😵',
            ],
            'lowAvg' => [
                '平均有點低，再多摸摸弓才有感情啦～🥺',
                '先別對靶心放電，多對靶紙放點箭 🫡',
                '這把偏養生，火力不夠。加把勁！🧪',
                '平均低到像在射月亮 🌕',
                '建議先跟靶紙交朋友，再談命中 💔',
                '這成績⋯連風都替你尷尬了 🫠',
                '你不是沒射中，你只是射進另一個次元 😭',
            ],
            'ok' => [
                '還行！下一場多幾個 X 就完美 ✨',
                '方向對了，有進步空間 👍',
                '穩紮穩打，再加點狠勁！🧱',
                '中規中矩，再努力一點就能少挨兩句罵 😏',
                '還行啦～至少沒射到隔壁靶 👍',
                '這分數看起來像暖身而已，下一場該認真了 😬',
                '穩中帶菜，有潛力當射箭界打醬油之王 🧂',
            ],
        ];
        $pick = static fn(array $arr) => $arr[array_rand($arr)];

        // 規則（由上往下匹配）
        $rule = 'ok';
        $text = $pick($lines['ok']);
        $level = 'neutral';

        if ($missCount >= 10 || $mRate >= 12) {
            $rule = 'tooManyMiss';
            $text = $pick($lines['tooManyMiss']);
            $level = 'bad';
        } elseif ($xRate >= 25 || $xCount >= 10) {
            $rule = 'godLike';
            $text = $pick($lines['godLike']);
            $level = 'great';
        } elseif ($nineUpRate >= 55 && $avg >= 8.5) {
            $rule = 'solid';
            $text = $pick($lines['solid']);
            $level = 'good';
        } elseif ($consistency !== null && $consistency >= 12) {
            $rule = 'unstable';
            $text = $pick($lines['unstable']);
            $level = 'warn';
        } elseif ($avg <= 6.5) {
            $rule = 'lowAvg';
            $text = $pick($lines['lowAvg']);
            $level = 'warn';
        }

        // 溫和模式（可改成用 .env 控制）
        if (!$spicyMode) {
            $text = strtr($text, ['菜逼八' => '同學', '🤡' => '🙂', '😈' => '😉']);
        }

        return [
            'rule' => $rule,
            'text' => $text,
            'level' => $level,
            'stats' => [
                'avg' => $avg,
                'xRate' => $xRate,
                'mRate' => $mRate,
                'nineUpRate' => $nineUpRate,
                'consistency' => $consistency,
                'total' => $total,
            ],
        ];
    }
}
