<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->foreignId('recipient_id')->nullable()->change();
            $table->foreignId('admin_recipient_id')
                ->nullable()
                ->after('recipient_id')
                ->constrained('admins')
                ->nullOnDelete();
            $table->index('admin_recipient_id');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropForeign(['admin_recipient_id']);
            $table->dropIndex(['admin_recipient_id']);
            $table->dropColumn('admin_recipient_id');
            $table->foreignId('recipient_id')->nullable(false)->change();
        });
    }
};
