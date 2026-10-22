<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CompanyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'          => fake()->unique()->company(),
            'website'       => fake()->url(),
            'industry'      => 'Software',
            'doc_path'      => 'private/docs/' . fake()->uuid() . '.pdf',
            'logo_path'     => 'logos/' . fake()->uuid() . '.png',
            'contact_email' => fake()->safeEmail(),
            'contact_phone' => fake()->phoneNumber(),
            'address'       => fake()->address(),
            'status'        => 'approved',
            'owner_user_id' => User::factory(),
        ];
    }
}
