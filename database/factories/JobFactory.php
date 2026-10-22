<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class JobFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id'       => Company::factory(),
            'category_id'      => Category::factory(),
            'created_by'       => User::factory(),
            'title'            => fake()->jobTitle(),
            'description'      => fake()->paragraphs(3, true),
            'location'         => 'Cairo',
            'work_mode'        => 'onsite',
            'employment_type'  => 'full_time',
            'salary_min'       => 8000,
            'salary_max'       => 15000,
            'currency'         => 'EGP',
            'salary_period'    => 'month',
            'experience_years' => 2,
            'department'       => 'Engineering',
            'openings_count'   => 1,
            'deadline'         => now()->addDays(30),
            'visibility'       => 'public',
            'status'           => 'published',
            'published_at'     => now()->subDay(),
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => 'draft', 'published_at' => null]);
    }

    public function closed(): static
    {
        return $this->state(['status' => 'closed', 'closed_at' => now()]);
    }

    public function expired(): static
    {
        return $this->state(['deadline' => now()->subDay()]);
    }

    public function privateVisibility(): static
    {
        return $this->state(['visibility' => 'private', 'private_apply_mode' => 'all_employees']);
    }
}
