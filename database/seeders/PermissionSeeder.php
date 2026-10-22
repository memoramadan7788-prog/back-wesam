<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public const ALL = [
        // platform
        'companies.review' => 'Approve or reject company registrations',
        'companies.suspend' => 'Suspend a company',
        'roles.manage' => 'Create roles and set their permissions',
        'permissions.manage' => 'Manage the permission list',
        'users.manage' => 'Manage all platform users',
        'audit.view' => 'View audit logs',
        'reports.view' => 'View platform reports',
        'chatbot.admin' => 'Use the admin chatbot',
        // company
        'employees.view' => 'View company employees',
        'employees.approve' => 'Approve or reject join requests and assign roles',
        'employees.manage' => 'Edit or remove company employees',
        'jobs.create' => 'Create jobs',
        'jobs.update' => 'Edit jobs',
        'jobs.publish' => 'Publish jobs',
        'jobs.close' => 'Close or reopen jobs',
        'jobs.nominate' => 'Manage nomination list of private jobs',
        'applications.view' => 'View applications of the company',
        'applications.review' => 'Shortlist or reject applications',
        'applications.override_ai' => 'Override the AI CV match recommendation',
        'cvs.view' => 'View candidate CVs',
        'interviews.assign' => 'Assign interviewers to rounds',
        'interviews.conduct' => 'Conduct interviews',
        'interviews.evaluate' => 'Write interview evaluations',
        'exams.create' => 'Create exams and questions',
        'exams.manage' => 'Manage exams and attempts',
        'proctoring.review' => 'Review proctoring flags',
        'offers.manage' => 'Create and send offers',
        'salary.benchmark.view' => 'View salary benchmark and recommendations',
        // candidate
        'jobs.apply' => 'Apply to jobs',
        'applications.view_own' => 'View own applications',
        'exams.take' => 'Take exams',
        'interviews.book' => 'Book interview slots',
        'offers.respond' => 'Accept or reject offers',
        'chatbot.candidate' => 'Use the candidate chatbot',
    ];

    public function run(): void
    {
        foreach (self::ALL as $key => $description) {
            Permission::firstOrCreate(['key' => $key], ['description' => $description]);
        }
    }
}
