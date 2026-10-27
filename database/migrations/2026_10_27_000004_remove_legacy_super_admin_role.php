<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        $roleId = DB::table('roles')
            ->where('name', 'super_admin')
            ->whereNull('deleted_at')
            ->value('id');

        if (! $roleId) {
            return;
        }

        if (Schema::hasTable('user_roles')) {
            DB::table('user_roles')->where('role_id', $roleId)->delete();
        }

        if (Schema::hasTable('role_permissions')) {
            DB::table('role_permissions')->where('role_id', $roleId)->delete();
        }

        DB::table('roles')
            ->where('id', $roleId)
            ->update(['deleted_at' => now()]);
    }

    public function down(): void
    {
        if (Schema::hasTable('roles')) {
            DB::table('roles')
                ->where('name', 'super_admin')
                ->update(['deleted_at' => null]);
        }
    }
};
