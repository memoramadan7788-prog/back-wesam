<?php

use App\Http\Controllers\Api\Admin\AuditLogController;
use App\Http\Controllers\Api\Admin\AdminAuthController;
use App\Http\Controllers\Api\Admin\CompanyManagementController;
use App\Http\Controllers\Api\Admin\UserManagementController;
use App\Http\Controllers\Api\Application\CandidateApplicationController;
use App\Http\Controllers\Api\Application\CompanyApplicationController;
use App\Http\Controllers\Api\Auth\UserAuthController;
use App\Http\Controllers\Api\Candidate\CandidateCvController;
use App\Http\Controllers\Api\Candidate\CandidateEducationController;
use App\Http\Controllers\Api\Candidate\CandidateExperienceController;
use App\Http\Controllers\Api\Candidate\CandidateProfileController;
use App\Http\Controllers\Api\Candidate\CandidateRecommendedJobController;
use App\Http\Controllers\Api\Candidate\CandidateSavedJobController;
use App\Http\Controllers\Api\Candidate\CandidateSkillController;
use App\Http\Controllers\Api\CandidateAvailableSlotsController;
use App\Http\Controllers\Api\CandidateInterviewController;
use App\Http\Controllers\Api\Company\CompanyEmployeeController;
use App\Http\Controllers\Api\Company\CompanyJobController;
use App\Http\Controllers\Api\Company\JobInterviewRoundController;
use App\Http\Controllers\Api\Company\CompanyInterviewController;
use App\Http\Controllers\Api\Exam\CandidateExamController;
use App\Http\Controllers\Api\Exam\CompanyExamController;
use App\Http\Controllers\Api\InterviewEvaluationController;
use App\Http\Controllers\Api\InterviewerAvailabilityController;
use App\Http\Controllers\Api\InterviewerInterviewController;
use App\Http\Controllers\Api\InterviewSchedulingController;
use App\Http\Controllers\Api\JobController;
use App\Http\Controllers\Api\Offer\OfferController;
use App\Http\Controllers\Api\Salary\SalaryBenchmarkController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\Admin\AdminAnalyticsController;
use App\Http\Controllers\Api\Admin\CompanyDetailsController;
use App\Http\Controllers\Api\Company\CompanyProfileController;

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
        'message' => 'Hello World'
    ]);
});

/*
|--------------------------------------------------------------------------
| Public Job Discovery Routes
|--------------------------------------------------------------------------
*/
Route::middleware('throttle:60,1')->prefix('jobs')->name('jobs.')->group(function () {
    Route::get('/', [JobController::class, 'index'])->name('index');
    Route::get('{job}', [JobController::class, 'show'])->whereNumber('job')->name('show');
});

/*
|--------------------------------------------------------------------------
| System Lookups & Taxonomy (Public)
|--------------------------------------------------------------------------
*/
Route::get('/job-categories', [\App\Http\Controllers\Api\LookupController::class, 'categories'])->name('lookups.categories');
Route::get('/skills', [\App\Http\Controllers\Api\LookupController::class, 'skills'])->name('lookups.skills');
Route::get('/countries', [\App\Http\Controllers\Api\LookupController::class, 'countries'])->name('lookups.countries');

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

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [UserAuthController::class, 'logout'])->name('logout');
        Route::get('/me', [UserAuthController::class, 'me'])->name('me');
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

    // 1. Candidate Portal
    Route::prefix('candidate')->name('api.candidate.')->group(function () {
        // Profile & Password
        Route::get('/profile', [CandidateProfileController::class, 'show'])->name('profile.show');
        Route::put('/profile', [CandidateProfileController::class, 'update'])->name('profile.update');
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
        Route::delete('/experiences/{id}', [CandidateExperienceController::class, 'destroy'])->name('experiences.destroy');

        Route::get('/educations', [CandidateEducationController::class, 'index'])->name('educations.index');
        Route::post('/educations', [CandidateEducationController::class, 'store'])->name('educations.store');
        Route::delete('/educations/{id}', [CandidateEducationController::class, 'destroy'])->name('educations.destroy');

        // Applications
        Route::get('/applications', [CandidateApplicationController::class, 'index'])->name('applications.index');
        Route::get('/applications/{id}', [CandidateApplicationController::class, 'show'])->name('applications.show');
        Route::delete('/applications/{id}/withdraw', [CandidateApplicationController::class, 'withdraw'])->name('applications.withdraw');

        // Recommended Jobs (#12)
        Route::get('/recommended-jobs', [CandidateRecommendedJobController::class, 'index'])->name('recommended-jobs.index');

        // Saved Jobs (#13, #14, #15)
        Route::get('/saved-jobs', [CandidateSavedJobController::class, 'index'])->name('saved-jobs.index');
        Route::post('/saved-jobs/{jobId}', [CandidateSavedJobController::class, 'store'])->whereNumber('jobId')->name('saved-jobs.store');
        Route::delete('/saved-jobs/{jobId}', [CandidateSavedJobController::class, 'destroy'])->whereNumber('jobId')->name('saved-jobs.destroy');

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
        // Candidate/company exam discovery and details.
        Route::get('/exams', [CompanyExamController::class, 'index'])->name('exams.index');
        Route::get('/exams/{id}', [CompanyExamController::class, 'show'])->name('exams.show');

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
        Route::post('/interviews/{id}/cancel', [CompanyInterviewController::class, 'cancel'])->name('interviews.cancel');

        // Applications Management (ATS Pipeline)
        Route::get('/jobs/{jobId}/applications', [CompanyApplicationController::class, 'index'])->name('applications.index');
        Route::get('/applications/{id}', [CompanyApplicationController::class, 'show'])->name('applications.show');
        Route::patch('/applications/{id}/status', [CompanyApplicationController::class, 'updateStatus'])->name('applications.update-status');

        // Exams Management
        Route::post('/exams', [CompanyExamController::class, 'store'])->name('exams.store');
        Route::post('/exams/{id}/questions', [CompanyExamController::class, 'addQuestion'])->name('exams.add-question');
        Route::post('/jobs/{jobId}/exams/{examId}/attach', [CompanyExamController::class, 'attachToJob'])->name('exams.attach');

        // Offers Management
        Route::post('/offers', [OfferController::class, 'store'])->name('offers.store');
    });

    // 5. Shared Offer Details
    Route::get('/offers/{id}', [OfferController::class, 'show'])->name('offers.show');

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
