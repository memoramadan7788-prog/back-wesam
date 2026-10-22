<?php

namespace Tests\Feature\Candidate;

use App\Models\CandidateProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CandidateProfileAndCvTest extends TestCase
{
    use RefreshDatabase;

    public function test_candidate_can_view_and_update_profile(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/candidate/profile');
        $response->assertStatus(200);

        $updateResponse = $this->actingAs($user)->putJson('/api/candidate/profile', [
            'headline'         => 'Full Stack Engineer',
            'bio'              => 'Experienced Laravel & React developer.',
            'phone'            => '+201112223334',
            'years_experience' => 5,
        ]);

        $updateResponse->assertStatus(200);
        $updateResponse->assertJsonPath('data.headline', 'Full Stack Engineer');
        $updateResponse->assertJsonPath('data.years_experience', 5);

        $this->assertDatabaseHas('candidate_profiles', [
            'user_id'          => $user->id,
            'headline'         => 'Full Stack Engineer',
            'years_experience' => 5,
        ]);
    }

    public function test_candidate_can_upload_and_manage_cvs(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $file = UploadedFile::fake()->create('resume.pdf', 500, 'application/pdf');

        $uploadResponse = $this->actingAs($user)->postJson('/api/candidate/cvs', [
            'cv' => $file,
        ]);

        $uploadResponse->assertStatus(201);
        $uploadResponse->assertJsonPath('cv.original_filename', 'resume.pdf');
        $uploadResponse->assertJsonPath('cv.version', 1);
        $uploadResponse->assertJsonPath('cv.is_active', true);

        $cvId = $uploadResponse->json('cv.id');

        $listResponse = $this->actingAs($user)->getJson('/api/candidate/cvs');
        $listResponse->assertStatus(200);
        $listResponse->assertJsonCount(1, 'data');

        $deleteResponse = $this->actingAs($user)->deleteJson("/api/candidate/cvs/{$cvId}");
        $deleteResponse->assertStatus(200);
    }

    public function test_candidate_can_add_experience_and_education(): void
    {
        $user = User::factory()->create();

        $expResponse = $this->actingAs($user)->postJson('/api/candidate/experiences', [
            'company_name' => 'Google',
            'job_title'    => 'Software Engineer',
            'start_date'   => '2022-01-01',
            'is_current'   => true,
            'description'  => 'Working on cloud platforms.',
        ]);

        $expResponse->assertStatus(201);
        $expResponse->assertJsonPath('experience.company_name', 'Google');
        $expId = $expResponse->json('experience.id');

        $eduResponse = $this->actingAs($user)->postJson('/api/candidate/educations', [
            'degree'          => 'B.Sc. in Computer Science',
            'institution'     => 'Cairo University',
            'graduation_year' => 2021,
        ]);

        $eduResponse->assertStatus(201);
        $eduResponse->assertJsonPath('education.institution', 'Cairo University');
        $eduId = $eduResponse->json('education.id');

        $this->actingAs($user)->deleteJson("/api/candidate/experiences/{$expId}")->assertStatus(200);
        $this->actingAs($user)->deleteJson("/api/candidate/educations/{$eduId}")->assertStatus(200);
    }
}

