# Backend Architecture, Development & Testing Guide

## 1. Project Overview & Phased Roadmap

This document outlines the backend architecture, development conventions, API file generation commands, and automated testing guidelines for the **HR Management & AI-driven Recruitment Platform**.

---

### ⚠️ Critical Decision: Phasing AI Integration
> **Decision:** **AI features (FastAPI, CV Matching, STT, Vision Proctoring) are strictly postponed to Phase 2.**
> 
> **Why this is the correct engineering approach:**
> 1. **Solid Foundation First:** The core business workflows (Authentication, RBAC, Job Management, Applications, Interviews, Exams, and Offers) must function independently and reliably.
> 2. **Database Readiness:** The database schema already contains all necessary AI tables (`cv_match_results`, `ai_review_decisions`, `proctoring_events`, etc.).
> 3. **The Mock / Stub Strategy (Phase 1):** In Phase 1, we use a service layer with simulated responses (e.g., returning a mock score of `85%` or calculating a simple keyword match). This allows the frontend and backend teams to build, test, and deliver the complete end-to-end recruitment lifecycle without waiting for AI model training or Python microservices.
> 4. **Seamless Phase 2 Plug-in:** When the FastAPI service is ready, we simply swap the mock logic inside the `CvMatchService` with an HTTP call (`Http::post('http://fastapi-service/match')`), without touching any controllers, database tables, or frontend contracts.

---

## 2. Architecture & File Structure Conventions

To maintain a clean, testable, and enterprise-grade codebase, avoid putting business logic inside Controllers. Follow the **Controller -> FormRequest -> Service -> API Resource** pattern:

```text
app/
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       ├── Auth/                 # Login, Register, PasswordReset
│   │       ├── Candidate/            # Profile, CV, Experience, Applications
│   │       ├── Company/              # Profile, Employee Approval, Roles
│   │       ├── Job/                  # Job CRUD, Nominations, Stages
│   │       ├── Application/          # Application Review, Stage Progression
│   │       ├── Exam/                 # Exam Attempts, Questions, Grading
│   │       └── Interview/            # Slots, Bookings, Evaluations
│   ├── Requests/                     # Form Request Validation Classes
│   │   ├── Auth/
│   │   ├── Job/
│   │   └── ...
│   └── Resources/                    # API JSON Transformation Resources
│       ├── UserResource.php
│       ├── JobResource.php
│       └── ...
├── Services/                         # Core Business Logic & Orchestration
│   ├── AuthService.php
│   ├── JobService.php
│   ├── ApplicationService.php
│   ├── ExamGradingService.php
│   ├── InterviewBookingService.php
│   └── CvMatchService.php            # (Mocked in Phase 1)
└── Models/                           # Eloquent Models (45 Models existing)

tests/
├── Feature/                          # End-to-end API Endpoint Tests
│   ├── AuthTest.php
│   ├── CompanyOnboardingTest.php
│   ├── JobPostingTest.php
│   ├── ApplicationPipelineTest.php
│   ├── ExamAttemptTest.php
│   └── InterviewBookingTest.php
└── Unit/                             # Isolated Unit Tests
```

---

## 3. Workflow Mapping & Artisan CLI Commands

Based on the 6 system workflow diagrams, here are the exact Artisan commands to generate all necessary classes:

### Module 1: Registration, Onboarding & RBAC (Workflows 1 & 2)
*Covers: Candidate registration, Company registration with proof doc, Super Admin approval, and Employee join requests.*

```bash
# 1. Controllers
php artisan make:controller Api/Auth/AuthController
php artisan make:controller Api/Company/CompanyRegistrationController
php artisan make:controller Api/Company/CompanyEmployeeController
php artisan make:controller Api/Admin/CompanyApprovalController

# 2. Form Requests (Validation)
php artisan make:request Auth/RegisterRequest
php artisan make:request Auth/LoginRequest
php artisan make:request Company/RegisterCompanyRequest
php artisan make:request Company/JoinCompanyRequest
php artisan make:request Company/ReviewEmployeeRequest

# 3. Resources (JSON Output)
php artisan make:resource UserResource
php artisan make:resource CompanyResource
php artisan make:resource CompanyEmployeeResource
```

---

### Module 2: Job Postings & Nominations (Workflow 3)
*Covers: Job creation (draft/publish), public vs private visibility, nomination lists for employees, and stage configurations (exams + interview rounds).*

```bash
# 1. Controllers
php artisan make:controller Api/Job/JobController --api
php artisan make:controller Api/Job/JobNominationController
php artisan make:controller Api/Job/JobStageConfigController

# 2. Form Requests
php artisan make:request Job/CreateJobRequest
php artisan make:request Job/UpdateJobRequest
php artisan make:request Job/NominateEmployeeRequest

# 3. Resources
php artisan make:resource JobResource
php artisan make:resource JobDetailResource
php artisan make:resource JobNominationResource
```

---

### Module 3: Application Pipeline & AI Screening (Workflow 4)
*Covers: Submitting applications (CV required), simulated CV match, HR review queue, AI override with notes, and candidate withdrawal.*

```bash
# 1. Controllers
php artisan make:controller Api/Application/ApplicationController
php artisan make:controller Api/Application/ApplicationReviewController

# 2. Form Requests
php artisan make:request Application/SubmitApplicationRequest
php artisan make:request Application/ReviewApplicationRequest

# 3. Resources
php artisan make:resource ApplicationResource
php artisan make:resource ApplicationStatusHistoryResource
php artisan make:resource CvMatchResultResource
```

---

### Module 4: Exam System, Proctoring & Auto-Grading (Workflow 5)
*Covers: Timed exams, max 3 attempts rule, MCQ auto-grading, written questions, and proctoring event recording.*

```bash
# 1. Controllers
php artisan make:controller Api/Exam/ExamController --api
php artisan make:controller Api/Exam/ExamAttemptController
php artisan make:controller Api/Exam/ProctoringController

# 2. Form Requests
php artisan make:request Exam/StartAttemptRequest
php artisan make:request Exam/SubmitAnswerRequest
php artisan make:request Exam/RecordProctoringEventRequest

# 3. Resources
php artisan make:resource ExamResource
php artisan make:resource ExamAttemptResource
php artisan make:resource ExamQuestionResource
```

---

### Module 5: Interview Scheduling & Scorecards (Workflow 6)
*Covers: Interviewer availability slots, candidate slot booking (no double-booking), 24h cancellation rule, and scorecards.*

```bash
# 1. Controllers
php artisan make:controller Api/Interview/InterviewerAvailabilityController
php artisan make:controller Api/Interview/InterviewBookingController
php artisan make:controller Api/Interview/InterviewEvaluationController

# 2. Form Requests
php artisan make:request Interview/SetAvailabilityRequest
php artisan make:request Interview/BookSlotRequest
php artisan make:request Interview/SubmitEvaluationRequest

# 3. Resources
php artisan make:resource InterviewBookingResource
php artisan make:resource InterviewEvaluationResource
```

---

### Module 6: Offers & Final Decision (Workflow 4)
*Covers: Formal offer generation (salary within job range), candidate accept/reject, and closing openings.*

```bash
# 1. Controllers
php artisan make:controller Api/Offer/OfferController

# 2. Form Requests
php artisan make:request Offer/CreateOfferRequest
php artisan make:request Offer/RespondOfferRequest

# 3. Resources
php artisan make:resource OfferResource
```

---

## 4. Phase 1: Mocking the AI Service

Create `app/Services/CvMatchService.php`. In Phase 1, it provides a deterministic or randomized mock matching score so the entire pipeline flows seamlessly:

```php
<?php

namespace App\Services;

use App\Models\Application;
use App\Models\CvMatchResult;

class CvMatchService
{
    /**
     * Evaluate Candidate Application in Phase 1 (Mocked).
     */
    public function evaluate(Application $application): CvMatchResult
    {
        // Simulated Match Score (e.g. 85%)
        $mockScore = 85.00;
        
        return CvMatchResult::create([
            'application_id' => $application->id,
            'match_score'    => $mockScore,
            'matched_skills' => ['PHP', 'Laravel', 'MySQL'],
            'missing_skills' => ['Docker'],
            'analysis_summary' => 'Candidate meets primary technical criteria with strong Laravel experience (Phase 1 Simulated Score).',
            'status'         => 'completed',
        ]);
    }
}
```

> **Phase 2 Transition:** When the FastAPI microservice is deployed, replace the method body with `Http::timeout(10)->post('http://ai-service/cv-match', [...])`. **No other code in the application needs modification.**

---

## 5. Automated Testing Guidelines

Tests live in `tests/Feature/`. Every developer must write and verify automated tests before opening a Pull Request.

### Command to Create a Test
```bash
php artisan make:test Feature/AuthTest
php artisan make:test Feature/JobPostingTest
php artisan make:test Feature/ApplicationPipelineTest
php artisan make:test Feature/InterviewBookingTest
```

### Complete Example 1: Authentication & Account Status Test
Create `tests/Feature/AuthTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\AccountStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_candidate_can_register_and_defaults_to_active_status(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'Ahmed Candidate',
            'email'                 => 'ahmed@example.com',
            'password'              => 'Secret123!',
            'password_confirmation' => 'Secret123!',
            'account_type'          => 'candidate',
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('data.account_status_id', AccountStatus::ACTIVE);

        $this->assertDatabaseHas('users', [
            'email'             => 'ahmed@example.com',
            'account_status_id' => AccountStatus::ACTIVE,
        ]);
    }

    public function test_blocked_user_cannot_login(): void
    {
        $user = User::factory()->create([
            'password'          => bcrypt('password123'),
            'account_status_id' => AccountStatus::BLOCKED,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => $user->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(403)
                 ->assertJson(['message' => 'Account is blocked or inactive.']);
    }
}
```

---

### Complete Example 2: Interview Booking & 24h Cancellation Rule Test
Create `tests/Feature/InterviewBookingTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\InterviewBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InterviewBookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_candidate_cannot_cancel_interview_within_24_hours(): void
    {
        $candidate = User::factory()->create(['account_type' => 'candidate']);
        
        // Booking scheduled 5 hours from now
        $booking = InterviewBooking::factory()->create([
            'candidate_id' => $candidate->id,
            'scheduled_at' => now()->addHours(5),
            'status'       => 'confirmed',
        ]);

        $response = $this->actingAs($candidate, 'sanctum')
                         ->deleteJson("/api/interviews/bookings/{$booking->id}");

        $response->assertStatus(422)
                 ->assertJsonFragment(['message' => 'Cancellations are only allowed at least 24 hours prior to the session.']);
    }
}
```

---

## 6. How to Run & Verify Tests

Run all tests across the platform:
```bash
# Run entire test suite:
php artisan test

# Run a specific test class:
php artisan test --filter=AuthTest

# Stop on first failure:
php artisan test --stop-on-failure
```

---

## 7. Development Checklist for Backend Pull Requests

Before pushing any feature branch, verify:
- [ ] Database migrations pass: `php artisan migrate:fresh`
- [ ] Automated tests pass: `php artisan test`
- [ ] Controllers are thin and delegate validation to `FormRequest` classes.
- [ ] Responses are formatted using `JsonResource` classes.
- [ ] New entity creations leverage `UID` and `LUID` via the `Auditable` trait automatically.
- [ ] Sensitive actions implement authorization policies (`Gate::authorize(...)`).
