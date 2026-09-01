<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventAuditLog;
use App\Models\EventEliminationBracket;
use App\Models\EventGroup;
use App\Models\EventPhase;
use App\Models\EventRankingSnapshot;
use App\Models\EventTeam;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeamEliminationBracketService
{
    public const SIZES=[4,8,16,32];

    public function create(Event $event, EventGroup $group, int $size, bool $bronze=true, ?int $actorId=null, string $format='standard'): EventEliminationBracket
    {
        if (!in_array($size,self::SIZES,true)) throw ValidationException::withMessages(['bracket_size'=>'團體對抗表支援4、8、16或32隊。']);
        if ($group->event_id!==$event->id || !$group->is_team) throw ValidationException::withMessages(['event_group_id'=>'所選組別未開放團體賽。']);
        if (!$event->hasPlanFeature('team_competition')) throw ValidationException::withMessages(['plan'=>'目前方案不支援團體對抗賽。']);
        if (!$group->hasTeamFormat($format)) throw ValidationException::withMessages(['category'=>'此組別未開放所選團體形式。']);

        return DB::transaction(function() use($event,$group,$size,$bronze,$actorId,$format) {
            $snapshot=EventRankingSnapshot::where('event_id',$event->id)->where('event_group_id',$group->id)->where('status','locked')->whereNull('superseded_at')->lockForUpdate()->first();
            if(!$snapshot) throw ValidationException::withMessages(['event_group_id'=>'請先正式發布個人排名，才能建立團體種子。']);
            $category=$format==='mixed'?'mixed_team':'team';
            if(EventEliminationBracket::where('event_group_id',$group->id)->where('category',$category)->exists()) throw ValidationException::withMessages(['event_group_id'=>'此組別已建立所選類型的團體對抗表。']);

            $teams=$group->eventTeams()->where('team_format',$format)->whereIn('status',['full','locked'])->with(['memberships'=>fn($q)=>$q->where('status','active'),'memberships.registration.scoreEntries'])->get()->map(function(EventTeam $team){
                $members=$team->memberships;
                if($members->count()!==$team->requiredSize() || $members->contains(fn($m)=>!$m->registration?->result_published_at)) return null;
                $counted=$members->sortByDesc(fn($m)=>$m->registration->scoreEntries->sum('end_total'))->take($team->scoringSize());
                $entries=$counted->flatMap(fn($m)=>$m->registration->scoreEntries); $arrows=$entries->flatMap(fn($e)=>$e->scores??[]);
                return ['team'=>$team,'total'=>$entries->sum('end_total'),'ten'=>$arrows->where('10')->count(),'x'=>$arrows->filter(fn($v)=>strtoupper((string)$v)==='X')->count()];
            })->filter()->sort(fn($a,$b)=>[$b['total'],$b['ten'],$b['x']]<=>[$a['total'],$a['ten'],$a['x']])->take($size)->values();
            if($teams->count()<2) throw ValidationException::withMessages(['event_group_id'=>'至少需要2支完整且已發布成績的隊伍。']);

            $mode=$group->bow_type==='compound'?'cumulative':'set'; $now=now();
            $label=$format==='mixed'?'混雙':'團體';
            $phase=EventPhase::create(['event_id'=>$event->id,'event_group_id'=>$group->id,'name'=>$group->name.' '.$label.'對抗賽','type'=>'elimination','sequence'=>((int)EventPhase::where('event_group_id',$group->id)->max('sequence'))+1,'scoring_mode'=>$mode,'status'=>'ready','settings'=>['category'=>$category,'bracket_size'=>$size],'locked_at'=>$now,'created_by'=>$actorId]);
            $bracket=EventEliminationBracket::create(['event_id'=>$event->id,'event_group_id'=>$group->id,'event_phase_id'=>$phase->id,'event_ranking_snapshot_id'=>$snapshot->id,'name'=>$group->name.' '.$label.'對抗表','category'=>$category,'scoring_mode'=>$mode,'bracket_size'=>$size,'status'=>'ready','bronze_match_enabled'=>$bronze,'locked_at'=>$now,'created_by'=>$actorId]);
            $this->build($bracket,$teams->mapWithKeys(fn($row,$i)=>[$i+1=>$row['team']]),$bronze);
            EventAuditLog::create(['event_id'=>$event->id,'user_id'=>$actorId,'action'=>'team_elimination.bracket_created','subject_type'=>EventEliminationBracket::class,'subject_id'=>$bracket->id,'metadata'=>['group_id'=>$group->id,'teams'=>$teams->count(),'bracket_size'=>$size,'scoring_mode'=>$mode]]);
            return $bracket;
        });
    }

    private function build(EventEliminationBracket $bracket,$bySeed,bool $bronze): void
    {
        $roundCount=(int)log($bracket->bracket_size,2); $rounds=[];
        for($r=1;$r<=$roundCount;$r++){ $count=(int)($bracket->bracket_size/(2**$r)); for($p=1;$p<=$count;$p++) $rounds[$r][$p]=$bracket->matches()->create(['round_number'=>$r,'position'=>$p,'match_type'=>'main','label'=>$this->label($count),'status'=>'pending']); }
        $bronzeMatch=$bronze&&$roundCount>=2?$bracket->matches()->create(['round_number'=>$roundCount,'position'=>1,'match_type'=>'bronze','label'=>'季軍賽','status'=>'pending']):null;
        for($r=1;$r<$roundCount;$r++) foreach($rounds[$r] as $p=>$match) $match->update(['next_match_id'=>$rounds[$r+1][(int)ceil($p/2)]->id,'next_slot'=>$p%2?1:2,'loser_next_match_id'=>$bronzeMatch&&$r===$roundCount-1?$bronzeMatch->id:null,'loser_next_slot'=>$bronzeMatch&&$r===$roundCount-1?$p:null]);
        $order=$this->seedOrder($bracket->bracket_size);
        foreach($rounds[1] as $p=>$match){$one=$bySeed->get($order[($p-1)*2]);$two=$bySeed->get($order[(($p-1)*2)+1]);$match->update(['participant_one_team_id'=>$one?->id,'participant_two_team_id'=>$two?->id,'participant_one_seed'=>$one?array_search($one->id,$bySeed->map->id->all(),true)+1:null,'participant_two_seed'=>$two?array_search($two->id,$bySeed->map->id->all(),true)+1:null,'status'=>$one&&$two?'ready':(($one||$two)?'walkover':'pending'),'winner_team_id'=>$one&&!$two?$one->id:(!$one&&$two?$two->id:null),'completed_at'=>($one xor $two)?now():null]); if($one xor $two){$next=$match->nextMatch;if($next){$word=$match->next_slot===1?'one':'two';$winner=$one?:$two;$next->update(["participant_{$word}_team_id"=>$winner->id,"participant_{$word}_seed"=>$match->participant_one_team_id?$match->participant_one_seed:$match->participant_two_seed]);}}}
        foreach($rounds as $r=>$matches) if($r>1) foreach($matches as $match) if($match->participant_one_team_id&&$match->participant_two_team_id)$match->update(['status'=>'ready']);
    }
    private function seedOrder(int $size): array {$seeds=[1,2];while(count($seeds)<$size){$sum=count($seeds)*2+1;$next=[];foreach($seeds as $i=>$seed)$i%2===0?array_push($next,$seed,$sum-$seed):array_push($next,$sum-$seed,$seed);$seeds=$next;}return $seeds;}
    private function label(int $count): string{return match($count){1=>'決賽',2=>'準決賽',4=>'八強賽',8=>'十六強賽',16=>'三十二強賽',default=>($count*2).'強賽'};}
}
