<?php

namespace Tests\Feature\Setup;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use App\Models\Admin;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_can_be_seeded_for_the_basic_recruitment_flow(): void
    {
        $exitCode = Artisan::call('db:seed', ['--force' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertTrue(Schema::hasTable('admins'));
        $this->assertFalse(Schema::hasColumn('admins', 'two_factor_secret'));
        $this->assertFalse(Schema::hasColumn('admins', 'two_factor_confirmed_at'));
        $this->assertDatabaseHas('admins', [
            'email' => 'admin@example.com',
        ]);
        $this->assertDatabaseHas('users', ['email' => 'company@example.com']);
        $this->assertDatabaseHas('users', ['email' => 'candidate@example.com']);
        $this->assertDatabaseHas('admin_roles', ['name' => 'super_admin']);
        $this->assertDatabaseMissing('roles', [
            'name' => 'super_admin',
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('admin_role_assignments', [
            'admin_id' => Admin::where('email', 'admin@example.com')->value('id'),
        ]);
        $this->assertDatabaseHas('jobs', ['title' => 'Senior Laravel & Cloud Architect']);
    }
}
