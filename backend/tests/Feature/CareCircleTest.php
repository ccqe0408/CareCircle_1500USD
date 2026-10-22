<?php

namespace Tests\Feature;

use App\Jobs\MatchRequest;
use App\Models\Beneficiary;
use App\Models\FamilyAuthorization;
use App\Models\HelpRequest;
use App\Models\Incident;
use App\Models\RequestEvent;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Models\VolunteerProfile;
use App\Models\Zone;
use App\Services\CareService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CareCircleTest extends TestCase
{
    use RefreshDatabase;

    private User $resident;

    private User $family;

    private User $volunteer;

    private User $admin;

    private Beneficiary $beneficiary;

    private CareService $care;

    private Zone $zone;

    private ServiceCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Queue::fake();
        $this->travelTo(now()->startOfDay()->addHours(1));
        $this->care = app(CareService::class);
        $this->zone = Zone::create(['name' => 'Test area']);
        $this->category = ServiceCategory::create(['name' => 'Groceries', 'slug' => 'prepaid-groceries', 'description' => 'Prepaid only']);
        $this->resident = $this->user('Resident');
        $this->family = $this->user('Family');
        $this->volunteer = $this->user('Volunteer');
        $this->admin = $this->user('Admin');
        $this->admin->forceFill(['is_admin' => true])->save();
        $this->beneficiary = Beneficiary::create(['user_id' => $this->resident->id, 'created_by' => $this->resident->id, 'name' => 'Demo resident', 'phone' => '000', 'address' => 'Private test address', 'zone_id' => $this->zone->id]);
        $this->profile($this->volunteer);
    }

    private function user(string $name): User
    {
        return User::create(['name' => $name, 'email' => strtolower($name).'@test.example', 'password' => 'TestPassword2026', 'phone' => '000']);
    }

    private function profile(User $u, array $override = []): VolunteerProfile
    {
        return VolunteerProfile::create(['user_id' => $u->id, 'status' => 'approved', 'zone_ids' => [$this->zone->id], 'category_ids' => [$this->category->id], 'availability' => array_map(fn ($d) => ['day' => $d, 'start' => '00:00', 'end' => '23:59'], range(1, 7)), 'max_tasks' => 1, ...$override]);
    }

    private function payload(): array
    {
        $start = now()->addDay()->setTime(8, 0);

        return ['beneficiary_id' => $this->beneficiary->id, 'service_category_id' => $this->category->id, 'title' => 'Collect prepaid groceries', 'pickup_address' => 'Private pickup counter', 'scheduled_start' => $start->toIso8601String(), 'scheduled_end' => $start->copy()->addHour()->toIso8601String(), 'prepaid_confirmed' => true];
    }

    private function task(): HelpRequest
    {
        return $this->care->createRequest($this->resident, $this->payload());
    }

    private function accept(): HelpRequest
    {
        $r = $this->task();
        $this->care->match($r->id);
        $o = $r->offers()->firstOrFail();

        return $this->care->accept($this->volunteer, $o->id);
    }

    private function ready(): HelpRequest
    {
        $r = $this->accept();
        $r = $this->care->progress($this->volunteer, $r, 'start');

        return $this->care->progress($this->volunteer, $r, 'arrive');
    }

    public function test_registration_cannot_elevate_to_admin_and_logout_revokes_token(): void
    {
        $response = $this->postJson('/api/v1/register', ['name' => 'New member', 'email' => 'new@example.test', 'phone' => '000', 'password' => 'HelloWorld2026', 'password_confirmation' => 'HelloWorld2026', 'is_admin' => true, 'suspended' => false])->assertCreated();
        $this->assertFalse(User::where('email', 'new@example.test')->first()->is_admin);
        $token = $response->json('token');
        $this->withToken($token)->getJson('/api/v1/me')->assertOk();
        $this->withToken($token)->postJson('/api/v1/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_guest_and_non_admin_cannot_access_protected_surfaces(): void
    {
        $this->getJson('/api/v1/requests')->assertUnauthorized();
        $this->actingAs($this->resident)->get('/admin')->assertForbidden();
        $this->actingAs($this->admin)->get('/admin')->assertOk();
    }

    public function test_family_requires_current_verified_authorization(): void
    {
        Sanctum::actingAs($this->family);
        $this->postJson('/api/v1/requests', $this->payload())->assertForbidden();
        $grant = FamilyAuthorization::create(['beneficiary_id' => $this->beneficiary->id, 'user_id' => $this->family->id, 'relationship' => 'Daughter', 'consent_note' => 'Recipient agreed to family help.']);
        $this->postJson('/api/v1/requests', $this->payload())->assertForbidden();
        $this->care->reviewFamily($this->admin, $grant, 'approved', 'Recipient consent verified in person.');
        $r = $this->postJson('/api/v1/requests', $this->payload())->assertCreated()->json('id');
        Queue::assertPushed(MatchRequest::class);
        $this->care->reviewFamily($this->admin, $grant, 'revoked', 'Recipient withdrew authorization.');
        $this->getJson('/api/v1/requests/'.$r)->assertForbidden();
        $this->assertDatabaseHas('help_requests', ['id' => $r, 'status' => 'escalated']);
    }

    public function test_end_to_end_handover_persists_and_cannot_be_replayed(): void
    {
        $r = $this->ready();
        $code = $this->care->issueCode($this->resident, $r)['code'];
        Sanctum::actingAs($this->volunteer);
        $this->postJson('/api/v1/requests/'.$r->id.'/confirm', ['code' => $code])->assertOk()->assertJsonPath('status', 'completed');
        $this->assertDatabaseHas('help_requests', ['id' => $r->id, 'completion_kind' => 'verified_handover', 'handover_hash' => null]);
        $this->postJson('/api/v1/requests/'.$r->id.'/confirm', ['code' => $code])->assertUnprocessable();
        Sanctum::actingAs($this->resident);
        $this->getJson('/api/v1/requests/'.$r->id)->assertOk()->assertJsonPath('status', 'completed')->assertJsonMissingPath('handover_hash');
    }

    public function test_invitation_excludes_addresses_and_other_users_cannot_access(): void
    {
        $r = $this->task();
        $this->care->match($r->id);
        Sanctum::actingAs($this->volunteer);
        $this->getJson('/api/v1/offers')->assertOk()->assertJsonMissingPath('0.request.pickup_address')->assertJsonMissingPath('0.request.beneficiary_name');
        $this->getJson('/api/v1/requests/'.$r->id)->assertForbidden();
        $other = $this->user('Other');
        Sanctum::actingAs($other);
        $this->postJson('/api/v1/offers/'.$r->offers()->first()->id.'/accept')->assertForbidden();
    }

    public function test_matching_rejects_unapproved_out_of_area_and_unavailable(): void
    {
        $this->volunteer->volunteerProfile->update(['status' => 'pending']);
        $other = $this->user('Outside');
        $this->profile($other, ['zone_ids' => [999]]);
        $third = $this->user('Busy');
        $this->profile($third, ['availability' => [['day' => 1, 'start' => '01:00', 'end' => '02:00']]]);
        $r = $this->task();
        $this->care->match($r->id);
        $this->assertEquals('escalated', $r->fresh()->status);
        $this->assertDatabaseCount('match_offers', 0);
    }

    public function test_stale_or_duplicate_accept_is_rejected(): void
    {
        $r = $this->task();
        $this->care->match($r->id);
        $o = $r->offers()->first();
        Sanctum::actingAs($this->volunteer);
        $this->postJson('/api/v1/offers/'.$o->id.'/accept')->assertOk();
        $this->postJson('/api/v1/offers/'.$o->id.'/accept')->assertUnprocessable();
        $this->assertEquals(1, RequestEvent::where('help_request_id', $r->id)->where('action', 'accepted')->count());
    }

    public function test_capacity_is_rechecked_when_accepting_an_existing_invitation(): void
    {
        $a = $this->task();
        $b = $this->task();
        $this->care->match($a->id);
        $this->care->match($b->id);
        $this->care->accept($this->volunteer, $a->offers()->first()->id);
        Sanctum::actingAs($this->volunteer);
        $this->postJson('/api/v1/offers/'.$b->offers()->first()->id.'/accept')->assertUnprocessable();
        $this->assertNull($b->fresh()->volunteer_id);
    }

    public function test_expired_invitation_is_rejected_and_sweep_invites_next_volunteer(): void
    {
        $next = $this->user('Next');
        $this->profile($next);
        $r = $this->task();
        $this->care->match($r->id);
        $o = $r->offers()->first();
        $o->update(['expires_at' => now()->subMinute()]);
        Sanctum::actingAs($this->volunteer);
        $this->postJson('/api/v1/offers/'.$o->id.'/accept')->assertUnprocessable();
        $this->artisan('carecircle:sweep')->assertSuccessful();
        $this->assertEquals('expired', $o->fresh()->status);
        $this->assertDatabaseHas('match_offers', ['help_request_id' => $r->id, 'volunteer_id' => $next->id, 'status' => 'pending']);
    }

    public function test_decline_queues_matching_and_revoked_offer_cannot_be_accepted(): void
    {
        $r = $this->task();
        $this->care->match($r->id);
        $o = $r->offers()->first();
        Sanctum::actingAs($this->volunteer);
        $this->postJson('/api/v1/offers/'.$o->id.'/decline')->assertOk();
        Queue::assertPushed(MatchRequest::class);
        $this->postJson('/api/v1/offers/'.$o->id.'/accept')->assertUnprocessable();
    }

    public function test_wrong_codes_persist_attempts_and_lock_after_five(): void
    {
        $r = $this->ready();
        $actual = $this->care->issueCode($this->resident, $r)['code'];
        $wrong = $actual === '111111' ? '222222' : '111111';
        Sanctum::actingAs($this->volunteer);
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/requests/'.$r->id.'/confirm', ['code' => $wrong])->assertUnprocessable();
        }
        $this->assertEquals(5, $r->fresh()->handover_attempts);
        $this->assertEquals('escalated', $r->fresh()->status);
        $this->postJson('/api/v1/requests/'.$r->id.'/confirm', ['code' => $actual])->assertUnprocessable();
    }

    public function test_expired_and_regenerated_codes_cannot_complete(): void
    {
        $r = $this->ready();
        $old = $this->care->issueCode($this->resident, $r)['code'];
        $new = $this->care->issueCode($this->resident, $r)['code'];
        Sanctum::actingAs($this->volunteer);
        $this->assertNotSame($old, $new);
        if ($old !== $new) {
            $this->postJson('/api/v1/requests/'.$r->id.'/confirm', ['code' => $old])->assertUnprocessable();
        }
        $r->update(['handover_expires_at' => now()->subSecond()]);
        $this->postJson('/api/v1/requests/'.$r->id.'/confirm', ['code' => $new])->assertUnprocessable();
    }

    public function test_volunteer_cannot_issue_code_or_skip_task_steps(): void
    {
        $r = $this->accept();
        Sanctum::actingAs($this->volunteer);
        $this->postJson('/api/v1/requests/'.$r->id.'/handover-code')->assertForbidden();
        $this->postJson('/api/v1/requests/'.$r->id.'/arrive')->assertUnprocessable();
    }

    public function test_in_progress_cancellation_requires_admin_resolution(): void
    {
        $r = $this->accept();
        $this->care->progress($this->volunteer, $r, 'start');
        Sanctum::actingAs($this->resident);
        $this->postJson('/api/v1/requests/'.$r->id.'/cancel', ['reason' => 'Recipient cannot receive now'])->assertOk()->assertJsonPath('status', 'escalated');
        $this->assertEquals($this->volunteer->id, $r->fresh()->volunteer_id);
        $this->care->close($this->admin, $r, 'Items returned to store; both parties informed.');
        $this->assertEquals('admin_closed', $r->fresh()->completion_kind);
        $this->assertEquals(0, $this->care->stats()['verified_completed']);
    }

    public function test_cancel_before_execution_revokes_offers(): void
    {
        $r = $this->task();
        $this->care->match($r->id);
        $this->care->cancel($this->resident, $r, 'No longer need collection');
        $this->assertEquals('cancelled', $r->fresh()->status);
        $this->assertEquals('revoked', $r->offers()->first()->status);
    }

    public function test_volunteer_withdrawal_preserves_history_and_queues_replacement(): void
    {
        $r = $this->accept();
        Sanctum::actingAs($this->volunteer);
        $this->postJson('/api/v1/requests/'.$r->id.'/cancel', ['reason' => 'Unable to attend this window'])->assertOk()->assertJsonPath('status', 'matching');
        $this->assertNull($r->fresh()->volunteer_id);
        Queue::assertPushed(MatchRequest::class);
    }

    public function test_suspension_revokes_execution_and_escalates_active_task(): void
    {
        $r = $this->accept();
        $this->care->reviewVolunteer($this->admin, $this->volunteer->volunteerProfile, 'suspended', 'Eligibility review is required.');
        Sanctum::actingAs($this->volunteer);
        $this->postJson('/api/v1/requests/'.$r->id.'/start')->assertUnprocessable();
        $this->getJson('/api/v1/requests/'.$r->id)->assertForbidden();
        $this->assertEquals('escalated', $r->fresh()->status);
    }

    public function test_overdue_tasks_escalate_without_app_running(): void
    {
        $r = $this->accept();
        $this->travelTo($r->scheduled_end->copy()->addMinute());
        $this->artisan('carecircle:sweep')->assertSuccessful();
        $this->assertEquals('escalated', $r->fresh()->status);
    }

    public function test_complaint_escalates_and_admin_resolution_is_audited(): void
    {
        $r = $this->accept();
        Sanctum::actingAs($this->resident);
        $id = $this->postJson('/api/v1/requests/'.$r->id.'/report', ['description' => 'The parcel collection needs community review.'])->assertCreated()->json('id');
        $this->assertEquals('escalated', $r->fresh()->status);
        $this->care->resolveIncident($this->admin, Incident::find($id), 'Contacted both parties and recorded next steps.');
        $this->assertDatabaseHas('audit_logs', ['action' => 'incident_resolved', 'subject_id' => $id]);
    }

    public function test_manual_assignment_rechecks_eligibility_and_invalidates_invitation(): void
    {
        $next = $this->user('Next');
        $this->profile($next);
        $r = $this->task();
        $this->care->match($r->id);
        $old = $r->offers()->first();
        $this->care->assign($this->admin, $r, $next, 'Volunteer agreed by phone; identity verified.');
        $this->assertEquals($next->id, $r->fresh()->volunteer_id);
        $this->assertEquals('revoked', $old->fresh()->status);
        Sanctum::actingAs($this->volunteer);
        $this->postJson('/api/v1/offers/'.$old->id.'/accept')->assertUnprocessable();
    }

    public function test_non_admin_cannot_use_admin_domain_actions(): void
    {
        $r = $this->task();
        $this->expectException(HttpException::class);
        $this->care->assign($this->resident, $r, $this->volunteer, 'Try to bypass admin permissions');
    }

    public function test_invalid_time_category_and_prepaid_consent_are_rejected(): void
    {
        Sanctum::actingAs($this->resident);
        $this->postJson('/api/v1/requests', [...$this->payload(), 'prepaid_confirmed' => false])->assertUnprocessable();
        $this->postJson('/api/v1/requests', [...$this->payload(), 'scheduled_start' => now()->subDay()->toIso8601String()])->assertUnprocessable();
        $this->category->update(['active' => false]);
        $this->postJson('/api/v1/requests', $this->payload())->assertUnprocessable();
    }

    public function test_inactive_family_permission_redacts_recipient_contact_details(): void
    {
        FamilyAuthorization::create(['beneficiary_id' => $this->beneficiary->id, 'user_id' => $this->family->id, 'relationship' => 'Daughter', 'consent_note' => 'Consent has been withdrawn.', 'status' => 'revoked']);
        Sanctum::actingAs($this->family);
        $this->getJson('/api/v1/beneficiaries')->assertOk()->assertJsonPath('0.can_request', false)->assertJsonPath('0.phone', '')->assertJsonPath('0.address', 'Private — family authorization required');
        $this->getJson('/api/v1/family-authorizations')->assertOk()->assertJsonMissingPath('0.beneficiary.address')->assertJsonMissingPath('0.beneficiary.phone');
    }

    public function test_invitation_limit_is_independent_of_confirmation_limit(): void
    {
        $r = $this->ready();
        Sanctum::actingAs($this->resident);
        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/v1/requests/'.$r->id.'/handover-code')->assertOk();
        }
        $this->postJson('/api/v1/requests/'.$r->id.'/handover-code')->assertStatus(429);
    }
}
