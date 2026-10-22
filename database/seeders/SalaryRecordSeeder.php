<?php

namespace Database\Seeders;

use App\Models\JobCategory;
use App\Models\SalaryRecord;
use Illuminate\Database\Seeder;

class SalaryRecordSeeder extends Seeder
{
    public function run(): void
    {
        $itCategory = JobCategory::where('name', 'IT & Software')->first();
        $categoryId = $itCategory?->id;

        $benchmarks = [
            [
                'job_title' => 'Junior Full Stack Developer',
                'category_id' => $categoryId,
                'location' => 'Cairo',
                'currency' => 'EGP',
                'amount_min' => 12000,
                'amount_max' => 18000,
                'experience_level' => 'junior',
                'source' => 'market_benchmark_2026',
            ],
            [
                'job_title' => 'Mid-level Backend Engineer (Laravel/PHP)',
                'category_id' => $categoryId,
                'location' => 'Cairo',
                'currency' => 'EGP',
                'amount_min' => 20000,
                'amount_max' => 32000,
                'experience_level' => 'mid',
                'source' => 'market_benchmark_2026',
            ],
            [
                'job_title' => 'Senior Backend Engineer (Laravel/PHP)',
                'category_id' => $categoryId,
                'location' => 'Cairo',
                'currency' => 'EGP',
                'amount_min' => 35000,
                'amount_max' => 60000,
                'experience_level' => 'senior',
                'source' => 'market_benchmark_2026',
            ],
            [
                'job_title' => 'Frontend Engineer (React/Vue)',
                'category_id' => $categoryId,
                'location' => 'Cairo',
                'currency' => 'EGP',
                'amount_min' => 18000,
                'amount_max' => 35000,
                'experience_level' => 'mid',
                'source' => 'market_benchmark_2026',
            ],
            [
                'job_title' => 'DevOps Engineer',
                'category_id' => $categoryId,
                'location' => 'Remote / Egypt',
                'currency' => 'EGP',
                'amount_min' => 40000,
                'amount_max' => 70000,
                'experience_level' => 'senior',
                'source' => 'market_benchmark_2026',
            ],
        ];

        foreach ($benchmarks as $b) {
            SalaryRecord::firstOrCreate(
                ['job_title' => $b['job_title'], 'experience_level' => $b['experience_level']],
                $b
            );
        }
    }
}

