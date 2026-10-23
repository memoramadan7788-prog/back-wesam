<?php

namespace Database\Seeders;

use App\Models\AccountStatus;
use App\Models\Admin;
use App\Models\Application;
use App\Models\ApplicationStatusHistory;
use App\Models\CandidateCv;
use App\Models\CandidateEducation;
use App\Models\CandidateExperience;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\CompanyEmployee;
use App\Models\Country;
use App\Models\Exam;
use App\Models\Interview;
use App\Models\InterviewBooking;
use App\Models\InterviewerAvailability;
use App\Models\Job;
use App\Models\JobCategory;
use App\Models\Offer;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Role;
use App\Models\SalaryRecord;
use App\Models\Skill;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Ensure basic lookups exist
        $this->call([
            RoleSeeder::class,
            PermissionSeeder::class,
            SuperAdminSeeder::class,
            JobCategorySeeder::class,
            SkillSeeder::class,
            CountrySeeder::class,
            SalaryRecordSeeder::class,
        ]);

        $egypt = Country::where('code', 'EG')->first();
        $itCategory = JobCategory::where('name', 'IT & Software')->first();
        $marketingCat = JobCategory::where('name', 'Marketing')->first();

        $phpSkill = Skill::where('name', 'PHP')->first();
        $laravelSkill = Skill::where('name', 'Laravel')->first();
        $reactSkill = Skill::where('name', 'React')->first();
        $jsSkill = Skill::where('name', 'JavaScript')->first();
        $mysqlSkill = Skill::where('name', 'MySQL')->first();
        $dockerSkill = Skill::where('name', 'Docker')->first();

        // 2. Seed Approved Company & Employer User
        $employerUser = User::firstOrCreate(
            ['email' => 'company@example.com'],
            [
                'name'              => 'Karim Omar (HR Director)',
                'password'          => Hash::make('Password123!'),
                'account_type'      => 'company',
                'account_status_id' => AccountStatus::ACTIVE,
                'email_verified_at' => now(),
                'country_id'        => $egypt?->id,
            ]
        );

        $company = Company::firstOrCreate(
            ['name' => 'TechSolutions Egypt'],
            [
                'industry'          => 'Software Development & Cloud Solutions',
                'website'           => 'https://techsolutions.eg',
                'contact_email'     => 'contact@techsolutions.eg',
                'contact_phone'     => '+201001122334',
                'address'           => 'Smart Village, Building B12, Giza, Egypt',
                'status'            => 'approved',
                'account_status_id' => AccountStatus::ACTIVE,
                'owner_user_id'     => $employerUser->id,
            ]
        );

        $companyAdminRole = Role::where('name', 'company_admin')->first();
        if ($companyAdminRole) {
            $employerUser->roles()->syncWithoutDetaching([
                $companyAdminRole->id => ['company_id' => $company->id, 'assigned_at' => now()],
            ]);
        }

        // 3. Seed Interviewer User
        $interviewerUser = User::firstOrCreate(
            ['email' => 'interviewer@example.com'],
            [
                'name'              => 'Dr. Hisham Khaled (Lead Technical Interviewer)',
                'password'          => Hash::make('Password123!'),
                'account_type'      => 'company',
                'account_status_id' => AccountStatus::ACTIVE,
                'email_verified_at' => now(),
                'country_id'        => $egypt?->id,
            ]
        );

        $interviewerRole = Role::where('name', 'interviewer')->first();

        CompanyEmployee::firstOrCreate(
            ['user_id' => $interviewerUser->id, 'company_id' => $company->id],
            [
                'requested_job_title' => 'Principal Software Architect & Lead Interviewer',
                'status'              => 'approved',
                'role_id'             => $interviewerRole?->id,
                'joined_at'           => now()->subMonths(6),
            ]
        );

        if ($interviewerRole) {
            $interviewerUser->roles()->syncWithoutDetaching([
                $interviewerRole->id => ['company_id' => $company->id, 'assigned_at' => now()],
            ]);
        }

        // 4. Seed Interviewer Availability Slots
        $slot1 = InterviewerAvailability::firstOrCreate(
            [
                'interviewer_id' => $interviewerUser->id,
                'start_at'       => Carbon::tomorrow()->setTime(10, 0, 0),
            ],
            [
                'end_at'              => Carbon::tomorrow()->setTime(12, 0, 0),
                'availability_status' => 'booked',
            ]
        );

        $slot2 = InterviewerAvailability::firstOrCreate(
            [
                'interviewer_id' => $interviewerUser->id,
                'start_at'       => Carbon::tomorrow()->setTime(14, 0, 0),
            ],
            [
                'end_at'              => Carbon::tomorrow()->setTime(16, 0, 0),
                'availability_status' => 'open',
            ]
        );

        $slot3 = InterviewerAvailability::firstOrCreate(
            [
                'interviewer_id' => $interviewerUser->id,
                'start_at'       => Carbon::now()->addDays(2)->setTime(11, 0, 0),
            ],
            [
                'end_at'              => Carbon::now()->addDays(2)->setTime(14, 0, 0),
                'availability_status' => 'open',
            ]
        );

        $slot4 = InterviewerAvailability::firstOrCreate(
            [
                'interviewer_id' => $interviewerUser->id,
                'start_at'       => Carbon::now()->addDays(4)->setTime(9, 0, 0),
            ],
            [
                'end_at'              => Carbon::now()->addDays(4)->setTime(13, 0, 0),
                'availability_status' => 'open',
            ]
        );

        // 5. Seed 3 Published Public Jobs
        $job1 = Job::firstOrCreate(
            ['company_id' => $company->id, 'title' => 'Senior Laravel & Cloud Architect'],
            [
                'category_id'      => $itCategory?->id,
                'created_by'       => $employerUser->id,
                'description'      => 'We are looking for an experienced Senior Laravel Developer with deep expertise in cloud architectures, MySQL optimization, and event-driven systems. You will lead technical design and mentor junior developers in our fast-growing engineering team.',
                'location'         => 'Smart Village, Giza, Egypt',
                'work_mode'        => 'hybrid',
                'employment_type'  => 'full_time',
                'salary_min'       => 35000,
                'salary_max'       => 55000,
                'currency'         => 'EGP',
                'salary_period'    => 'month',
                'experience_years' => 5,
                'department'       => 'Engineering',
                'openings_count'   => 2,
                'deadline'         => now()->addDays(60),
                'visibility'       => 'public',
                'status'           => 'published',
                'published_at'     => now()->subDays(2),
            ]
        );

        $job2 = Job::firstOrCreate(
            ['company_id' => $company->id, 'title' => 'Frontend React Engineer'],
            [
                'category_id'      => $itCategory?->id,
                'created_by'       => $employerUser->id,
                'description'      => 'Seeking a talented Frontend Engineer with strong expertise in React, TypeScript, and modern UI/UX principles. You will build user-facing portals for our enterprise recruitment SaaS.',
                'location'         => 'Cairo, Egypt',
                'work_mode'        => 'remote',
                'employment_type'  => 'full_time',
                'salary_min'       => 22000,
                'salary_max'       => 38000,
                'currency'         => 'EGP',
                'salary_period'    => 'month',
                'experience_years' => 3,
                'department'       => 'Engineering',
                'openings_count'   => 1,
                'deadline'         => now()->addDays(45),
                'visibility'       => 'public',
                'status'           => 'published',
                'published_at'     => now()->subDay(),
            ]
        );

        $job3 = Job::firstOrCreate(
            ['company_id' => $company->id, 'title' => 'DevOps & Site Reliability Engineer'],
            [
                'category_id'      => $itCategory?->id,
                'created_by'       => $employerUser->id,
                'description'      => 'Join our cloud infrastructure team to manage Kubernetes clusters, CI/CD pipelines, Docker containerization, and automated disaster recovery.',
                'location'         => 'Cairo, Egypt',
                'work_mode'        => 'onsite',
                'employment_type'  => 'full_time',
                'salary_min'       => 30000,
                'salary_max'       => 50000,
                'currency'         => 'EGP',
                'salary_period'    => 'month',
                'experience_years' => 4,
                'department'       => 'Infrastructure',
                'openings_count'   => 1,
                'deadline'         => now()->addDays(30),
                'visibility'       => 'public',
                'status'           => 'published',
                'published_at'     => now()->subDays(3),
            ]
        );

        // Attach skills to Job 1
        if ($phpSkill && $laravelSkill && $mysqlSkill) {
            $job1->skills()->syncWithoutDetaching([
                $phpSkill->id     => ['is_required' => true],
                $laravelSkill->id => ['is_required' => true],
                $mysqlSkill->id   => ['is_required' => true],
            ]);
        }

        // Attach skills to Job 2
        if ($reactSkill && $jsSkill) {
            $job2->skills()->syncWithoutDetaching([
                $reactSkill->id => ['is_required' => true],
                $jsSkill->id    => ['is_required' => true],
            ]);
        }

        // 6. Seed Technical Assessment Exam for Job 1
        $exam = Exam::firstOrCreate(
            ['company_id' => $company->id, 'title' => 'PHP & Laravel Core Assessment'],
            [
                'created_by'     => $employerUser->id,
                'time_limit_min' => 45,
                'passing_score'  => 70,
                'attempt_limit'  => 3,
                'status'         => 'published',
            ]
        );

        // Attach Exam to Job 1
        DB::table('job_exams')->updateOrInsert(
            ['job_id' => $job1->id, 'exam_id' => $exam->id],
            ['stage_order' => 1, 'created_at' => now(), 'updated_at' => now()]
        );

        // Questions for the Exam
        $q1 = Question::firstOrCreate(
            ['exam_id' => $exam->id, 'order_no' => 1],
            [
                'question_type' => 'mcq',
                'content'       => 'What is the primary role of a Service Provider in Laravel?',
                'points'        => 25,
                'status'        => 'published',
            ]
        );
        QuestionOption::firstOrCreate(['question_id' => $q1->id, 'option_text' => 'To bind classes and services into the IoC Service Container'], ['is_correct' => true]);
        QuestionOption::firstOrCreate(['question_id' => $q1->id, 'option_text' => 'To handle Blade HTML templating engine output'], ['is_correct' => false]);
        QuestionOption::firstOrCreate(['question_id' => $q1->id, 'option_text' => 'To manage browser session storage and cookies'], ['is_correct' => false]);

        $q2 = Question::firstOrCreate(
            ['exam_id' => $exam->id, 'order_no' => 2],
            [
                'question_type' => 'mcq',
                'content'       => 'Which HTTP status code is returned when a resource conflict occurs (e.g., duplicate application)?',
                'points'        => 25,
                'status'        => 'published',
            ]
        );
        QuestionOption::firstOrCreate(['question_id' => $q2->id, 'option_text' => '409 Conflict'], ['is_correct' => true]);
        QuestionOption::firstOrCreate(['question_id' => $q2->id, 'option_text' => '400 Bad Request'], ['is_correct' => false]);
        QuestionOption::firstOrCreate(['question_id' => $q2->id, 'option_text' => '422 Unprocessable Content'], ['is_correct' => false]);

        $q3 = Question::firstOrCreate(
            ['exam_id' => $exam->id, 'order_no' => 3],
            [
                'question_type' => 'mcq',
                'content'       => 'In Eloquent ORM, what problem is solved by using eager loading with with()?',
                'points'        => 25,
                'status'        => 'published',
            ]
        );
        QuestionOption::firstOrCreate(['question_id' => $q3->id, 'option_text' => 'The N+1 query problem by executing a single WHERE IN query'], ['is_correct' => true]);
        QuestionOption::firstOrCreate(['question_id' => $q3->id, 'option_text' => 'Preventing Cross-Site Scripting (XSS) in views'], ['is_correct' => false]);
        QuestionOption::firstOrCreate(['question_id' => $q3->id, 'option_text' => 'Memory leakage during file uploads'], ['is_correct' => false]);

        $q4 = Question::firstOrCreate(
            ['exam_id' => $exam->id, 'order_no' => 4],
            [
                'question_type' => 'mcq',
                'content'       => 'What is the primary architectural purpose of Laravel Form Requests?',
                'points'        => 25,
                'status'        => 'published',
            ]
        );
        QuestionOption::firstOrCreate(['question_id' => $q4->id, 'option_text' => 'To encapsulate validation and authorization rules outside controller methods'], ['is_correct' => true]);
        QuestionOption::firstOrCreate(['question_id' => $q4->id, 'option_text' => 'To create database schema migrations automatically'], ['is_correct' => false]);
        QuestionOption::firstOrCreate(['question_id' => $q4->id, 'option_text' => 'To send outgoing emails through SMTP queues'], ['is_correct' => false]);

        // 7. Seed Candidate User & Rich Profile
        $candidateUser = User::firstOrCreate(
            ['email' => 'candidate@example.com'],
            [
                'name'              => 'Ahmed Mostafa',
                'password'          => Hash::make('Password123!'),
                'account_type'      => 'candidate',
                'account_status_id' => AccountStatus::ACTIVE,
                'email_verified_at' => now(),
                'country_id'        => $egypt?->id,
            ]
        );

        $candidateRole = Role::where('name', 'candidate')->first();
        if ($candidateRole) {
            $candidateUser->roles()->syncWithoutDetaching([
                $candidateRole->id => ['assigned_at' => now()],
            ]);
        }

        $profile = CandidateProfile::firstOrCreate(
            ['user_id' => $candidateUser->id],
            [
                'headline'          => 'Full Stack Software Engineer (Laravel / React / Cloud)',
                'bio'               => 'Passionate software engineer with 4+ years building high-throughput APIs, clean microservice architectures, and modern responsive frontends. Experienced with Laravel, React, Docker, and MySQL tuning.',
                'phone'             => '+201112233445',
                'location'          => 'Cairo, Egypt',
                'years_experience'  => 4,
                'has_no_experience' => false,
            ]
        );

        // Candidate Experience
        CandidateExperience::firstOrCreate(
            ['candidate_id' => $profile->id, 'company_name' => 'CloudScale MENA'],
            [
                'job_title'    => 'Senior Backend Engineer',
                'start_date'   => '2023-01-01',
                'end_date'     => null,
                'is_current'   => true,
                'place'        => 'Cairo, Egypt',
                'description'  => 'Led backend API development for high-traffic financial services, optimized MySQL queries, implemented Redis caching.',
            ]
        );

        CandidateExperience::firstOrCreate(
            ['candidate_id' => $profile->id, 'company_name' => 'Digital Pioneers'],
            [
                'job_title'    => 'Full Stack Developer',
                'start_date'   => '2021-07-01',
                'end_date'     => '2022-12-31',
                'is_current'   => false,
                'place'        => 'Cairo, Egypt',
                'description'  => 'Developed web applications using Laravel, Vue.js, and PostgreSQL. Wrote automated feature tests.',
            ]
        );

        // Candidate Education
        CandidateEducation::firstOrCreate(
            ['candidate_id' => $profile->id, 'institution' => 'Cairo University'],
            [
                'degree'          => 'B.Sc. in Computer Science',
                'field_of_study'  => 'Software Engineering & Information Systems',
                'graduation_year' => 2021,
                'place'           => 'Giza, Egypt',
            ]
        );

        // Candidate CV
        $cv = CandidateCv::firstOrCreate(
            ['candidate_id' => $profile->id, 'version' => 1],
            [
                'storage_path'      => 'cvs/ahmed_mostafa_resume_2026.pdf',
                'original_filename' => 'Ahmed_Mostafa_Resume_2026.pdf',
                'mime_type'         => 'application/pdf',
                'size_bytes'        => 185420,
                'is_active'         => true,
            ]
        );

        // 8. Seed Application for Ahmed on Job 1
        $application = Application::firstOrCreate(
            ['job_id' => $job1->id, 'candidate_id' => $profile->id, 'attempt_no' => 1],
            [
                'cv_id'        => $cv->id,
                'cover_note'   => 'I am thrilled to apply for the Senior Laravel & Cloud Architect role. Having 4+ years architecting microservices and optimizing database layers, I am confident I will bring tremendous value to TechSolutions Egypt.',
                'status'       => 'interviewing',
                'applied_at'   => now()->subDays(5),
                'consent_at'   => now()->subDays(5),
            ]
        );

        // Application Stage History
        ApplicationStatusHistory::firstOrCreate(
            ['application_id' => $application->id, 'to_status' => 'submitted'],
            [
                'from_status' => null,
                'changed_by'  => $candidateUser->id,
                'reason'      => 'Application submitted by candidate with consent.',
                'created_at'  => now()->subDays(5),
            ]
        );

        ApplicationStatusHistory::firstOrCreate(
            ['application_id' => $application->id, 'to_status' => 'shortlisted'],
            [
                'from_status' => 'submitted',
                'changed_by'  => $employerUser->id,
                'reason'      => 'Strong technical profile matching core requirements.',
                'created_at'  => now()->subDays(3),
            ]
        );

        ApplicationStatusHistory::firstOrCreate(
            ['application_id' => $application->id, 'to_status' => 'interviewing'],
            [
                'from_status' => 'shortlisted',
                'changed_by'  => $employerUser->id,
                'reason'      => 'Moving candidate to technical interview round.',
                'created_at'  => now()->subDays(1),
            ]
        );

        // 9. Seed Scheduled Interview for Ahmed
        $interview = Interview::firstOrCreate(
            ['application_id' => $application->id, 'round_no' => 1],
            [
                'interview_type' => 'online',
                'status'         => 'scheduled',
                'meeting_link'   => 'https://meet.google.com/abc-defg-hij',
                'instructions'   => 'Technical interview covering Laravel architecture, system design, and database indexing.',
                'created_by'     => $employerUser->id,
            ]
        );

        InterviewBooking::firstOrCreate(
            ['interview_id' => $interview->id, 'availability_id' => $slot1->id],
            [
                'interviewer_id' => $interviewerUser->id,
                'start_at'       => $slot1->start_at,
                'end_at'         => $slot1->end_at,
                'booking_status' => 'confirmed',
            ]
        );

        // 10. Seed Extended Job Offer for Ahmed
        Offer::firstOrCreate(
            ['application_id' => $application->id],
            [
                'salary_amount' => 45000.00,
                'currency'      => 'EGP',
                'salary_period' => 'month',
                'start_date'    => Carbon::now()->addDays(14)->toDateString(),
                'status'        => 'sent',
                'sent_by'       => $employerUser->id,
                'sent_at'       => now()->subDay(),
                'expires_at'    => Carbon::now()->addDays(7),
            ]
        );

        // 11. Seed a Pending Company (to test the 403 pending check)
        $pendingUser = User::firstOrCreate(
            ['email' => 'pending@company.com'],
            [
                'name'              => 'Samir Nabil (Pending Company Founder)',
                'password'          => Hash::make('Password123!'),
                'account_type'      => 'company',
                'account_status_id' => AccountStatus::ACTIVE,
            ]
        );

        Company::firstOrCreate(
            ['name' => 'StartUp Innovate (Pending Approval)'],
            [
                'industry'          => 'Fintech',
                'status'            => 'pending',
                'account_status_id' => AccountStatus::ACTIVE,
                'owner_user_id'     => $pendingUser->id,
            ]
        );
    }
}
