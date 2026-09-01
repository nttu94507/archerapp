<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\EventStaff;
use App\Models\User;
use App\Models\UserProfile;
use App\Support\EventPlanCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SeedDemoEvent extends Command
{
    protected $signature = 'demo:seed-event
        {--owner=demo.organizer@example.test : 主辦方帳號 Email；不存在時自動建立}
        {--athletes=8 : 每個組別建立的選手與報名數}
        {--groups=3 : 建立組別數，最多 6 組；免費版固定 1 組}
        {--mode=outdoor : outdoor 或 indoor}
        {--free : 建立免費版賽事，否則預設為單場付費版}
        {--check-in : 啟用報到，並將所有測試選手標記為已報到}';

    protected $description = '建立可立即測試報名、排靶與計分的 Demo 賽事及選手資料';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('此指令只允許在 local 或 testing 環境執行。');

            return self::FAILURE;
        }

        $mode = (string) $this->option('mode');
        $athletesPerGroup = (int) $this->option('athletes');
        $requestedGroups = (int) $this->option('groups');
        $free = (bool) $this->option('free');
        $checkIn = (bool) $this->option('check-in') && ! $free;

        if (! in_array($mode, ['outdoor', 'indoor'], true)) {
            $this->error('--mode 只能是 outdoor 或 indoor。');

            return self::INVALID;
        }
        if ($athletesPerGroup < 1 || $athletesPerGroup > 128) {
            $this->error('--athletes 必須介於 1～128，且代表每個組別的人數。');

            return self::INVALID;
        }
        if ($requestedGroups < 1 || $requestedGroups > 6) {
            $this->error('--groups 必須介於 1～6。');

            return self::INVALID;
        }
        if ($free && $athletesPerGroup > 32) {
            $this->error('免費版每組最多建立 32 位測試選手。');

            return self::INVALID;
        }

        $groupCount = $free ? 1 : $requestedGroups;
        $batch = now()->format('YmdHis').'-'.Str::lower(Str::random(4));
        $password = 'password';
        $ownerEmail = mb_strtolower(trim((string) $this->option('owner')));

        try {
            [$event, $owner, $ownerCreated, $groups, $createdUsers] = DB::transaction(function () use (
                $mode, $athletesPerGroup, $groupCount, $free, $checkIn, $batch, $password, $ownerEmail
            ): array {
                $owner = User::where('email', $ownerEmail)->first();
                $ownerCreated = $owner === null;
                $owner ??= User::create([
                    'name'=>'Demo 主辦方', 'email'=>$ownerEmail, 'password'=>$password,
                    'email_verified_at'=>now(),
                ]);
                $owner->forceFill(['profile_completed_at'=>$owner->profile_completed_at ?: now()])->save();

                $plan = $free ? EventPlanCatalog::FREE : EventPlanCatalog::EVENT_PASS;
                $event = Event::create([
                    'name'=>'Demo 射箭賽 '.$batch,
                    'start_date'=>today()->addDays(7), 'end_date'=>today()->addDays(7),
                    'mode'=>$mode, 'verified'=>true, 'level'=>'local',
                    'organizer'=>'Demo 測試主辦方', 'venue'=>'Demo 測試射箭場',
                    'reg_start'=>now()->subDay(), 'reg_end'=>now()->addDays(6),
                    'status'=>'approved', 'published_at'=>now(), 'visibility'=>$free ? 'public' : 'unlisted',
                    'check_in_enabled'=>$checkIn,
                    'plan_code'=>$plan, 'plan_status'=>EventPlanCatalog::STATUS_ACTIVE,
                    'plan_limits_snapshot'=>EventPlanCatalog::limits($plan),
                    'plan_features_snapshot'=>EventPlanCatalog::features($plan),
                    'plan_activated_at'=>now(),
                ]);
                EventStaff::create([
                    'event_id'=>$event->id, 'user_id'=>$owner->id, 'role'=>'owner',
                    'status'=>'active', 'invited_by'=>$owner->id, 'accepted_at'=>now(),
                ]);

                $presets = $this->presets($mode);
                $groups = collect(array_slice($presets, 0, $groupCount))->map(function (array $preset) use ($event, $free): \App\Models\EventGroup {
                    return $event->groups()->create(array_merge($preset, [
                        'arrow_count'=>$event->mode === 'indoor' ? ($free ? 30 : 60) : ($free ? 36 : 72),
                        'arrows_per_end'=>$event->mode === 'indoor' ? 3 : 6,
                        'quota'=>null, 'fee'=>$free ? 0 : 500,
                        'is_team'=>! $free, 'standard_team_enabled'=>! $free,
                        'mixed_team_enabled'=>! $free, 'team_size'=>3, 'team_type'=>'standard',
                    ]));
                });

                $createdUsers = collect();
                foreach ($groups as $groupIndex => $group) {
                    for ($index = 1; $index <= $athletesPerGroup; $index++) {
                        $gender = $index % 2 === 0 ? 'female' : 'male';
                        $email = sprintf('demo.%s.g%02d.a%03d@example.test', $batch, $groupIndex + 1, $index);
                        $user = User::create([
                            'name'=>sprintf('Demo 選手 G%02d-%03d', $groupIndex + 1, $index),
                            'email'=>$email, 'password'=>$password, 'email_verified_at'=>now(),
                        ]);
                        $user->forceFill(['profile_completed_at'=>now()])->save();
                        UserProfile::create([
                            'user_id'=>$user->id, 'gender'=>$gender,
                            'bow_type'=>$group->bow_type, 'handedness'=>'right', 'club_name'=>'Demo 射箭隊',
                        ]);
                        EventRegistration::create([
                            'event_id'=>$event->id, 'event_group_id'=>$group->id, 'user_id'=>$user->id,
                            'name'=>$user->name, 'email'=>$user->email, 'team_name'=>'Demo 射箭隊',
                            'athlete_gender'=>$gender, 'status'=>$checkIn ? 'checked_in' : 'registered',
                            'paid'=>! $free, 'payment_status'=>$free ? 'exempt' : 'paid',
                            'payment_amount'=>$free ? 0 : 500,
                            'payment_confirmed_at'=>! $free ? now() : null,
                            'payment_confirmed_by'=>! $free ? $owner->id : null,
                            'checked_in_at'=>$checkIn ? now() : null,
                            'checked_in_by'=>$checkIn ? $owner->id : null,
                        ]);
                        $createdUsers->push($user);
                    }
                }

                return [$event, $owner, $ownerCreated, $groups, $createdUsers];
            });
        } catch (ValidationException $exception) {
            $this->error(collect($exception->errors())->flatten()->join(' '));

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Demo 賽事建立完成。');
        $this->table(['項目', '內容'], [
            ['批次代碼', $batch],
            ['賽事', $event->name],
            ['方案', $free ? '免費版' : '單場付費版'],
            ['模式', $mode === 'indoor' ? '室內' : '室外'],
            ['組別', $groups->count().' 組'],
            ['報名', $createdUsers->count().' 人'],
            ['報到', $checkIn ? '全部已報到' : '未啟用，可直接確認名單排靶'],
            ['主辦帳號', $owner->email],
            ['賽事管理', route('organizer.events.show', $event)],
            ['公開頁面', route('events.show', $event)],
        ]);
        if ($ownerCreated) {
            $this->warn('新建主辦帳號密碼：'.$password);
        }
        $this->line('測試選手密碼皆為：'.$password);
        $this->line('選手帳號範例：'.($createdUsers->first()?->email ?? '—'));

        return self::SUCCESS;
    }

    /** @return array<int, array<string, mixed>> */
    private function presets(string $mode): array
    {
        if ($mode === 'indoor') {
            return [
                ['name'=>'反曲弓 18 公尺公開組', 'bow_type'=>'recurve', 'gender'=>'open', 'distance'=>'18m'],
                ['name'=>'反曲弓 18 公尺男子組', 'bow_type'=>'recurve', 'gender'=>'male', 'distance'=>'18m'],
                ['name'=>'反曲弓 18 公尺女子組', 'bow_type'=>'recurve', 'gender'=>'female', 'distance'=>'18m'],
                ['name'=>'複合弓 18 公尺公開組', 'bow_type'=>'compound', 'gender'=>'open', 'distance'=>'18m'],
                ['name'=>'複合弓 18 公尺男子組', 'bow_type'=>'compound', 'gender'=>'male', 'distance'=>'18m'],
                ['name'=>'複合弓 18 公尺女子組', 'bow_type'=>'compound', 'gender'=>'female', 'distance'=>'18m'],
            ];
        }

        return [
            ['name'=>'反曲弓 70 公尺公開組', 'bow_type'=>'recurve', 'gender'=>'open', 'distance'=>'70m'],
            ['name'=>'反曲弓 70 公尺男子組', 'bow_type'=>'recurve', 'gender'=>'male', 'distance'=>'70m'],
            ['name'=>'反曲弓 70 公尺女子組', 'bow_type'=>'recurve', 'gender'=>'female', 'distance'=>'70m'],
            ['name'=>'複合弓 50 公尺公開組', 'bow_type'=>'compound', 'gender'=>'open', 'distance'=>'50m'],
            ['name'=>'複合弓 50 公尺男子組', 'bow_type'=>'compound', 'gender'=>'male', 'distance'=>'50m'],
            ['name'=>'複合弓 50 公尺女子組', 'bow_type'=>'compound', 'gender'=>'female', 'distance'=>'50m'],
        ];
    }
}
