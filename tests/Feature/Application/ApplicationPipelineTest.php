<?php

namespace Tests\Feature\Application;

use App\Models\Application;
use App\Models\CandidateCv;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_candidate_can_apply_to_available_job(): void
    {
        $candidateUser = User::factory()->create();
        $candidateProfile = CandidateProfile::create([
            'user_id'  => $candidateUser->id,
            'headline' => 'Backend Developer',
        ]);

        $cv = CandidateCv::create([
            'candidate_id'      => $candidateProfile->id,
            'original_filename' => 'resume.pdf',
            'storage_path'      => 'cvs/resume.pdf',
            'mime_type'         => 'application/pdf',
            'size_bytes'        => 102400,
            'version'           => 1,
            'is_active'         => true,
        ]);

        $company = Company::factory()->create(['status' => 'approved']);
        $job = Job::factory()->create([
            'company_id' => $company->id,
            'status'     => 'published',
            'visibility' => 'public',
        ]);

        $response = $this->actingAs($candidateUser)->postJson("/api/jobs/{$job->id}/apply", [
            'cover_note' => 'I am very interested in this software engineering position.',
            'cv_id'      => $cv->id,
            'consent'    => true,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('application.status', 'submitted');
        $response->assertJsonPath('application.job.id', $job->id);

        $this->assertDatabaseHas('applications', [
            'job_id'       => $job->id,
            'candidate_id' => $candidateProfile->id,
            'status'       => 'submitted',
        ]);

        $this->assertDatabaseHas('application_status_history', [
            'to_status'  => 'submitted',
            'changed_by' => $candidateUser->id,
        ]);
    }

    public function test_candidate_cannot_apply_twice_to_same_active_job(): void
    {
        $candidateUser = User::factory()->create();
        $candidateProfile = CandidateProfile::create([
            'user_id' => $candidateUser->id,
        ]);

        $company = Company::factory()->create(['status' => 'approved']);
        $job = Job::factory()->create(['company_id' => $company->id, 'status' => 'published', 'visibility' => 'public']);

        Application::create([
            'job_id'       => $job->id,
            'candidate_id' => $candidateProfile->id,
            'status'       => 'submitted',
            'attempt_no'   => 1,
            'applied_at'   => now(),
        ]);

        $response = $this->actingAs($candidateUser)->postJson("/api/jobs/{$job->id}/apply", [
            'cover_note' => 'Trying to apply again.',
            'consent'    => true,
        ]);

        $response->assertStatus(409);
        $response->assertJsonPath('message', 'You already have an active application for this position.');
    }

    public function test_candidate_can_withdraw_application(): void
    {
        $candidateUser = User::factory()->create();
        $candidateProfile = CandidateProfile::create([
            'user_id' => $candidateUser->id,
        ]);

        $company = Company::factory()->create(['status' => 'approved']);
        $job = Job::factory()->create(['company_id' => $company->id, 'status' => 'published', 'visibility' => 'public']);

        $application = Application::create([
            'job_id'       => $job->id,
            'candidate_id' => $candidateProfile->id,
            'status'       => 'submitted',
            'attempt_no'   => 1,
            'applied_at'   => now(),
        ]);

        $response = $this->actingAs($candidateUser)->deleteJson("/api/candidate/applications/{$application->id}/withdraw");
        $response->assertStatus(200);

        $this->assertDatabaseHas('applications', [
            'id'     => $application->id,
            'status' => 'withdrawn',
        ]);

        $this->assertDatabaseHas('application_status_history', [
            'application_id' => $application->id,
            'from_status'    => 'submitted',
            'to_status'      => 'withdrawn',
        ]);
    }

    public function test_company_can_view_applications_and_update_stage(): void
    {
        $companyOwner = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $companyOwner->id, 'status' => 'approved']);
        $job = Job::factory()->create(['company_id' => $company->id, 'status' => 'published', 'visibility' => 'public']);

        $candidateUser = User::factory()->create();
        $candidateProfile = CandidateProfile::create([
            'user_id'  => $candidateUser->id,
            'headline' => 'Senior Frontend Developer',
        ]);

        $application = Application::create([
            'job_id'       => $job->id,
            'candidate_id' => $candidateProfile->id,
            'status'       => 'submitted',
            'attempt_no'   => 1,
            'applied_at'   => now(),
        ]);

        // Company lists applications
        $listResponse = $this->actingAs($companyOwner)->getJson("/api/company/jobs/{$job->id}/applications");
        $listResponse->assertStatus(200);
        $listResponse->assertJsonCount(1, 'data');

        // Company updates stage to shortlisted
        $updateResponse = $this->actingAs($companyOwner)->patchJson("/api/company/applications/{$application->id}/status", [
            'status'  => 'shortlisted',
            'comment' => 'Candidate looks very promising after initial screening.',
        ]);

        $updateResponse->assertStatus(200);
        $updateResponse->assertJsonPath('application.status', 'shortlisted');

        $this->assertDatabaseHas('applications', [
            'id'     => $application->id,
            'status' => 'shortlisted',
        ]);

        $this->assertDatabaseHas('application_status_history', [
            'application_id' => $application->id,
            'from_status'    => 'submitted',
            'to_status'      => 'shortlisted',
            'reason'         => 'Candidate looks very promising after initial screening.',
        ]);
    }

    public function test_application_stage_machine_rejects_non_sequential_hire_transition(): void
    {
        $companyOwner = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $companyOwner->id, 'status' => 'approved']);
        $job = Job::factory()->create(['company_id' => $company->id]);
        $candidate = User::factory()->create();
        $profile = CandidateProfile::create(['user_id' => $candidate->id]);
        $application = Application::create([
            'job_id'       => $job->id,
            'candidate_id' => $profile->id,
            'status'       => 'submitted',
        ]);

        $response = $this->actingAs($companyOwner)->patchJson("/api/company/applications/{$application->id}/status", [
            'status' => 'hired',
        ]);

        $response->assertStatus(409);
        $response->assertJsonPath('message', "Cannot transition from 'submitted' to 'hired'.");
        $response->assertJsonPath('allowed_transitions.0', 'screening');
        $this->assertDatabaseHas('applications', ['id' => $application->id, 'status' => 'submitted']);
    }

    public function test_application_stage_machine_rejects_reopening_rejected_application(): void
    {
        $companyOwner = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $companyOwner->id, 'status' => 'approved']);
        $job = Job::factory()->create(['company_id' => $company->id]);
        $candidate = User::factory()->create();
        $profile = CandidateProfile::create(['user_id' => $candidate->id]);
        $application = Application::create([
            'job_id'       => $job->id,
            'candidate_id' => $profile->id,
            'status'       => 'rejected',
        ]);

        $response = $this->actingAs($companyOwner)->patchJson("/api/company/applications/{$application->id}/status", [
            'status' => 'shortlisted',
        ]);

        $response->assertStatus(409);
        $response->assertJsonPath('message', "Cannot transition from 'rejected' to 'shortlisted'.");
        $response->assertJsonPath('allowed_transitions', []);
        $this->assertDatabaseHas('applications', ['id' => $application->id, 'status' => 'rejected']);
    }
}
