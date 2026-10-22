<?php

namespace Tests\Feature;

use App\Filament\Resources\FamilyAuthorizations\Pages\ManageFamilyAuthorizations;
use App\Filament\Resources\VolunteerProfiles\Pages\ManageVolunteerProfiles;
use App\Models\FamilyAuthorization;
use App\Models\HelpRequest;
use App\Models\User;
use App\Models\VolunteerProfile;
use App\Services\CareService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_admin_pages_render_and_review_uses_domain_service(): void
    {
        Queue::fake();
        $this->seed();
        $admin = User::where('is_admin', true)->firstOrFail();
        $this->actingAs($admin);
        foreach (['/admin', '/admin/help-requests', '/admin/volunteer-profiles', '/admin/family-authorizations', '/admin/beneficiaries', '/admin/users', '/admin/incidents', '/admin/zones', '/admin/service-categories', '/admin/settings', '/admin/audit-logs'] as $path) {
            $this->get($path)->assertOk();
        }
        $profile = VolunteerProfile::where('status', 'pending')->firstOrFail();
        Livewire::test(ManageVolunteerProfiles::class)->callTableAction('review', $profile, ['status' => 'approved', 'note' => 'Fictional identity and service area verified.'])->assertHasNoTableActionErrors();
        $this->assertEquals('approved', $profile->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'volunteer_approved', 'subject_id' => $profile->id]);
        $grant = FamilyAuthorization::where('status', 'pending')->firstOrFail();
        Livewire::test(ManageFamilyAuthorizations::class)->callTableAction('review', $grant, ['status' => 'approved', 'note' => 'Fictional recipient consent checked in person.'])->assertHasNoTableActionErrors();
        $this->assertEquals('approved', $grant->fresh()->status);
        $task = HelpRequest::firstOrFail();
        $content = view('admin.request-details', ['request' => $task, 'candidates' => app(CareService::class)->candidates($task)])->render();
        $this->assertStringContainsString('Candidate eligibility', $content);
        $this->assertStringContainsString('Invitation history',$content);
    }
}
