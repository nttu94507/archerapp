<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\EventGroup;
use App\Models\EventRegistration;
use App\Models\EventTeam;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegisterDemoEventAthletes extends Command
{
    protected $signature = 'demo:register-event
        {event : 賽事 UUID 或資料庫 ID}
        {--athletes=8 : 每個組別新增的測試選手數，1～128}
        {--group=* : 只處理指定組別 ID，可重複使用；未指定時處理全部組別}
        {--with-teams : 同時為已開放的團體與混雙建立完整隊伍}
        {--teams=4 : 每一種已開放團體形式至少建立的隊數，1～32}';

    protected $description = '替既有賽事批次建立測試選手、報名、繳費及完整團體隊伍';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('此指令只允許在 local 或 testing 環境執行。');

            return self::FAILURE;
        }

        $athleteCount = (int) $this->option('athletes');
        $teamTarget = (int) $this->option('teams');
        if ($athleteCount < 1 || $athleteCount > 128) {
            $this->error('--athletes 必須介於 1～128，且代表每個組別新增的人數。');

            return self::INVALID;
        }
        if ($teamTarget < 1 || $teamTarget > 32) {
            $this->error('--teams 必須介於 1～32。');

            return self::INVALID;
        }

        $reference = (string) $this->argument('event');
        $event = Event::query()
            ->where('uuid', $reference)
            ->when(ctype_digit($reference), fn ($query) => $query->orWhereKey((int) $reference))
            ->first();
        if (! $event) {
            $this->error('找不到賽事：'.$reference);

            return self::FAILURE;
        }
        if ($event->isOfficiallyCompleted() || $event->scoringSessions()->exists()) {
            $this->error('賽事已完成或已排靶，不能再加入測試報名。');

            return self::FAILURE;
        }

        $requestedGroupIds = collect($this->option('group'))->filter()->map(fn ($id) => (int) $id)->unique();
        $groups = $event->groups()
            ->when($requestedGroupIds->isNotEmpty(), fn ($query) => $query->whereIn('id', $requestedGroupIds))
            ->orderBy('id')->get();
        if ($groups->isEmpty() || ($requestedGroupIds->isNotEmpty() && $groups->count() !== $requestedGroupIds->count())) {
            $this->error('找不到指定組別，或組別不屬於此賽事。');

            return self::FAILURE;
        }

        $ownerId = $event->staff()->where('role', 'owner')->where('status', 'active')->value('user_id');
        if (! $ownerId) {
            $this->error('此賽事沒有有效的主辦方帳號。');

            return self::FAILURE;
        }

        $batch = now()->format('YmdHis').'-'.Str::lower(Str::random(4));
        $withTeams = (bool) $this->option('with-teams');
        if ($withTeams) {
            $requiredAthletes = $groups->map(function (EventGroup $group) use ($teamTarget): int {
                $standard = $group->hasTeamFormat('standard') ? 4 * $teamTarget : 0;
                $mixed = $group->hasTeamFormat('mixed') && $group->gender === 'open' ? 2 * $teamTarget : 0;

                return $standard + $mixed;
            })->max() ?? 0;
            $athleteCount = max($athleteCount, $requiredAthletes);
            if ($athleteCount > 128) {
                $this->error('指定隊數需要每組超過 128 位選手，請降低 --teams。');

                return self::INVALID;
            }
        }
        $createdRegistrations = 0;
        $createdTeams = 0;

        DB::transaction(function () use (
            $event, $groups, $athleteCount, $ownerId, $batch, $withTeams, $teamTarget,
            &$createdRegistrations, &$createdTeams
        ): void {
            foreach ($groups as $groupIndex => $group) {
                $registrations = collect();
                for ($index = 1; $index <= $athleteCount; $index++) {
                    $gender = match ($group->gender) {
                        'male' => 'male',
                        'female' => 'female',
                        default => $index % 2 === 0 ? 'female' : 'male',
                    };
                    $email = sprintf('demo.reg.%s.g%02d.a%03d@example.test', $batch, $groupIndex + 1, $index);
                    $user = User::create([
                        'name'=>sprintf('Demo 報名選手 G%02d-%03d', $groupIndex + 1, $index),
                        'email'=>$email, 'password'=>Str::random(40), 'email_verified_at'=>now(),
                    ]);
                    $user->forceFill(['profile_completed_at'=>now()])->save();
                    UserProfile::create([
                        'user_id'=>$user->id, 'gender'=>$gender, 'bow_type'=>$group->bow_type,
                        'handedness'=>'right', 'club_name'=>'Demo 指令隊',
                    ]);
                    $fee = (int) $group->fee;
                    $registration = EventRegistration::create([
                        'event_id'=>$event->id, 'event_group_id'=>$group->id, 'user_id'=>$user->id,
                        'name'=>$user->name, 'email'=>$user->email, 'team_name'=>'Demo 指令隊',
                        'athlete_gender'=>$gender,
                        'status'=>$event->requiresCheckIn() ? 'checked_in' : 'registered',
                        'paid'=>$fee > 0, 'payment_status'=>$fee > 0 ? 'paid' : 'exempt',
                        'payment_amount'=>$fee,
                        'payment_confirmed_at'=>$fee > 0 ? now() : null,
                        'payment_confirmed_by'=>$fee > 0 ? $ownerId : null,
                        'checked_in_at'=>$event->requiresCheckIn() ? now() : null,
                        'checked_in_by'=>$event->requiresCheckIn() ? $ownerId : null,
                    ]);
                    $registrations->push($registration);
                    $createdRegistrations++;
                }

                if ($withTeams && $group->is_team) {
                    $createdTeams += $this->createTeams($event, $group, $registrations, $ownerId, $teamTarget);
                }
            }
        });

        $this->info('Demo 報名建立完成。');
        $this->table(['項目', '內容'], [
            ['批次代碼', $batch],
            ['賽事', $event->name],
            ['處理組別', $groups->count().' 組'],
            ['新增報名', $createdRegistrations.' 人'],
            ['新增完整隊伍', $withTeams ? $createdTeams.' 隊' : '未要求建立'],
            ['賽事管理', route('organizer.events.show', $event)],
        ]);

        return self::SUCCESS;
    }

    private function createTeams(Event $event, EventGroup $group, Collection $registrations, int $ownerId, int $teamTarget): int
    {
        $available = $registrations->values();
        $created = 0;

        if ($group->hasTeamFormat('standard')) {
            $standardMembers = $available->take(4 * $teamTarget);
            foreach ($standardMembers->chunk(4)->filter(fn ($chunk) => $chunk->count() === 4) as $members) {
                $this->createTeam($event, $group, $members->values(), 'standard', $ownerId, ++$created);
            }
            $available = $available->slice($standardMembers->count())->values();
        }

        if ($group->hasTeamFormat('mixed') && $group->gender === 'open') {
            $pairs = $available->where('athlete_gender', 'male')->values()
                ->zip($available->where('athlete_gender', 'female')->values())
                ->filter(fn ($pair) => $pair->filter()->count() === 2)
                ->take($teamTarget);
            foreach ($pairs as $members) {
                $this->createTeam($event, $group, collect($members)->values(), 'mixed', $ownerId, ++$created);
            }
        }

        return $created;
    }

    private function createTeam(Event $event, EventGroup $group, Collection $members, string $format, int $ownerId, int $sequence): void
    {
        $captain = $members->first();
        $team = EventTeam::create([
            'event_id'=>$event->id, 'event_group_id'=>$group->id,
            'captain_registration_id'=>$captain->id, 'team_format'=>$format,
            'name'=>($format === 'mixed' ? 'Demo 混雙 ' : 'Demo 團體 ').str_pad((string) $sequence, 2, '0', STR_PAD_LEFT),
            'is_open'=>false, 'status'=>'full',
        ]);
        foreach ($members as $index => $registration) {
            $team->memberships()->create([
                'event_group_id'=>$group->id, 'event_registration_id'=>$registration->id,
                'role'=>$index === 0 ? 'captain' : 'member', 'status'=>'active',
                'requested_by'=>$ownerId, 'responded_at'=>now(),
            ]);
        }
    }
}
