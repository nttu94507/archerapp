<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventStaff;
use App\Models\OrganizerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizerQualificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registered_member_can_create_free_event_without_organizer_approval(): void
    {
        $user=User::factory()->create();
        $this->actingAs($user)->get(route('organizer.events.create'))->assertOk();
        $this->actingAs($user)->post(route('organizer.events.store'), [
            'name'=>'會員自由賽事',
            'start_date'=>now()->addMonth()->toDateString(),
            'end_date'=>now()->addMonth()->toDateString(),
            'mode'=>'outdoor',
            'organizer'=>'一般會員主辦',
            'reg_start'=>now()->toDateTimeString(),
            'reg_end'=>now()->addWeeks(2)->toDateTimeString(),
        ])->assertRedirect();

        $event=Event::where('name','會員自由賽事')->firstOrFail();
        $this->assertTrue($event->isFreePlan());
        $this->assertDatabaseHas('event_staff',['event_id'=>$event->id,'user_id'=>$user->id,'role'=>'owner']);
    }

    public function test_member_can_save_submit_and_withdraw_application(): void
    {
        $user=User::factory()->create();
        $this->actingAs($user)->put(route('organizer.qualification.update'),$this->payload())->assertSessionHas('success');
        $profile=$user->organizerProfile()->firstOrFail();
        $this->assertSame('draft',$profile->status);

        $this->actingAs($user)->post(route('organizer.qualification.submit'))->assertSessionHas('success');
        $this->assertSame('pending',$profile->fresh()->status);
        $this->assertDatabaseHas('organizer_applications',['organizer_profile_id'=>$profile->id,'version'=>1,'status'=>'pending']);

        $this->actingAs($user)->post(route('organizer.qualification.withdraw'))->assertSessionHas('success');
        $this->assertSame('draft',$profile->fresh()->status);
        $this->assertDatabaseHas('organizer_applications',['organizer_profile_id'=>$profile->id,'status'=>'withdrawn']);
    }

    public function test_platform_can_request_changes_then_approve_resubmission(): void
    {
        $user=User::factory()->create(); $admin=User::factory()->create(['is_admin'=>true]);
        $this->actingAs($user)->put(route('organizer.qualification.update'),$this->payload());
        $this->actingAs($user)->post(route('organizer.qualification.submit'));
        $profile=$user->organizerProfile()->firstOrFail();

        $this->actingAs($admin)->post(route('admin.organizers.review',$profile),['decision'=>'changes_requested','public_note'=>'請補充主辦經歷'])->assertSessionHas('success');
        $this->assertSame('changes_requested',$profile->fresh()->status);
        $this->actingAs($user)->put(route('organizer.qualification.update'),array_merge($this->payload(), ['experience'=>'曾舉辦校內賽']));
        $this->actingAs($user)->post(route('organizer.qualification.submit'));
        $this->assertDatabaseHas('organizer_applications',['organizer_profile_id'=>$profile->id,'version'=>2,'status'=>'pending']);

        $this->actingAs($admin)->post(route('admin.organizers.review',$profile),['decision'=>'approve'])->assertSessionHas('success');
        $this->assertSame('approved',$profile->fresh()->status);
        $this->actingAs($user)->get(route('organizer.events.create'))->assertOk();
    }

    public function test_suspended_organizer_keeps_existing_roles_but_cannot_create_new_event(): void
    {
        $user=User::factory()->create(); $admin=User::factory()->create(['is_admin'=>true]);
        $profile=OrganizerProfile::create($this->payload()+['user_id'=>$user->id,'status'=>'approved','approved_at'=>now()]);
        $event=Event::factory()->create();
        EventStaff::create(['event_id'=>$event->id,'user_id'=>$user->id,'role'=>'owner','status'=>'active','invited_by'=>$user->id]);
        $this->actingAs($admin)->post(route('admin.organizers.suspend',$profile),['public_note'=>'資格審查中'])->assertSessionHas('success');
        $this->actingAs($user)->get(route('organizer.events.create'))->assertForbidden();
        $this->actingAs($user)->get(route('organizer.events.show',$event))->assertOk();
        $this->actingAs($user)->get(route('organizer.events.edit',$event))->assertForbidden();
        $this->actingAs($admin)->post(route('admin.organizers.restore',$profile))->assertSessionHas('success');
        $this->actingAs($user)->get(route('organizer.events.create'))->assertOk();
    }

    private function payload(): array
    {
        return ['organization_name'=>'台北弓社','organization_type'=>'club','contact_name'=>'王主辦','contact_email'=>'organizer@example.com','contact_phone'=>'0912345678','website'=>'https://example.com','experience'=>'曾協助地方賽','planned_events'=>'預計舉辦公開賽','application_reason'=>'希望使用平台管理賽事'];
    }
}
