<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historical transition migration.
 *
 * The old admins table is removed here and recreated by the current
 * platform-admin migration with isolated roles and permissions.
 * Keep this migration because it is part of the executed migration history.
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

