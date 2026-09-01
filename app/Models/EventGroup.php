<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Support\Str;

class EventGroup extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::saved(function (EventGroup $group): void {
            $phase = $group->phases()
                ->where('type', 'qualification')
                ->where('sequence', 1)
                ->first();

            if (! $phase) {
                $group->phases()->create($group->qualificationPhaseAttributes());
                return;
            }

            if (! $phase->isLocked()) {
                $phase->update($group->qualificationPhaseAttributes());
            }
        });
    }

    protected $fillable = [
        'event_id','name','bow_type','gender','age_class','distance','arrow_count',
        'arrows_per_end','quota','fee','is_team','standard_team_enabled','mixed_team_enabled','team_size','team_type','team_substitute_limit','team_formation_end','reg_start','reg_end','live_results_visible',
    ];

    protected $casts = [
        'is_team'   => 'boolean', 'standard_team_enabled'=>'boolean', 'mixed_team_enabled'=>'boolean',
        'team_formation_end' => 'datetime',
        'reg_start' => 'datetime', 'reg_end' => 'datetime',
        'live_results_visible' => 'boolean',
    ];

    public static function duplicateKey(?string $bowType, ?string $distance, ?string $gender, ?string $ageClass = null): string
    {
        $normalizedDistance = mb_strtolower(trim((string) $distance));
        $legacyAgeClass = mb_strtolower(trim((string) $ageClass));
        if ($normalizedDistance === '' && preg_match('/^\d+\s*(?:m|公尺)$/iu', $legacyAgeClass)) {
            $normalizedDistance = $legacyAgeClass;
        }
        $normalizedDistance = str_replace('公尺', 'm', $normalizedDistance);
        $normalizedDistance = preg_replace('/\s+/u', '', $normalizedDistance) ?? $normalizedDistance;

        return implode('|', [$bowType ?? '', $normalizedDistance, $gender ?? 'open']);
    }

    public static function duplicateName(?string $name): string
    {
        return mb_strtolower(preg_replace('/\s+/u', '', trim((string) $name)) ?? '');
    }

    public function event() {
        return $this->belongsTo(Event::class);
    }

    public function registrations()
    {
        // 如果外鍵是 event_group_id
        return $this->hasMany(EventRegistration::class, 'event_group_id', 'id');

        // 若你的外鍵其實叫 group_id，請改成：
        // return $this->hasMany(Registration::class, 'group_id', 'id');
    }

    public function scoringSessions()
    {
        return $this->hasMany(EventScoringSession::class, 'event_group_id');
    }

    public function phases()
    {
        return $this->hasMany(EventPhase::class, 'event_group_id')->orderBy('sequence');
    }

    public function qualificationPhase()
    {
        return $this->hasOne(EventPhase::class, 'event_group_id')
            ->where('type', 'qualification')
            ->where('sequence', 1);
    }

    public function rankingSnapshots()
    {
        return $this->hasMany(EventRankingSnapshot::class, 'event_group_id')->latest('version');
    }

    public function eventTeams() { return $this->hasMany(EventTeam::class, 'event_group_id'); }

    public function hasTeamFormat(string $format): bool
    {
        if ($this->standard_team_enabled || $this->mixed_team_enabled) {
            return $format === 'mixed' ? (bool) $this->mixed_team_enabled : (bool) $this->standard_team_enabled;
        }
        return (bool) $this->is_team && ($this->team_type ?: 'standard') === $format;
    }

    public function teamSizeFor(string $format): int { return $format === 'mixed' ? 2 : 3; }

    public function teamFormationIsOpen(): bool
    {
        if (! $this->is_team || $this->event?->scoringSessions()->exists()) return false;
        $deadline = $this->team_formation_end ?? $this->effectiveRegEnd();
        return $deadline !== null && now()->lte($deadline);
    }

    public function eliminationBrackets()
    {
        return $this->hasMany(EventEliminationBracket::class, 'event_group_id')->latest();
    }

    public function qualificationPhaseAttributes(): array
    {
        return [
            'event_id'=>$this->event_id,
            'name'=>Str::limit($this->name.' 排名賽', 120, ''),
            'type'=>'qualification',
            'sequence'=>1,
            'scoring_mode'=>'cumulative',
            'total_arrows'=>$this->arrow_count,
            'arrows_per_end'=>$this->arrows_per_end ?: 6,
        ];
    }

    public function usesCustomRegistrationWindow(): bool
    {
        return $this->reg_start !== null && $this->reg_end !== null;
    }

    public function effectiveRegStart(): ?Carbon
    {
        return $this->reg_start ?? $this->event?->reg_start;
    }

    public function effectiveRegEnd(): ?Carbon
    {
        return $this->reg_end ?? $this->event?->reg_end;
    }

    public function isRegistrationOpen(?Carbon $at = null): bool
    {
        $event = $this->event;
        if ($event && ($event->relationLoaded('scoringSessions')
            ? $event->scoringSessions->isNotEmpty()
            : $event->scoringSessions()->exists())) {
            return false;
        }

        $start = $this->effectiveRegStart();
        $end = $this->effectiveRegEnd();

        return $start !== null && $end !== null && ($at ?? now())->between($start, $end);
    }

}
