<?php

namespace Tests\Feature\Setup;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_can_be_seeded_for_the_basic_recruitment_flow(): void
    {
        $exitCode = Artisan::call('db:seed', ['--force' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertFalse(Schema::hasTable('admins'));
        $this->assertDatabaseHas('users', [
            'email' => 'admin@example.com',
            'account_type' => 'admin',
        ]);
        $this->assertDatabaseHas('users', ['email' => 'company@example.com']);
        $this->assertDatabaseHas('users', ['email' => 'candidate@example.com']);
        $this->assertDatabaseHas('roles', ['name' => 'super_admin']);
        $this->assertDatabaseHas('jobs', ['title' => 'Senior Laravel & Cloud Architect']);
    }
}
