<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    public function run(): void
    {
        $countries = [
            ['name' => 'Egypt', 'code' => 'EG'],
            ['name' => 'Saudi Arabia', 'code' => 'SA'],
            ['name' => 'United Arab Emirates', 'code' => 'AE'],
            ['name' => 'Qatar', 'code' => 'QA'],
            ['name' => 'Kuwait', 'code' => 'KW'],
            ['name' => 'Jordan', 'code' => 'JO'],
            ['name' => 'United States', 'code' => 'US'],
            ['name' => 'United Kingdom', 'code' => 'GB'],
            ['name' => 'Germany', 'code' => 'DE'],
            ['name' => 'Canada', 'code' => 'CA'],
        ];

        foreach ($countries as $country) {
            Country::firstOrCreate(['code' => $country['code']], ['name' => $country['name']]);
        }
    }
}

