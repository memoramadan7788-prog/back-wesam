<?php

namespace Database\Seeders;

use App\Models\Skill;
use Illuminate\Database\Seeder;

class SkillSeeder extends Seeder
{
    public function run(): void
    {
        $tech = ['PHP', 'Laravel', 'JavaScript', 'React', 'Node.js', 'MySQL', 'Python', 'FastAPI', 'Docker', 'AWS', 'Git', 'REST APIs'];
        $soft = ['Communication', 'Teamwork', 'Problem Solving', 'Time Management', 'Leadership', 'Adaptability'];

        foreach ($tech as $name) {
            Skill::firstOrCreate(['name' => $name], ['type' => 'tech', 'accepted' => true]);
        }
        foreach ($soft as $name) {
            Skill::firstOrCreate(['name' => $name], ['type' => 'soft', 'accepted' => true]);
        }
    }
}
