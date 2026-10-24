<?php

namespace Tests\Feature\Offer;

use App\Models\Application;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\Job;
use App\Models\Offer;
use App\Models\SalaryRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfferLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_can_issue_offer_and_application_status_updates(): void
    {
        $employer = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $employer->id]);
        $job = Job::factory()->create(['company_id' => $company->id]);

        $candidateUser = User::factory()->create();
        $candidateProfile = CandidateProfile::create(['user_id' => $candidateUser->id]);
        $application = Application::create([
            'job_id'       => $job->id,
            'candidate_id' => $candidateProfile->id,
            'status'       => 'interviewing',
        ]);

        $startDate = now()->addDays(14)->toDateString();
        $expiresAt = now()->addDays(7)->toDateTimeString();

        $response = $this->actingAs($employer)->postJson('/api/company/offers', [
            'application_id' => $application->id,
            'salary_amount'  => 28000,
            'currency'       => 'EGP',
            'salary_period'  => 'month',
            'start_date'     => $startDate,
            'expires_at'     => $expiresAt,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('offer.salary_amount', 28000);
        $response->assertJsonPath('offer.status', 'sent');

        $this->assertDatabaseHas('offers', [
            'application_id' => $application->id,
            'salary_amount'  => 28000,
            'status'         => 'sent',
        ]);

        $this->assertDatabaseHas('applications', [
            'id'     => $application->id,
            'status' => 'offered',
        ]);

        $this->assertDatabaseHas('application_status_history', [
            'application_id' => $application->id,
            'from_status'    => 'interviewing',
            'to_status'      => 'offered',
        ]);
    }

    public function test_candidate_can_view_and_accept_offer(): void
    {
        $employer = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $employer->id]);
        $job = Job::factory()->create(['company_id' => $company->id]);

        $candidateUser = User::factory()->create();
        $candidateProfile = CandidateProfile::create(['user_id' => $candidateUser->id]);
        $application = Application::create([
            'job_id'       => $job->id,
            'candidate_id' => $candidateProfile->id,
            'status'       => 'offered',
        ]);

        $offer = Offer::create([
            'application_id' => $application->id,
            'salary_amount'  => 30000,
            'currency'       => 'EGP',
            'salary_period'  => 'month',
            'start_date'     => now()->addDays(10)->toDateString(),
            'status'         => 'sent',
            'sent_by'        => $employer->id,
            'sent_at'        => now(),
        ]);

        // Candidate views offers
        $listResponse = $this->actingAs($candidateUser)->getJson('/api/candidate/offers');
        $listResponse->assertStatus(200);
        $listResponse->assertJsonCount(1, 'data');

        // Candidate accepts offer
        $acceptResponse = $this->actingAs($candidateUser)->postJson("/api/candidate/offers/{$offer->id}/accept");
        $acceptResponse->assertStatus(200);
        $acceptResponse->assertJsonPath('offer.status', 'accepted');

        $this->assertDatabaseHas('offers', [
            'id'     => $offer->id,
            'status' => 'accepted',
        ]);

        $this->assertDatabaseHas('applications', [
            'id'     => $application->id,
            'status' => 'hired',
        ]);

        $this->assertDatabaseHas('application_status_history', [
            'application_id' => $application->id,
            'from_status'    => 'offered',
            'to_status'      => 'hired',
        ]);
    }

    public function test_candidate_can_reject_offer(): void
    {
        $employer = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $employer->id]);
        $job = Job::factory()->create(['company_id' => $company->id]);

        $candidateUser = User::factory()->create();
        $candidateProfile = CandidateProfile::create(['user_id' => $candidateUser->id]);
        $application = Application::create([
            'job_id'       => $job->id,
            'candidate_id' => $candidateProfile->id,
            'status'       => 'offered',
        ]);

        $offer = Offer::create([
            'application_id' => $application->id,
            'salary_amount'  => 22000,
            'currency'       => 'EGP',
            'salary_period'  => 'month',
            'start_date'     => now()->addDays(10)->toDateString(),
            'status'         => 'sent',
            'sent_by'        => $employer->id,
            'sent_at'        => now(),
        ]);

        $rejectResponse = $this->actingAs($candidateUser)->postJson("/api/candidate/offers/{$offer->id}/reject", [
            'reason' => 'Found another offer with better alignment.',
        ]);

        $rejectResponse->assertStatus(200);
        $rejectResponse->assertJsonPath('offer.status', 'rejected');

        $this->assertDatabaseHas('offers', [
            'id'     => $offer->id,
            'status' => 'rejected',
        ]);

        $this->assertDatabaseHas('applications', [
            'id'     => $application->id,
            'status' => 'rejected',
        ]);
    }

    public function test_candidate_cannot_accept_or_reject_an_offer_that_is_not_sent(): void
    {
        $employer = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $employer->id]);
        $job = Job::factory()->create(['company_id' => $company->id]);
        $candidate = User::factory()->create();
        $profile = CandidateProfile::create(['user_id' => $candidate->id]);

        $acceptedApplication = Application::create([
            'job_id'       => $job->id,
            'candidate_id' => $profile->id,
            'status'       => 'hired',
            'attempt_no'   => 1,
        ]);
        $acceptedOffer = Offer::create([
            'application_id' => $acceptedApplication->id,
            'salary_amount'  => 30000,
            'currency'       => 'EGP',
            'salary_period'  => 'month',
            'start_date'     => now()->addDays(10)->toDateString(),
            'status'         => 'accepted',
            'sent_by'        => $employer->id,
            'sent_at'        => now(),
        ]);

        $rejectedApplication = Application::create([
            'job_id'       => $job->id,
            'candidate_id' => $profile->id,
            'status'       => 'rejected',
            'attempt_no'   => 2,
        ]);
        $rejectedOffer = Offer::create([
            'application_id' => $rejectedApplication->id,
            'salary_amount'  => 25000,
            'currency'       => 'EGP',
            'salary_period'  => 'month',
            'start_date'     => now()->addDays(10)->toDateString(),
            'status'         => 'rejected',
            'sent_by'        => $employer->id,
            'sent_at'        => now(),
        ]);

        $this->actingAs($candidate)
            ->postJson("/api/candidate/offers/{$acceptedOffer->id}/accept")
            ->assertStatus(409)
            ->assertJsonPath('message', "Cannot accept offer with status 'accepted'. Only 'sent' offers can be accepted.");

        $this->actingAs($candidate)
            ->postJson("/api/candidate/offers/{$rejectedOffer->id}/reject", ['reason' => 'No longer interested.'])
            ->assertStatus(409)
            ->assertJsonPath('message', "Cannot reject offer with status 'rejected'. Only 'sent' offers can be rejected.");
    }

    public function test_candidate_cannot_respond_to_another_candidates_offer(): void
    {
        $employer = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $employer->id]);
        $job = Job::factory()->create(['company_id' => $company->id]);
        $ownerCandidate = User::factory()->create();
        $ownerProfile = CandidateProfile::create(['user_id' => $ownerCandidate->id]);
        $otherCandidate = User::factory()->create();
        $application = Application::create([
            'job_id'       => $job->id,
            'candidate_id' => $ownerProfile->id,
            'status'       => 'offered',
        ]);
        $offer = Offer::create([
            'application_id' => $application->id,
            'salary_amount'  => 28000,
            'currency'       => 'EGP',
            'salary_period'  => 'month',
            'start_date'     => now()->addDays(10)->toDateString(),
            'status'         => 'sent',
            'sent_by'        => $employer->id,
            'sent_at'        => now(),
        ]);

        $this->actingAs($otherCandidate)
            ->postJson("/api/candidate/offers/{$offer->id}/accept")
            ->assertStatus(403);

        $this->actingAs($otherCandidate)
            ->postJson("/api/candidate/offers/{$offer->id}/reject", ['reason' => 'Not mine.'])
            ->assertStatus(403);

        $this->assertDatabaseHas('offers', ['id' => $offer->id, 'status' => 'sent']);
    }

    public function test_salary_benchmark_returns_market_summary(): void
    {
        $user = User::factory()->create();

        SalaryRecord::create([
            'job_title'        => 'Backend Engineer',
            'currency'         => 'EGP',
            'amount_min'       => 15000,
            'amount_max'       => 25000,
            'experience_level' => 'mid',
            'source'           => 'market_survey',
        ]);

        SalaryRecord::create([
            'job_title'        => 'Senior Backend Engineer',
            'currency'         => 'EGP',
            'amount_min'       => 30000,
            'amount_max'       => 45000,
            'experience_level' => 'senior',
            'source'           => 'market_survey',
        ]);

        $response = $this->actingAs($user)->getJson('/api/salary-benchmark?job_title=Backend');

        $response->assertStatus(200);
        $response->assertJsonPath('market_summary.total_records', 2);
        $response->assertJsonPath('market_summary.average_minimum', 22500);
        $response->assertJsonPath('market_summary.average_maximum', 35000);
        $response->assertJsonCount(2, 'records');
    }
}

