<?php

use App\Http\Controllers\Api\Admin\AdminAnalyticsController;
use App\Http\Controllers\Api\Admin\AdminAuthController;
use App\Http\Controllers\Api\Admin\AuditLogController;
use App\Http\Controllers\Api\Admin\CompanyDetailsController;
use App\Http\Controllers\Api\Admin\CompanyManagementController;
use App\Http\Controllers\Api\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\Admin\UserManagementController;
use App\Http\Controllers\Api\Application\CandidateApplicationController;
use App\Http\Controllers\Api\Application\CandidateApplicationDraftController;
use App\Http\Controllers\Api\Application\CompanyApplicationController;
use App\Http\Controllers\Api\Auth\UserAuthController;
use App\Http\Controllers\Api\Candidate\CandidateCvController;
use App\Http\Controllers\Api\Candidate\CandidateDashboardController;
use App\Http\Controllers\Api\Candidate\CandidateEducationController;
use App\Http\Controllers\Api\Candidate\CandidateExperienceController;
use App\Http\Controllers\Api\Candidate\CandidateHiddenJobController;
use App\Http\Controllers\Api\Candidate\CandidateProfileController;
use App\Http\Controllers\Api\Candidate\CandidateProfilePreviewController;
use App\Http\Controllers\Api\Candidate\CandidateRecommendedJobController;
use App\Http\Controllers\Api\Candidate\CandidateSavedJobController;
use App\Http\Controllers\Api\Candidate\CandidateSavedSearchController;
use App\Http\Controllers\Api\Candidate\CandidateSettingsController;
use App\Http\Controllers\Api\Candidate\CandidateSkillController;
use App\Http\Controllers\Api\CandidateAvailableSlotsController;
use App\Http\Controllers\Api\CandidateInterviewController;
use App\Http\Controllers\Api\Company\CompanyEmployeeController;
use App\Http\Controllers\Api\Company\CompanyHrController;
use App\Http\Controllers\Api\Company\CompanyInterviewController;
use App\Http\Controllers\Api\Company\CompanyJobController;
use App\Http\Controllers\Api\Company\CompanyProfileController;
use App\Http\Controllers\Api\Company\JobInterviewRoundController;
use App\Http\Controllers\Api\ContactMessageController;
use App\Http\Controllers\Api\Exam\CandidateExamController;
use App\Http\Controllers\Api\Exam\CompanyExamController;
use App\Http\Controllers\Api\InterviewerAvailabilityController;
use App\Http\Controllers\Api\InterviewerInterviewController;
use App\Http\Controllers\Api\InterviewEvaluationController;
use App\Http\Controllers\Api\InterviewSchedulingController;
use App\Http\Controllers\Api\JobController;
use App\Http\Controllers\Api\JobScreeningQuestionController;
use App\Http\Controllers\Api\LookupController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\Offer\OfferController;
use App\Http\Controllers\Api\PublicCompanyController;
use App\Http\Controllers\Api\PublicFeaturedController;
use App\Http\Controllers\Api\Salary\SalaryBenchmarkController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Company\CompanyAtsController;
/*
|--------------------------------------------------------------------------
| General / Basic Routes
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'HR Management System API is running',
    ]);
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/hello', function () {
    return response()->json([
        'message' => 'Hello World',
    ]);
});

/*
|--------------------------------------------------------------------------
| Public Job Discovery Routes
|--------------------------------------------------------------------------
*/
Route::middleware('throttle:60,1')->prefix('jobs')->name('jobs.')->group(function () {
    Route::get('/', [JobController::class, 'index'])->name('index');
    Route::get('{job}/screening-questions', [JobScreeningQuestionController::class, 'index'])->whereNumber('job')->name('screening-questions');
    Route::get('{job}', [JobController::class, 'show'])->whereNumber('job')->name('show');
});

// Public company discovery and homepage content.
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/companies', [PublicCompanyController::class, 'index'])->name('companies.index');
    Route::get('/companies/{id}/jobs', [PublicCompanyController::class, 'jobs'])->whereNumber('id')->name('companies.jobs');
    Route::get('/companies/{id}', [PublicCompanyController::class, 'show'])->whereNumber('id')->name('companies.show');
    Route::get('/public/featured', [PublicFeaturedController::class, 'index'])->name('public.featured');
});

/*
|--------------------------------------------------------------------------
| System Lookups & Taxonomy (Public)
|--------------------------------------------------------------------------
*/
Route::get('/job-categories', [LookupController::class, 'categories'])->name('lookups.categories');
Route::get('/skills', [LookupController::class, 'skills'])->name('lookups.skills');
Route::get('/countries', [LookupController::class, 'countries'])->name('lookups.countries');

/*
|--------------------------------------------------------------------------
| Salary Benchmark Route (Public)
|--------------------------------------------------------------------------
*/
Route::get('/salary-benchmark', [SalaryBenchmarkController::class, 'index'])->name('salary-benchmark');

/*
|--------------------------------------------------------------------------
| Universal User Authentication Routes (Candidates & Companies)
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->name('api.auth.')->group(function () {
    Route::post('/register/candidate', [UserAuthController::class, 'registerCandidate'])->name('register.candidate');
    Route::post('/register/company', [UserAuthController::class, 'registerCompany'])->name('register.company');
    Route::post('/register/employee', [UserAuthController::class, 'registerEmployee'])->name('register.employee');
    Route::post('/login', [UserAuthController::class, 'login'])->name('login');
    Route::post('/email/verify', [UserAuthController::class, 'verifyEmail'])->name('email.verify');
    Route::post('/email/resend', [UserAuthController::class, 'resendEmailVerification'])->name('email.resend');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [UserAuthController::class, 'logout'])->name('logout');
        Route::get('/me', [UserAuthController::class, 'me'])->name('me');
        Route::patch('/me/preferences', [UserAuthController::class, 'updatePreferences'])->name('me.preferences');
        Route::put('/change-password', [UserAuthController::class, 'changePassword'])->name('change-password');
        Route::delete('/account', [UserAuthController::class, 'deleteAccount'])->name('account.delete');
    });
});

Route::prefix('auth')->group(function () {
    Route::post('/forgot-password', [UserAuthController::class, 'forgotPassword'])->name('password.email');
    Route::post('/reset-password', [UserAuthController::class, 'resetPassword'])->name('password.reset');
});

// Compatibility reset-password path used by the company frontend.
Route::post('/company/reset-password', [UserAuthController::class, 'resetPassword'])
    ->name('api.company.password.reset');

// Contact us is public for visitors; authenticated users are identified from users.
Route::post('/contact', [ContactMessageController::class, 'store'])->name('contact.store');

// Contact messages are visible only to a dedicated platform Super Admin.
Route::prefix('super-admin')->name('super-admin.')->middleware(['auth:sanctum', 'admin', 'super-admin'])->group(function () {
    Route::get('/contact-messages', [ContactMessageController::class, 'index'])->name('contact-messages.index');
    Route::get('/contact-messages/{id}', [ContactMessageController::class, 'show'])->whereNumber('id')->name('contact-messages.show');
    Route::patch('/contact-messages/{id}/status', [ContactMessageController::class, 'updateStatus'])->whereNumber('id')->name('contact-messages.status');
    Route::delete('/contact-messages/{id}', [ContactMessageController::class, 'destroy'])->whereNumber('id')->name('contact-messages.destroy');
});

/*
|--------------------------------------------------------------------------
| Admin Authentication Routes
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->group(function () {
    Route::post('/login', [AdminAuthController::class, 'login']);
    Route::post('/forgot-password', [AdminAuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AdminAuthController::class, 'resetPassword']);

    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::post('/logout', [AdminAuthController::class, 'logout']);
        Route::get('/me', [AdminAuthController::class, 'me']);

        // Platform KPIs (Super Admin only)
        Route::middleware(['super-admin', 'permission:reports.view'])
            ->get('/dashboard', [AdminDashboardController::class, 'index'])
            ->name('admin.dashboard');
        Route::middleware(['super-admin', 'permission:reports.view'])->get('/analytics', [AdminAnalyticsController::class, 'index'])->name('admin.analytics');

        // Company review & lifecycle management (permission: companies.review)
        Route::middleware('permission:companies.review')->group(function () {
            Route::get('/companies', [CompanyManagementController::class, 'index'])->name('admin.companies.index');
            Route::get('/companies/{id}', [CompanyManagementController::class, 'show'])->name('admin.companies.show');
            Route::post('/companies/{id}/approve', [CompanyManagementController::class, 'approve'])->name('admin.companies.approve');
            Route::post('/companies/{id}/reject', [CompanyManagementController::class, 'reject'])->name('admin.companies.reject');

            // Company details, editing and documents
            Route::get('/companies/{id}/details', [CompanyDetailsController::class, 'show'])->name('admin.companies.details');
            Route::put('/companies/{id}', [CompanyDetailsController::class, 'update'])->name('admin.companies.update');
            Route::post('/companies/{id}/documents', [CompanyDetailsController::class, 'storeDocument'])->name('admin.companies.documents.store');
            Route::get('/companies/{id}/documents/{docId}/download', [CompanyDetailsController::class, 'downloadDocument'])->name('admin.companies.documents.download');
            Route::delete('/companies/{id}/documents/{docId}', [CompanyDetailsController::class, 'destroyDocument'])->name('admin.companies.documents.destroy');
        });

        // Company suspension (permission: companies.suspend)
        Route::middleware('permission:companies.suspend')->group(function () {
            Route::post('/companies/{id}/suspend', [CompanyManagementController::class, 'suspend'])->name('admin.companies.suspend');
        });

        // User management (permission: users.manage)
        Route::middleware('permission:users.manage')->group(function () {
            Route::get('/users', [UserManagementController::class, 'index'])->name('admin.users.index');
            Route::get('/users/{id}', [UserManagementController::class, 'show'])->name('admin.users.show');
            Route::patch('/users/{id}/status', [UserManagementController::class, 'updateStatus'])->name('admin.users.status');
        });

        // Roles & role assignment (permission: roles.manage)
        Route::middleware('permission:roles.manage')->group(function () {
            Route::get('/roles', [UserManagementController::class, 'roles'])->name('admin.roles.index');
            Route::post('/users/{id}/roles', [UserManagementController::class, 'assignRole'])->name('admin.users.roles.assign');
            Route::delete('/users/{id}/roles/{roleId}', [UserManagementController::class, 'removeRole'])->name('admin.users.roles.remove');
        });

        // Audit logs (permission: audit.view)
        Route::middleware('permission:audit.view')->group(function () {
            Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('admin.audit-logs.index');
        });
    });
});

/*
|--------------------------------------------------------------------------
| Protected Operational Routes (Sanctum)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum'])->group(function () {

    // Shared notifications for candidates, company users, interviewers, employees, and platform admins.
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->whereNumber('id')->name('notifications.read');

    // 1. Candidate Portal
    Route::prefix('candidate')->name('api.candidate.')->group(function () {
        Route::get('/dashboard', [CandidateDashboardController::class, 'index'])->name('dashboard');

        // Profile & Password
        Route::get('/profile', [CandidateProfileController::class, 'show'])->name('profile.show');
        Route::put('/profile', [CandidateProfileController::class, 'update'])->name('profile.update');
        Route::get('/profile/preview', [CandidateProfilePreviewController::class, 'show'])->name('profile.preview');
        Route::post('/profile/photo', [CandidateProfileController::class, 'uploadPhoto'])->name('profile.photo.upload');
        Route::delete('/profile/photo', [CandidateProfileController::class, 'deletePhoto'])->name('profile.photo.delete');
        Route::put('/change-password', [UserAuthController::class, 'changePassword'])->name('change-password');
        Route::delete('/account', [UserAuthController::class, 'deleteAccount'])->name('account.delete');

        // Notifications
        Route::get('/notifications', [CandidateProfileController::class, 'notifications'])->name('notifications.index');
        Route::patch('/notifications/read-all', [CandidateProfileController::class, 'markAllNotificationsAsRead'])->name('notifications.read-all');
        Route::patch('/notifications/{id}/read', [CandidateProfileController::class, 'markNotificationAsRead'])->name('notifications.read');

        // Skills Management
        Route::get('/skills', [CandidateSkillController::class, 'index'])->name('skills.index');
        Route::post('/skills', [CandidateSkillController::class, 'sync'])->name('skills.sync');

        // CV Management
        Route::get('/cvs', [CandidateCvController::class, 'index'])->name('cvs.index');
        Route::post('/cvs', [CandidateCvController::class, 'store'])->name('cvs.store');
        Route::patch('/cvs/{id}/set-active', [CandidateCvController::class, 'setActive'])->name('cvs.set-active');
        Route::delete('/cvs/{id}', [CandidateCvController::class, 'destroy'])->name('cvs.destroy');

        // Experiences & Educations
        Route::get('/experiences', [CandidateExperienceController::class, 'index'])->name('experiences.index');
        Route::post('/experiences', [CandidateExperienceController::class, 'store'])->name('experiences.store');
        Route::put('/experiences/{id}', [CandidateExperienceController::class, 'update'])->name('experiences.update');
        Route::delete('/experiences/{id}', [CandidateExperienceController::class, 'destroy'])->name('experiences.destroy');

        Route::get('/educations', [CandidateEducationController::class, 'index'])->name('educations.index');
        Route::post('/educations', [CandidateEducationController::class, 'store'])->name('educations.store');
        Route::put('/educations/{id}', [CandidateEducationController::class, 'update'])->name('educations.update');
        Route::delete('/educations/{id}', [CandidateEducationController::class, 'destroy'])->name('educations.destroy');

        // Onboarding, job preferences, notification preferences, and privacy
        Route::get('/onboarding', [CandidateSettingsController::class, 'onboarding'])->name('onboarding.show');
        Route::put('/onboarding', [CandidateSettingsController::class, 'updateOnboarding'])->name('onboarding.update');
        Route::get('/preferences', [CandidateSettingsController::class, 'preferences'])->name('preferences.show');
        Route::put('/preferences', [CandidateSettingsController::class, 'updatePreferences'])->name('preferences.update');
        Route::get('/notification-settings', [CandidateSettingsController::class, 'notificationSettings'])->name('notification-settings.show');
        Route::put('/notification-settings', [CandidateSettingsController::class, 'updateNotificationSettings'])->name('notification-settings.update');
        Route::get('/privacy', [CandidateSettingsController::class, 'privacy'])->name('privacy.show');
        Route::put('/privacy', [CandidateSettingsController::class, 'updatePrivacy'])->name('privacy.update');

        // Applications
        Route::get('/applications', [CandidateApplicationController::class, 'index'])->name('applications.index');
        Route::get('/applications/{id}/activity', [CandidateApplicationController::class, 'activity'])->whereNumber('id')->name('applications.activity');
        Route::get('/applications/{id}', [CandidateApplicationController::class, 'show'])->whereNumber('id')->name('applications.show');
        Route::delete('/applications/{id}/withdraw', [CandidateApplicationController::class, 'withdraw'])->name('applications.withdraw');

        // Application drafts
        Route::get('/application-drafts/{jobId}', [CandidateApplicationDraftController::class, 'show'])->whereNumber('jobId')->name('application-drafts.show');
        Route::put('/application-drafts/{jobId}', [CandidateApplicationDraftController::class, 'update'])->whereNumber('jobId')->name('application-drafts.update');

        // Recommended Jobs (#12)
        Route::get('/recommended-jobs', [CandidateRecommendedJobController::class, 'index'])->name('recommended-jobs.index');

        // Saved Jobs (#13, #14, #15)
        Route::get('/saved-jobs', [CandidateSavedJobController::class, 'index'])->name('saved-jobs.index');
        Route::post('/saved-jobs/{jobId}', [CandidateSavedJobController::class, 'store'])->whereNumber('jobId')->name('saved-jobs.store');
        Route::delete('/saved-jobs/{jobId}', [CandidateSavedJobController::class, 'destroy'])->whereNumber('jobId')->name('saved-jobs.destroy');

        // Saved searches and recommendation controls
        Route::get('/saved-searches', [CandidateSavedSearchController::class, 'index'])->name('saved-searches.index');
        Route::post('/saved-searches', [CandidateSavedSearchController::class, 'store'])->name('saved-searches.store');
        Route::put('/saved-searches/{id}', [CandidateSavedSearchController::class, 'update'])->whereNumber('id')->name('saved-searches.update');
        Route::delete('/saved-searches/{id}', [CandidateSavedSearchController::class, 'destroy'])->whereNumber('id')->name('saved-searches.destroy');
        Route::post('/recommended-jobs/{jobId}/hide', [CandidateHiddenJobController::class, 'store'])->whereNumber('jobId')->name('recommended-jobs.hide');

        // Available Interview Slots & Interviews
        Route::get('/available-slots', [CandidateAvailableSlotsController::class, 'index'])->name('available-slots');
        Route::get('/interviews', [CandidateInterviewController::class, 'index'])->name('interviews.index');
        Route::post('/interviews/book', [CandidateInterviewController::class, 'book'])->name('interviews.book');
        Route::get('/interviews/{id}', [CandidateInterviewController::class, 'show'])->name('interviews.show');
        Route::post('/interviews/{id}/reschedule', [CandidateInterviewController::class, 'reschedule'])->name('interviews.reschedule');
        Route::post('/interviews/{id}/cancel', [CandidateInterviewController::class, 'cancel'])->name('interviews.cancel');

        // Exam Attempts
        Route::get('/exams', [CandidateExamController::class, 'index'])->name('exams.index');
        Route::post('/exams/{examId}/applications/{applicationId}/start', [CandidateExamController::class, 'start'])->name('exams.start');
        Route::post('/exam-attempts/{attemptId}/submit', [CandidateExamController::class, 'submit'])->name('exam-attempts.submit');
        Route::get('/exam-attempts/{attemptId}/result', [CandidateExamController::class, 'result'])->name('exam-attempts.result');

        // Offers
        Route::get('/offers', [OfferController::class, 'candidateOffers'])->name('offers.index');
        Route::post('/offers/{id}/accept', [OfferController::class, 'accept'])->name('offers.accept');
        Route::post('/offers/{id}/reject', [OfferController::class, 'reject'])->name('offers.reject');
        Route::post('/offers/{id}/question', [CompanyHrController::class, 'askOfferQuestion'])->name('offers.question');
    });

    // 2. Candidate Job Application Action
    Route::post('/jobs/{jobId}/apply', [CandidateApplicationController::class, 'apply'])->name('jobs.apply');

    // 3. Interviewer Endpoints
    Route::prefix('interviewer')->name('api.interviewer.')->group(function () {
        Route::get('/interviews', [InterviewerInterviewController::class, 'index'])->name('interviews.index');
        Route::get('/interviews/{id}', [InterviewerInterviewController::class, 'show'])->name('interviews.show');

        // Availability CRUD
        Route::get('/availabilities', [InterviewerAvailabilityController::class, 'index'])->name('availabilities.index');
        Route::post('/availabilities', [InterviewerAvailabilityController::class, 'store'])->name('availabilities.store');
        Route::get('/availabilities/{id}', [InterviewerAvailabilityController::class, 'show'])->name('availabilities.show');
        Route::put('/availabilities/{id}', [InterviewerAvailabilityController::class, 'update'])->name('availabilities.update');
        Route::patch('/availabilities/{id}', [InterviewerAvailabilityController::class, 'update'])->name('availabilities.patch');
        Route::delete('/availabilities/{id}', [InterviewerAvailabilityController::class, 'destroy'])->name('availabilities.destroy');
    });

    // 4a. Company profile & documents (NOT behind company.active: a pending company must upload its papers)
    Route::prefix('company')->name('api.company.profile.')->group(function () {
        Route::put('/change-password', [UserAuthController::class, 'changePassword'])->name('change-password');
        Route::delete('/delete-account', [UserAuthController::class, 'deleteAccount'])->name('account.delete');
        Route::get('/profile', [CompanyProfileController::class, 'show'])->name('show');
        Route::put('/profile', [CompanyProfileController::class, 'update'])->name('update');
        Route::get('/documents', [CompanyProfileController::class, 'documents'])->name('documents.index');
        Route::post('/documents', [CompanyProfileController::class, 'storeDocument'])->name('documents.store');
        Route::get('/documents/{id}/download', [CompanyProfileController::class, 'downloadDocument'])->name('documents.download');
        Route::delete('/documents/{id}', [CompanyProfileController::class, 'destroyDocument'])->name('documents.destroy');
    });

    // 4. Company HR & Employer Portal
    Route::prefix('company')->name('api.company.')->middleware('company.active')->group(function () {
        Route::get('/dashboard', [CompanyHrController::class, 'dashboard'])->name('dashboard');
        // Candidate/company exam discovery and details.
        Route::get('/exams', [CompanyExamController::class, 'index'])->name('exams.index');
        Route::get('/exams/{id}', [CompanyExamController::class, 'show'])->name('exams.show');
        Route::middleware('permission:applications.review')
            ->post('/applications/{id}/ats-evaluation', [CompanyAtsController::class, 'store'])
            ->middleware('throttle:20,1')->whereNumber('id')->name('applications.ats.store');

        Route::middleware('permission:applications.view')
            ->get('/applications/{id}/ats-evaluation', [CompanyAtsController::class, 'show'])
            ->whereNumber('id')->name('applications.ats.show');

        Route::middleware('permission:applications.override_ai')
            ->post('/applications/{id}/ats-evaluation/decision', [CompanyAtsController::class, 'decide'])
            ->whereNumber('id')->name('applications.ats.decide');

        // Employees & Interviewer Onboarding (Package 2)
        Route::post('/join-requests', [CompanyEmployeeController::class, 'storeJoinRequest'])->name('employees.join');

        Route::middleware('permission:employees.view')->group(function () {
            Route::get('/employees', [CompanyEmployeeController::class, 'index'])->name('employees.index');
            Route::get('/employees/{id}', [CompanyEmployeeController::class, 'show'])->name('employees.show');
        });

        Route::middleware('permission:employees.approve')->group(function () {
            Route::post('/employees/{id}/approve', [CompanyEmployeeController::class, 'approve'])->name('employees.approve');
            Route::post('/employees/{id}/reject', [CompanyEmployeeController::class, 'reject'])->name('employees.reject');
        });

        Route::middleware('permission:employees.manage')->group(function () {
            Route::match(['put', 'patch'], '/employees/{id}', [CompanyEmployeeController::class, 'update'])->name('employees.update');
            Route::delete('/employees/{id}', [CompanyEmployeeController::class, 'destroy'])->name('employees.destroy');
        });

        // Jobs Management
        Route::get('/jobs', [CompanyJobController::class, 'index'])->name('jobs.index');
        Route::post('/jobs', [CompanyJobController::class, 'store'])->name('jobs.store');
        Route::get('/jobs/{id}', [CompanyJobController::class, 'show'])->name('jobs.show');
        Route::put('/jobs/{id}', [CompanyJobController::class, 'update'])->name('jobs.update');
        Route::post('/jobs/{id}/publish', [CompanyHrController::class, 'publish'])->name('jobs.publish');
        Route::get('/jobs/{id}/publication-checklist', [CompanyHrController::class, 'publicationChecklist'])->name('jobs.publication-checklist');
        Route::get('/jobs/{id}/overview', [CompanyHrController::class, 'overview'])->name('jobs.overview');
        Route::get('/jobs/{id}/hiring-team', [CompanyHrController::class, 'hiringTeam'])->name('jobs.hiring-team.index');
        Route::post('/jobs/{id}/hiring-team', [CompanyHrController::class, 'addHiringTeam'])->name('jobs.hiring-team.store');
        Route::delete('/jobs/{id}/hiring-team/{userId}', [CompanyHrController::class, 'removeHiringTeam'])->name('jobs.hiring-team.destroy');
        Route::patch('/jobs/{id}/status', [CompanyJobController::class, 'changeStatus'])->name('jobs.change-status');
        Route::delete('/jobs/{id}', [CompanyJobController::class, 'destroy'])->name('jobs.destroy');

        // Published private jobs for approved members of this company only
        Route::get('/internal-jobs', [CompanyJobController::class, 'internalIndex'])->name('internal-jobs.index');
        Route::get('/internal-jobs/{id}', [CompanyJobController::class, 'internalShow'])->name('internal-jobs.show');

        // Job Interview Rounds CRUD (Package 3)
        Route::middleware('permission:interviews.assign')->group(function () {
            Route::get('/jobs/{job}/interview-rounds', [JobInterviewRoundController::class, 'index'])->name('jobs.interview-rounds.index');
            Route::post('/jobs/{job}/interview-rounds', [JobInterviewRoundController::class, 'store'])->name('jobs.interview-rounds.store');
            Route::get('/jobs/{job}/interview-rounds/{round}', [JobInterviewRoundController::class, 'show'])->name('jobs.interview-rounds.show');
            Route::put('/jobs/{job}/interview-rounds/{round}', [JobInterviewRoundController::class, 'update'])->name('jobs.interview-rounds.update');
            Route::delete('/jobs/{job}/interview-rounds/{round}', [JobInterviewRoundController::class, 'destroy'])->name('jobs.interview-rounds.destroy');
        });

        // Company Interviews Management (Package 3)
        Route::post('/applications/{id}/interviews', [CompanyInterviewController::class, 'store'])->name('interviews.store');
        Route::get('/applications/{id}/interviews', [CompanyInterviewController::class, 'applicationInterviews'])->name('applications.interviews.index');
        Route::get('/interviews', [CompanyInterviewController::class, 'index'])->name('interviews.index');
        Route::get('/interviews/calendar', [CompanyHrController::class, 'calendar'])->name('interviews.calendar');
        Route::post('/interviews/check-conflicts', [CompanyHrController::class, 'checkConflicts'])->name('interviews.check-conflicts');
        Route::put('/interviews/{id}', [CompanyHrController::class, 'updateInterview'])->name('interviews.update');
        Route::post('/interviews/{id}/cancel', [CompanyInterviewController::class, 'cancel'])->name('interviews.cancel');

        // Applications Management (ATS Pipeline)
        Route::get('/jobs/{jobId}/applications', [CompanyApplicationController::class, 'index'])->name('applications.index');
        Route::post('/jobs/{jobId}/applications', [CompanyHrController::class, 'addApplication'])->name('applications.store');
        Route::get('/jobs/{jobId}/applications/export', [CompanyHrController::class, 'exportApplications'])->name('applications.export');
        Route::patch('/applications/bulk', [CompanyHrController::class, 'bulkApplications'])->name('applications.bulk');
        Route::get('/applications/{id}', [CompanyApplicationController::class, 'show'])->name('applications.show');
        Route::get('/applications/{id}/notes', [CompanyHrController::class, 'notes'])->name('applications.notes.index');
        Route::post('/applications/{id}/notes', [CompanyHrController::class, 'notes'])->name('applications.notes.store');
        Route::patch('/applications/{id}/assign', [CompanyHrController::class, 'assignApplication'])->name('applications.assign');
        Route::get('/applications/{id}/available-slots', [CompanyHrController::class, 'availableSlots'])->name('applications.available-slots');
        Route::get('/applications/{id}/evaluations', [CompanyHrController::class, 'evaluations'])->name('applications.evaluations');
        Route::post('/applications/{id}/decision', [CompanyHrController::class, 'decision'])->name('applications.decision.store');
        Route::patch('/applications/{id}/decision', [CompanyHrController::class, 'decision'])->name('applications.decision.update');
        Route::patch('/applications/{id}/status', [CompanyApplicationController::class, 'updateStatus'])->name('applications.update-status');

        // Exams Management
        Route::post('/exams', [CompanyExamController::class, 'store'])->name('exams.store');
        Route::post('/exams/{id}/questions', [CompanyExamController::class, 'addQuestion'])->name('exams.add-question');
        Route::post('/jobs/{jobId}/exams/{examId}/attach', [CompanyExamController::class, 'attachToJob'])->name('exams.attach');

        // Offers Management
        Route::post('/offers', [OfferController::class, 'store'])->name('offers.store');
        Route::post('/offers/{id}/request-approval', [CompanyHrController::class, 'offerAction'])->defaults('action', 'request-approval')->name('offers.request-approval');
        Route::post('/offers/{id}/approve', [CompanyHrController::class, 'offerAction'])->defaults('action', 'approve')->name('offers.approve');
        Route::post('/offers/{id}/send', [CompanyHrController::class, 'offerAction'])->defaults('action', 'send')->name('offers.send');
        Route::get('/offer-templates', [CompanyHrController::class, 'offerTemplates'])->name('offer-templates.index');
        Route::get('/talent-pool', [CompanyHrController::class, 'talentPool'])->name('talent-pool.index');
        Route::post('/talent-pool', [CompanyHrController::class, 'talentPool'])->name('talent-pool.store');
        Route::patch('/talent-pool/{id}', [CompanyHrController::class, 'talentPoolItem'])->name('talent-pool.update');
        Route::patch('/talent-pool/{id}/tags', [CompanyHrController::class, 'talentPoolItem'])->name('talent-pool.tags.update');
        Route::delete('/talent-pool/{id}', [CompanyHrController::class, 'talentPoolItem'])->name('talent-pool.destroy');
        Route::get('/analytics', [CompanyHrController::class, 'analytics'])->name('analytics.index');
        Route::get('/analytics/export', [CompanyHrController::class, 'analyticsExport'])->name('analytics.export');
        Route::get('/invitations', [CompanyHrController::class, 'invitations'])->name('invitations.index');
        Route::post('/invitations', [CompanyHrController::class, 'invitations'])->name('invitations.store');
        Route::delete('/invitations/{id}', [CompanyHrController::class, 'deleteInvitation'])->name('invitations.destroy');
        Route::get('/roles', [CompanyHrController::class, 'roles'])->name('roles.index');
        Route::put('/employees/{id}/role', [CompanyHrController::class, 'employeeRole'])->name('employees.role.update');
        Route::get('/workspace', [CompanyHrController::class, 'workspace'])->name('workspace.show');
        Route::put('/workspace', [CompanyHrController::class, 'workspace'])->name('workspace.update');
    });

    // 5. Shared Offer Details
    Route::get('/offers/{id}', [OfferController::class, 'show'])->name('offers.show');
    Route::get('/offers/{id}/documents', [CompanyHrController::class, 'offerDocuments'])->name('offers.documents.index');
    Route::post('/offers/{id}/documents', [CompanyHrController::class, 'offerDocuments'])->name('offers.documents.store');
    Route::post('/offers/{id}/sign', [CompanyHrController::class, 'signOffer'])->name('offers.sign');

    // 6. Interview Scheduling & Evaluations (HR / Admin)
    Route::prefix('interviews')->name('api.interviews.')->group(function () {
        Route::post('/', [InterviewSchedulingController::class, 'store'])->name('store');
        Route::post('/{id}/reschedule', [InterviewSchedulingController::class, 'reschedule'])->name('reschedule');
        Route::post('/{id}/cancel', [InterviewSchedulingController::class, 'cancel'])->name('cancel');

        // Evaluations
        Route::get('/{id}/evaluations', [InterviewEvaluationController::class, 'index'])->name('evaluations.index');
        Route::post('/{id}/evaluations', [InterviewEvaluationController::class, 'store'])->name('evaluations.store');
        Route::post('/{id}/evaluations/{evaluationId}/publish', [InterviewEvaluationController::class, 'publish'])->name('evaluations.publish');
    });
});
