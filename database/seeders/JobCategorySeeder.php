<?php

namespace Database\Seeders;

use App\Models\JobCategory;
use Illuminate\Database\Seeder;

class JobCategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'IT & Software', 'Marketing', 'Finance', 'Human Resources', 'Sales',
            'Engineering', 'Design', 'Customer Support', 'Healthcare', 'Education',
        ] as $name) {
            JobCategory::firstOrCreate(['name' => $name]);
        }
    }
}
