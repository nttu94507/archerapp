<?php

namespace Database\Seeders;

use App\Models\Archer;
use App\Models\Event;
use App\Models\Rating;
use App\Models\Round;
use App\Models\Score;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LeaderboardDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 建立基本資料
        Round::factory()->count(2)->create(); // 18m / 70m
        Archer::factory()->count(60)->create();
        Event::factory()->count(25)->create();

        // 每位射手 3~10 場成績（分散到不同 event/round）
        Archer::all()->each(function ($archer) {
            $n = rand(3, 10);
            for ($i=0; $i<$n; $i++) {
                Score::factory()->create(['archer_id' => $archer->id]);
            }

            Rating::updateOrCreate(
                ['archer_id' => $archer->id],
                ['elo' => rand(1000, 1600), 'last_played_at' => now()->subDays(rand(0,180))]
            );
        });

    }
}
