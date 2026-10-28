<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\RoleCategory;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $company = [
            'employees.view', 'employees.approve', 'employees.manage',
            'jobs.create', 'jobs.update', 'jobs.publish', 'jobs.close', 'jobs.nominate',
            'applications.view', 'applications.review', 'applications.override_ai', 'cvs.view',
            'interviews.assign', 'interviews.conduct', 'interviews.evaluate',
            'exams.create', 'exams.manage', 'proctoring.review', 'offers.manage', 'salary.benchmark.view',
        ];

        $roles = [
            'candidate' => ['platform', 'Regular candidate', [
                'jobs.apply', 'applications.view_own', 'exams.take', 'interviews.book',
                'offers.respond', 'chatbot.candidate',
            ]],
            'company_admin' => ['company', 'Company owner / admin', $company],
            'hr' => ['company', 'HR manager', [
                'employees.view', 'jobs.create', 'jobs.update', 'jobs.publish', 'jobs.close', 'jobs.nominate',
                'applications.view', 'applications.review', 'applications.override_ai', 'cvs.view',
                'interviews.assign', 'exams.create', 'exams.manage', 'proctoring.review',
                'offers.manage', 'salary.benchmark.view',
            ]],
            'interviewer' => ['company', 'Interviewer', [
                'applications.view', 'cvs.view', 'interviews.conduct', 'interviews.evaluate',
            ]],
            'employee' => ['company', 'Regular employee (no extra permissions)', []],
        ];

        foreach ($roles as $name => [$scope, $description, $keys]) {
            $category = RoleCategory::firstOrCreate([
                'name' => $scope === 'company' ? 'company' : 'user',
            ]);

            $role = Role::updateOrCreate(
                ['name' => $name],
                [
                    'scope' => $scope,
                    'role_category_id' => $category->id,
                    'description' => $description,
                ]
            );
            $ids = Permission::whereIn('key', $keys)->pluck('id');
            $role->permissions()->sync($ids);
        }
    }
}
