<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 0.5: Drop the admins table.
 *
 * Admin authentication is now handled via the `users` table with account_type='admin'.
 * The separate admins table is no longer needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('admins');
    }

    public function down(): void
    {
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });
    }
};

