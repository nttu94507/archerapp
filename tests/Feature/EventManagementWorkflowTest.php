<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventGroup;
use App\Models\EventRegistration;
use App\Models\EventScoreEntry;
use App\Models\EventStaff;
use App\Models\User;
use App\Models\OrganizerProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventManagementWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_organizer_draft_requires_platform_approval_before_publication(): void
    {
        $owner = User::factory()->create();
        OrganizerProfile::create(['user_id'=>$owner->id,'organization_name'=>'測試主辦方','organization_type'=>'club','contact_name'=>$owner->name,'contact_email'=>$owner->email,'contact_phone'=>'0912345678','application_reason'=>'測試','status'=>'approved','approved_at'=>now()]);
        $admin = User::factory()->create(['is_admin'=>true]);
        $response = $this->actingAs($owner)->post(route('organizer.events.store'), $this->eventPayload());
        $event = Event::firstOrFail();
        $response->assertRedirect(route('organizer.events.show',$event));
        $this->app['auth']->logout();
        $this->get(route('events.show',$event))->assertNotFound();

        EventGroup::factory()->create(['event_id'=>$event->id]);
        $this->actingAs($owner)->post(route('organizer.events.submit',$event))->assertSessionHas('success');
        $this->assertSame('pending',$event->fresh()->status);

        $this->actingAs($admin)->post(route('admin.events.review',$event),['decision'=>'approve'])->assertSessionHas('success');
        $this->assertTrue($event->fresh()->isPublished());
        $this->get(route('events.show',$event))->assertOk();
    }

    public function test_event_roles_control_management_access(): void
    {
        $owner = User::factory()->create();
        $staffUser = User::factory()->create();
        $viewer = User::factory()->create();
        $outsider = User::factory()->create();
        $event = Event::factory()->create();
        foreach ([[$owner,'owner'],[$staffUser,'staff'],[$viewer,'viewer']] as [$user,$role]) {
            EventStaff::create(['event_id'=>$event->id,'user_id'=>$user->id,'role'=>$role,'status'=>'active','invited_by'=>$owner->id]);
        }

        $this->actingAs($owner)->get(route('organizer.events.edit',$event))->assertOk();
        $this->actingAs($staffUser)->get(route('organizer.events.registrations.index',$event))->assertOk();
        $this->actingAs($viewer)->get(route('organizer.events.registrations.index',$event))->assertForbidden();
        $this->actingAs($outsider)->get(route('organizer.events.show',$event))->assertForbidden();
    }

    public function test_staff_can_check_in_member_by_uuid_and_audit_is_recorded(): void
    {
        [$owner,$event,$group] = $this->ownedEvent();
        $member = User::factory()->create();
        $registration = $this->registration($event,$group,$member);

        $this->actingAs($owner)->post(route('organizer.events.registrations.check-in',$event),['uuid'=>$member->uuid])->assertSessionHas('success');
        $this->assertSame('checked_in',$registration->fresh()->status);
        $this->assertNotNull($registration->fresh()->checked_in_at);
        $this->assertDatabaseHas('event_audit_logs',['event_id'=>$event->id,'action'=>'registration.checked_in']);
    }

    public function test_scores_are_bound_to_registration_and_require_verification_before_publication(): void
    {
        [$owner,$event,$group] = $this->ownedEvent();
        $member = User::factory()->create();
        $registration = $this->registration($event,$group,$member);
        $registration->update(['score_submitted_at'=>now()]);
        EventScoreEntry::create(['event_id'=>$event->id,'event_registration_id'=>$registration->id,'user_id'=>$member->id,'end_number'=>1,'scores'=>[10,10,9,9,8,8],'end_total'=>54]);

        $this->actingAs($owner)->post(route('organizer.events.results.verify',$event),['registration_ids'=>[$registration->id]])->assertSessionHas('success');
        $this->assertNotNull($registration->fresh()->score_verified_at);
        $this->actingAs($owner)->post(route('organizer.events.results.publish',$event))->assertSessionHas('success');
        $this->assertNotNull($registration->fresh()->result_published_at);
        $this->assertNotNull($event->fresh()->completed_at);
    }

    private function eventPayload(): array
    {
        return ['name'=>'測試公開賽','start_date'=>now()->addMonth()->toDateString(),'end_date'=>now()->addMonth()->addDay()->toDateString(),'mode'=>'outdoor','organizer'=>'測試主辦方','reg_start'=>now()->toDateTimeString(),'reg_end'=>now()->addWeeks(2)->toDateTimeString()];
    }

    private function ownedEvent(): array
    {
        $owner=User::factory()->create(); $event=Event::factory()->create(); $group=EventGroup::factory()->create(['event_id'=>$event->id]);
        EventStaff::create(['event_id'=>$event->id,'user_id'=>$owner->id,'role'=>'owner','status'=>'active','invited_by'=>$owner->id]);
        return [$owner,$event,$group];
    }

    private function registration(Event $event, EventGroup $group, User $member): EventRegistration
    {
        return EventRegistration::create(['event_id'=>$event->id,'event_group_id'=>$group->id,'user_id'=>$member->id,'name'=>$member->name,'email'=>$member->email,'status'=>'registered']);
    }
}
