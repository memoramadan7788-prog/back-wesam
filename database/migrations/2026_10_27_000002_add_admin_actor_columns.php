<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->foreignId('admin_id')->nullable()->after('user_id')->constrained('admins')->nullOnDelete();
            $table->index('admin_id');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->foreignId('reviewed_by_admin_id')->nullable()->after('reviewed_by')->constrained('admins')->nullOnDelete();
        });

        Schema::table('user_roles', function (Blueprint $table) {
            $table->foreignId('assigned_by_admin_id')->nullable()->after('assigned_by')->constrained('admins')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('user_roles', function (Blueprint $table) {
            $table->dropForeign(['assigned_by_admin_id']);
            $table->dropColumn('assigned_by_admin_id');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by_admin_id']);
            $table->dropColumn('reviewed_by_admin_id');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropForeign(['admin_id']);
            $table->dropIndex(['admin_id']);
            $table->dropColumn('admin_id');
        });
    }
};
