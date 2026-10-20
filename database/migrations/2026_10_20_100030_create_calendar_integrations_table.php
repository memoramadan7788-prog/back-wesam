<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->enum('provider', ['google', 'outlook'])->default('google');
            $table->string('token_ref');
            $table->string('sync_status')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_integrations');
    }
};
