<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventBadge;
use App\Models\EventBadgeClaim;
use App\Models\EventGroup;
use App\Models\EventRegistration;
use App\Models\EventScoreEntry;
use App\Models\EventStaff;
use App\Models\OrganizerProfile;
use App\Models\User;
use App\Models\UserEventBadge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EventBadgeWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_scan_location_qr_and_receive_badge_inside_radius_but_not_from_another_city(): void
    {
        $admin=User::factory()->create(['is_admin'=>true]); $nearby=User::factory()->create(); $farAway=User::factory()->create();
        $this->actingAs($admin)->post(route('admin.badges.store'),[
            'name'=>'台南定位 Badge','location_claim_enabled'=>1,'claim_lat'=>22.999728,'claim_lng'=>120.227028,'claim_radius_km'=>10,
        ])->assertSessionHas('success');
        $badge=EventBadge::where('name','台南定位 Badge')->firstOrFail();

        $this->actingAs($admin)->get(route('admin.badges.index'))->assertOk()->assertSee('定位 QR Code');
        $this->get(route('badge-drops.qrcode',$badge->claim_token))->assertOk()->assertHeader('Content-Type','image/svg+xml');
        $this->actingAs($nearby)->post(route('badge-drops.claim',$badge->claim_token),[
            'lat'=>23.0005,'lng'=>120.2200,'accuracy'=>120,
        ])->assertSessionHas('success','位置驗證成功，Badge 已取得。');
        $this->actingAs($farAway)->post(route('badge-drops.claim',$badge->claim_token),[
            'lat'=>25.0330,'lng'=>121.5654,'accuracy'=>800,
        ])->assertSessionHas('error','目前不在 Badge 發放區域內。');

        $this->assertDatabaseHas('user_event_badges',['event_badge_id'=>$badge->id,'user_id'=>$nearby->id,'award_source'=>'location_qr','limited_serial'=>1]);
        $this->assertDatabaseMissing('user_event_badges',['event_badge_id'=>$badge->id,'user_id'=>$farAway->id]);
    }

    public function test_location_badge_can_be_limited_to_an_optional_claim_date(): void
    {
        $this->travelTo(now()->setDate(2026, 8, 1)->setTime(12, 0));
        $admin=User::factory()->create(['is_admin'=>true]);
        $member=User::factory()->create();

        $this->actingAs($admin)->post(route('admin.badges.store'),[
            'name'=>'限定日期定位 Badge',
            'location_claim_enabled'=>1,
            'claim_lat'=>22.999728,
            'claim_lng'=>120.227028,
            'claim_radius_km'=>10,
            'claim_date'=>'2026-08-02',
        ])->assertSessionHas('success');

        $badge=EventBadge::where('name','限定日期定位 Badge')->firstOrFail();
        $this->assertSame('2026-08-02 00:00:00',$badge->claim_starts_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-02 23:59:59',$badge->claim_ends_at->format('Y-m-d H:i:s'));

        $claim=['lat'=>23.0005,'lng'=>120.2200,'accuracy'=>120];
        $this->actingAs($member)->post(route('badge-drops.claim',$badge->claim_token),$claim)
            ->assertSessionHas('error','Badge 尚未開放領取。');

        $this->travelTo(now()->addDay());
        $this->actingAs($member)->post(route('badge-drops.claim',$badge->claim_token),$claim)
            ->assertSessionHas('success','位置驗證成功，Badge 已取得。');
        $this->travelBack();
    }

    public function test_event_badge_qr_application_can_use_a_period_and_be_closed_immediately(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 1)->setTime(12, 0));
        [$owner,$event]=$this->eventWithOwner();
        $first=User::factory()->create();
        $second=User::factory()->create();
        $this->register($event,$first,'registered');
        $this->register($event,$second,'registered');
        $badge=EventBadge::create([
            'event_id'=>$event->id,
            'created_by'=>$owner->id,
            'name'=>'期間限定申請',
            'type'=>'special',
            'eligibility'=>'registered',
            'award_rule'=>'manual',
        ]);

        $this->actingAs($owner)->patch(route('organizer.events.badges.update',[$event,$badge]),[
            'claim_enabled'=>1,
            'claim_starts_at'=>'2026-09-02 09:00',
            'claim_ends_at'=>'2026-09-02 18:00',
        ])->assertSessionHas('success');

        $this->actingAs($first)->post(route('badge-claims.store',$badge->claim_token))
            ->assertSessionHas('error','此 Badge 目前未開放申請。');

        $this->travelTo(now()->addDay());
        $this->actingAs($first)->post(route('badge-claims.store',$badge->claim_token))
            ->assertSessionHas('success','申請已送出，等待主辦方確認。');

        $this->actingAs($owner)->patch(route('organizer.events.badges.update',[$event,$badge]),[])
            ->assertSessionHas('success');
        $this->assertFalse($badge->fresh()->claim_enabled);
        $this->actingAs($second)->post(route('badge-claims.store',$badge->claim_token))
            ->assertSessionHas('error','此 Badge 目前未開放申請。');
        $this->travelBack();
    }

    public function test_location_badge_accepts_a_custom_period_without_blocking_manual_awards(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 1)->setTime(12, 0));
        $admin=User::factory()->create(['is_admin'=>true]);
        $member=User::factory()->create();

        $this->actingAs($admin)->post(route('admin.badges.store'),[
            'name'=>'跨日定位 Badge',
            'location_claim_enabled'=>1,
            'claim_lat'=>22.999728,
            'claim_lng'=>120.227028,
            'claim_radius_km'=>10,
            'claim_starts_at'=>'2026-10-03 09:00',
            'claim_ends_at'=>'2026-10-04 18:00',
        ])->assertSessionHas('success');

        $badge=EventBadge::where('name','跨日定位 Badge')->firstOrFail();
        $this->assertSame('2026-10-03 09:00',$badge->claim_starts_at->format('Y-m-d H:i'));
        $this->assertSame('2026-10-04 18:00',$badge->claim_ends_at->format('Y-m-d H:i'));

        $this->actingAs($admin)->post(route('admin.badges.award',$badge),['member'=>$member->uuid])
            ->assertSessionHas('success','官方 Badge 已發放。');
        $this->assertDatabaseHas('user_event_badges',['event_badge_id'=>$badge->id,'user_id'=>$member->id]);
        $this->travelBack();
    }

    public function test_organizer_can_disable_and_reopen_their_own_self_claim_badge(): void
    {
        $organizer=User::factory()->create();
        $otherOrganizer=User::factory()->create();
        $admin=User::factory()->create(['is_admin'=>true]);
        $member=User::factory()->create();
        $secondMember=User::factory()->create();
        foreach([$organizer,$otherOrganizer] as $user) {
            OrganizerProfile::create([
                'user_id'=>$user->id,
                'organization_name'=>'測試主辦單位',
                'organization_type'=>'club',
                'contact_name'=>$user->name,
                'contact_email'=>$user->email,
                'contact_phone'=>'0912345678',
                'application_reason'=>'測試 Badge 發放管理',
                'status'=>'approved',
                'approved_at'=>now(),
            ]);
        }
        $badge=EventBadge::create([
            'created_by'=>$organizer->id,
            'issuer_type'=>'organizer',
            'issuer_name'=>'測試主辦單位',
            'external_activity_name'=>'場地活動',
            'name'=>'主辦方自行領取',
            'type'=>'special',
            'eligibility'=>'any',
            'award_rule'=>'manual',
            'location_claim_enabled'=>true,
            'claim_lat'=>22.999728,
            'claim_lng'=>120.227028,
            'claim_radius_km'=>10,
        ]);

        $this->actingAs($organizer)->get(route('organizer.badges.index'))
            ->assertOk()->assertSee('Badge 列表')->assertSee('新增 Badge')->assertSee('顯示 QR Code')->assertSee('停用');
        $this->actingAs($organizer)->get(route('organizer.badges.create'))
            ->assertOk()->assertSee('新增 Badge')->assertSee('可領取日期')->assertDontSee('開始時間');
        $this->actingAs($organizer)->get(route('organizer.badges.edit',$badge))
            ->assertOk()->assertSee('編輯 主辦方自行領取')->assertSee('人工發放');
        $this->actingAs($organizer)->put(route('organizer.badges.update',$badge),[
            'name'=>'主辦方自行領取',
            'external_activity_name'=>'場地活動',
            'external_activity_location'=>'台南',
            'location_claim_enabled'=>1,
            'claim_lat'=>22.999728,
            'claim_lng'=>120.227028,
            'claim_radius_km'=>10,
            'claim_date'=>'2026-11-02',
        ])->assertRedirect(route('organizer.badges.index'))->assertSessionHas('success','Badge 已更新。');
        $this->assertDatabaseHas('event_badges',['id'=>$badge->id,'external_activity_location'=>'台南']);
        $this->assertSame('2026-11-02 00:00:00',$badge->fresh()->claim_starts_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-02 23:59:59',$badge->fresh()->claim_ends_at->format('Y-m-d H:i:s'));

        $this->actingAs($otherOrganizer)->patch(route('organizer.badges.claim-toggle',$badge))
            ->assertForbidden();
        $this->actingAs($organizer)->patch(route('organizer.badges.claim-toggle',$badge))
            ->assertSessionHas('success','自行領取已停用，既有 QR Code 暫時無法領取。');
        $this->assertFalse($badge->fresh()->location_claim_enabled);

        $this->actingAs($member)->post(route('badge-drops.claim',$badge->claim_token),[
            'lat'=>23.0005,'lng'=>120.2200,'accuracy'=>120,
        ])->assertSessionHas('error','此 Badge 目前未開放領取。');

        $this->actingAs($organizer)->post(route('organizer.badges.award',$badge),['member'=>$member->uuid])
            ->assertSessionHas('success','Badge 已發放。');
        $this->actingAs($organizer)->patch(route('organizer.badges.claim-toggle',$badge))
            ->assertSessionHas('success','自行領取已重新開放。');
        $this->assertTrue($badge->fresh()->location_claim_enabled);

        $this->actingAs($admin)->patch(route('admin.badges.toggle',$badge))
            ->assertSessionHas('success','Badge 已由平台停用，所有自行領取暫停。');
        $this->assertFalse($badge->fresh()->is_active);
        $this->assertTrue($badge->fresh()->location_claim_enabled);
        $this->actingAs($organizer)->get(route('organizer.badges.index'))
            ->assertOk()->assertSee('平台停用');
        $this->actingAs($admin)->get(route('admin.badges.index'))
            ->assertOk()->assertSee('平台已停用 Badge');
        $this->actingAs($organizer)->patch(route('organizer.badges.claim-toggle',$badge))
            ->assertSessionHas('error','此 Badge 已由平台停用，無法變更自行領取狀態。');
        $this->actingAs($organizer)->post(route('organizer.badges.award',$badge),['member'=>$secondMember->uuid])
            ->assertSessionHas('error','此 Badge 已由平台停用。');
    }

    public function test_platform_limited_badge_stops_at_maximum_and_has_public_certificate(): void
    {
        $admin=User::factory()->create(['is_admin'=>true]); $first=User::factory()->create(); $second=User::factory()->create();
        $this->actingAs($admin)->post(route('admin.badges.store'),['name'=>'官方限量','max_supply'=>1])->assertSessionHas('success');
        $badge=EventBadge::where('name','官方限量')->firstOrFail();
        $this->actingAs($admin)->post(route('admin.badges.award',$badge),['member'=>$first->uuid])->assertSessionHas('success');
        $this->actingAs($admin)->post(route('admin.badges.award',$badge),['member'=>$second->uuid])->assertSessionHas('error','徽章數量已達到最大值。');
        $award=UserEventBadge::firstOrFail();
        $this->assertNotNull($award->public_id); $this->assertSame(1,$award->limited_serial);
        $this->get(route('badge-certificates.show',$award->public_id))->assertOk()->assertSee('有效認證')->assertSee('官方限量');
    }

    public function test_republished_corrected_scores_reconcile_placement_badge(): void
    {
        [$owner,$event]=$this->eventWithOwner(); $group=EventGroup::factory()->create(['event_id'=>$event->id]); $members=User::factory()->count(2)->create();
        $registrations=[]; foreach([50,40] as $i=>$score){$registrations[$i]=EventRegistration::create(['event_id'=>$event->id,'event_group_id'=>$group->id,'user_id'=>$members[$i]->id,'name'=>$members[$i]->name,'email'=>$members[$i]->email,'status'=>'checked_in','score_submitted_at'=>now(),'score_verified_at'=>now()]); EventScoreEntry::create(['event_id'=>$event->id,'event_registration_id'=>$registrations[$i]->id,'user_id'=>$members[$i]->id,'end_number'=>1,'scores'=>[$score],'end_total'=>$score]);}
        $badge=EventBadge::create(['event_id'=>$event->id,'event_group_id'=>$group->id,'created_by'=>$owner->id,'name'=>'金牌修正','type'=>'special','eligibility'=>'scored','award_rule'=>'placement','placement'=>1]);
        $this->actingAs($owner)->post(route('organizer.events.results.publish',$event));
        EventScoreEntry::where('event_registration_id',$registrations[1]->id)->update(['end_total'=>60,'scores'=>[60]]);
        $this->actingAs($owner)->post(route('organizer.events.results.publish',$event));
        $this->assertDatabaseHas('user_event_badges',['event_badge_id'=>$badge->id,'user_id'=>$members[0]->id,'revoked_reason'=>'正式成績修正，名次重新判定']);
        $this->assertDatabaseHas('user_event_badges',['event_badge_id'=>$badge->id,'user_id'=>$members[1]->id,'revoked_at'=>null,'score_snapshot'=>60]);
    }

    public function test_creating_staff_badge_awards_existing_active_work_team_without_application(): void
    {
        [$owner, $event] = $this->eventWithOwner();
        $staff = User::factory()->create();
        EventStaff::create(['event_id'=>$event->id,'user_id'=>$staff->id,'role'=>'staff','status'=>'active','invited_by'=>$owner->id,'accepted_at'=>now()]);

        $this->actingAs($owner)->post(route('organizer.events.badges.store',$event), [
            'name'=>'賽事工作人員', 'type'=>'staff', 'eligibility'=>'any', 'award_rule'=>'staff',
        ])->assertSessionHas('success');

        $badge = EventBadge::where('name','賽事工作人員')->firstOrFail();
        $this->assertDatabaseHas('user_event_badges',['event_badge_id'=>$badge->id,'user_id'=>$owner->id,'award_source'=>'staff']);
        $this->assertDatabaseHas('user_event_badges',['event_badge_id'=>$badge->id,'user_id'=>$staff->id,'award_source'=>'staff']);
    }

    public function test_staff_badge_can_be_limited_to_selected_team_roles(): void
    {
        [$owner, $event] = $this->eventWithOwner();
        $staff = User::factory()->create();
        EventStaff::create(['event_id'=>$event->id,'user_id'=>$staff->id,'role'=>'staff','status'=>'active','invited_by'=>$owner->id]);

        $this->actingAs($owner)->post(route('organizer.events.badges.store',$event), [
            'name'=>'現場工作人員', 'type'=>'staff', 'award_rule'=>'placement', 'staff_roles'=>['staff'],
        ]);

        $badge = EventBadge::where('name','現場工作人員')->firstOrFail();
        $this->assertSame('staff',$badge->award_rule);
        $this->assertSame(['staff'],$badge->staff_roles);
        $this->assertDatabaseHas('user_event_badges',['event_badge_id'=>$badge->id,'user_id'=>$staff->id]);
        $this->assertDatabaseMissing('user_event_badges',['event_badge_id'=>$badge->id,'user_id'=>$owner->id]);
    }

    public function test_accepting_volunteer_invitation_automatically_awards_volunteer_badge(): void
    {
        [$owner, $event] = $this->eventWithOwner();
        $volunteer = User::factory()->create();
        $badge = EventBadge::create(['event_id'=>$event->id,'created_by'=>$owner->id,'name'=>'賽事志工','type'=>'volunteer','eligibility'=>'any','award_rule'=>'volunteer']);
        $url = \Illuminate\Support\Facades\URL::temporarySignedRoute('organizer.staff-invitations.show', now()->addDay(), ['event'=>$event,'role'=>'volunteer','inviter'=>$owner->id]);

        $this->actingAs($volunteer)->post($url)->assertRedirect(route('organizer.events.show',$event));

        $this->assertDatabaseHas('event_staff',['event_id'=>$event->id,'user_id'=>$volunteer->id,'role'=>'volunteer','status'=>'active']);
        $this->assertDatabaseHas('user_event_badges',['event_badge_id'=>$badge->id,'user_id'=>$volunteer->id,'award_source'=>'volunteer']);
    }

    public function test_paid_and_checked_in_registration_receives_attendance_badge_regardless_of_order(): void
    {
        [$owner, $event] = $this->eventWithOwner();
        $member = User::factory()->create();
        $registration = $this->register($event, $member, 'registered');
        $badge = EventBadge::create(['event_id'=>$event->id,'created_by'=>$owner->id,'name'=>'參賽 Badge','type'=>'participant','eligibility'=>'checked_in','award_rule'=>'attendance']);

        $this->actingAs($owner)->post(route('organizer.events.registrations.check-in',$event), ['uuid'=>$member->uuid]);
        $this->assertDatabaseMissing('user_event_badges', ['event_badge_id'=>$badge->id,'user_id'=>$member->id]);
        $this->actingAs($owner)->patch(route('organizer.events.registrations.payment',$event), ['registration_ids'=>[$registration->id],'payment_status'=>'paid']);

        $this->assertDatabaseHas('user_event_badges', ['event_badge_id'=>$badge->id,'user_id'=>$member->id,'award_source'=>'attendance','revoked_at'=>null]);
        $this->assertDatabaseHas('event_payment_audits', ['event_registration_id'=>$registration->id,'to_status'=>'paid','changed_by'=>$owner->id]);
    }

    public function test_publishing_verified_results_awards_placement_badge_once(): void
    {
        [$owner, $event] = $this->eventWithOwner();
        $group = EventGroup::factory()->create(['event_id'=>$event->id]);
        $members = User::factory()->count(3)->create();
        foreach ([60, 50, 40] as $index => $score) {
            $registration = EventRegistration::create(['event_id'=>$event->id,'event_group_id'=>$group->id,'user_id'=>$members[$index]->id,'name'=>$members[$index]->name,'email'=>$members[$index]->email,'status'=>'checked_in','score_submitted_at'=>now(),'score_verified_at'=>now()]);
            EventScoreEntry::create(['event_id'=>$event->id,'event_registration_id'=>$registration->id,'user_id'=>$members[$index]->id,'end_number'=>1,'scores'=>[$score],'end_total'=>$score]);
        }
        $badge = EventBadge::create(['event_id'=>$event->id,'event_group_id'=>$group->id,'created_by'=>$owner->id,'name'=>'金牌','type'=>'special','eligibility'=>'scored','award_rule'=>'placement','placement'=>1]);

        $this->actingAs($owner)->post(route('organizer.events.results.publish',$event))->assertSessionHas('success');
        $this->actingAs($owner)->post(route('organizer.events.results.publish',$event))->assertSessionHas('success');

        $this->assertDatabaseCount('user_event_badges', 1);
        $this->assertDatabaseHas('user_event_badges', ['event_badge_id'=>$badge->id,'user_id'=>$members[0]->id,'award_source'=>'placement']);
    }

    public function test_organizer_can_bulk_award_special_badge_to_event_participants(): void
    {
        [$owner, $event] = $this->eventWithOwner();
        $members = User::factory()->count(2)->create();
        foreach ($members as $member) $this->register($event, $member, 'registered');
        $badge = EventBadge::create(['event_id'=>$event->id,'created_by'=>$owner->id,'name'=>'最佳新人','type'=>'special','eligibility'=>'registered','award_rule'=>'manual']);

        $this->actingAs($owner)->post(route('organizer.events.badges.award',[$event,$badge]), ['user_ids'=>$members->pluck('id')->all(),'award_note'=>'賽後評選'])->assertSessionHas('success');

        $this->assertSame(2, UserEventBadge::where('event_badge_id',$badge->id)->where('award_source','manual')->count());
    }

    public function test_event_owner_can_create_badge_but_unrelated_user_cannot_manage_it(): void
    {
        Storage::fake('public');
        [$owner, $event] = $this->eventWithOwner();

        $response = $this->actingAs($owner)->post(route('organizer.events.badges.store', $event), [
            'name' => '公開賽參賽者',
            'description' => '完成現場報到',
            'type' => 'participant',
            'eligibility' => 'checked_in',
            'claim_enabled' => '1',
            'icon' => UploadedFile::fake()->createWithContent(
                'badge.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=')
            ),
        ]);

        $badge = EventBadge::firstOrFail();
        $response->assertRedirect(route('organizer.events.badges.show', [$event, $badge]));
        $this->assertTrue($badge->claim_enabled);
        $this->assertNotNull($badge->icon_path);
        Storage::disk('public')->assertExists($badge->icon_path);

        $this->actingAs(User::factory()->create())
            ->get(route('organizer.events.badges.show', [$event, $badge]))
            ->assertForbidden();
    }

    public function test_badge_without_uploaded_icon_uses_default_icon(): void
    {
        [$owner, $event] = $this->eventWithOwner();
        $badge = EventBadge::create([
            'event_id'=>$event->id, 'created_by'=>$owner->id, 'name'=>'預設圖示',
            'type'=>'participant', 'eligibility'=>'any',
        ]);

        $this->assertStringEndsWith('/images/default-badge.svg', $badge->icon_url);
    }

    public function test_registered_member_can_scan_and_submit_only_one_claim(): void
    {
        [$owner, $event] = $this->eventWithOwner();
        $member = User::factory()->create();
        $this->register($event, $member, 'registered');
        $badge = EventBadge::create([
            'event_id' => $event->id,
            'created_by' => $owner->id,
            'name' => '參賽者',
            'type' => 'participant',
            'eligibility' => 'registered',
            'claim_enabled' => true,
        ]);

        $this->actingAs($member)->post(route('badge-claims.store', $badge->claim_token))->assertSessionHas('success');
        $this->actingAs($member)->post(route('badge-claims.store', $badge->claim_token))->assertSessionHas('success');

        $this->assertDatabaseCount('event_badge_claims', 1);
        $this->assertDatabaseHas('event_badge_claims', [
            'event_badge_id' => $badge->id,
            'user_id' => $member->id,
            'status' => 'pending',
            'is_eligible' => true,
        ]);
    }

    public function test_unregistered_member_is_sent_to_manual_review(): void
    {
        [$owner, $event] = $this->eventWithOwner();
        $member = User::factory()->create();
        $badge = EventBadge::create([
            'event_id' => $event->id,
            'created_by' => $owner->id,
            'name' => '參賽者',
            'type' => 'participant',
            'eligibility' => 'registered',
            'claim_enabled' => true,
        ]);

        $this->actingAs($member)->post(route('badge-claims.store', $badge->claim_token));

        $this->assertDatabaseHas('event_badge_claims', [
            'event_badge_id' => $badge->id,
            'user_id' => $member->id,
            'status' => 'needs_review',
            'is_eligible' => false,
        ]);
    }

    public function test_any_active_group_registration_qualifies_even_if_an_older_group_was_withdrawn(): void
    {
        [$owner, $event] = $this->eventWithOwner();
        $member = User::factory()->create();
        $this->register($event, $member, 'withdrawn');
        $this->register($event, $member, 'registered');
        $badge = EventBadge::create([
            'event_id'=>$event->id, 'created_by'=>$owner->id, 'name'=>'參賽 Badge',
            'type'=>'participant', 'eligibility'=>'registered', 'claim_enabled'=>true,
        ]);

        $this->actingAs($member)->post(route('badge-claims.store',$badge->claim_token));

        $this->assertDatabaseHas('event_badge_claims', [
            'event_badge_id'=>$badge->id, 'user_id'=>$member->id,
            'status'=>'pending', 'is_eligible'=>true, 'eligibility_note'=>'已有有效報名',
        ]);
    }

    public function test_pending_claim_refreshes_after_member_registers(): void
    {
        [$owner, $event] = $this->eventWithOwner();
        $member = User::factory()->create();
        $badge = EventBadge::create([
            'event_id'=>$event->id, 'created_by'=>$owner->id, 'name'=>'參賽 Badge',
            'type'=>'participant', 'eligibility'=>'registered', 'claim_enabled'=>true,
        ]);
        $this->actingAs($member)->post(route('badge-claims.store',$badge->claim_token));
        $this->register($event, $member, 'registered');

        $this->actingAs($member)->get(route('badge-claims.show',$badge->claim_token))->assertOk();

        $this->assertDatabaseHas('event_badge_claims', [
            'event_badge_id'=>$badge->id, 'user_id'=>$member->id,
            'status'=>'pending', 'is_eligible'=>true,
        ]);
    }

    public function test_owner_can_bulk_approve_claim_and_badge_appears_on_member_profile(): void
    {
        [$owner, $event] = $this->eventWithOwner();
        $member = User::factory()->create();
        $badge = EventBadge::create([
            'event_id' => $event->id,
            'created_by' => $owner->id,
            'name' => '完賽 Badge',
            'type' => 'finisher',
            'eligibility' => 'registered',
            'claim_enabled' => true,
        ]);
        $claim = EventBadgeClaim::create([
            'event_badge_id' => $badge->id,
            'user_id' => $member->id,
            'status' => 'pending',
            'is_eligible' => true,
            'eligibility_note' => '已有有效報名',
        ]);

        $this->actingAs($owner)->post(route('organizer.events.badges.review', [$event, $badge]), [
            'action' => 'approve',
            'claim_ids' => [$claim->id],
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('user_event_badges', [
            'event_badge_id' => $badge->id,
            'user_id' => $member->id,
            'revoked_at' => null,
        ]);
        $this->actingAs($member)->get(route('member-profile.index'))->assertOk()->assertSee('完賽 Badge');
    }

    public function test_platform_admin_can_disable_badge_and_revoke_award_with_audit_reason(): void
    {
        [$owner, $event] = $this->eventWithOwner();
        $admin = User::factory()->create(['is_admin' => true]);
        $member = User::factory()->create();
        $badge = EventBadge::create([
            'event_id' => $event->id,
            'created_by' => $owner->id,
            'name' => '測試 Badge',
            'type' => 'special',
            'eligibility' => 'any',
            'claim_enabled' => true,
        ]);
        $award = UserEventBadge::create([
            'event_badge_id' => $badge->id,
            'user_id' => $member->id,
            'awarded_by' => $owner->id,
            'awarded_at' => now(),
        ]);

        $this->actingAs($admin)->patch(route('admin.badges.toggle', $badge));
        $this->assertFalse($badge->fresh()->is_active);
        $this->assertTrue($badge->fresh()->claim_enabled);

        $this->actingAs($admin)->patch(route('admin.badge-awards.revoke', $award), ['reason' => '主辦方誤發']);
        $this->assertNotNull($award->fresh()->revoked_at);
        $this->assertSame('主辦方誤發', $award->fresh()->revoked_reason);
        $this->actingAs($member)->get(route('member-profile.index'))->assertDontSee('測試 Badge');
    }

    /** @return array{User,Event} */
    private function eventWithOwner(): array
    {
        $owner = User::factory()->create();
        $event = Event::factory()->create();
        EventStaff::create([
            'event_id' => $event->id,
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
            'invited_by' => $owner->id,
            'invited_at' => now(),
            'accepted_at' => now(),
        ]);

        return [$owner, $event];
    }

    private function register(Event $event, User $user, string $status): EventRegistration
    {
        $group = EventGroup::factory()->create(['event_id' => $event->id]);

        return EventRegistration::create([
            'event_id' => $event->id,
            'event_group_id' => $group->id,
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => $status,
        ]);
    }
}
