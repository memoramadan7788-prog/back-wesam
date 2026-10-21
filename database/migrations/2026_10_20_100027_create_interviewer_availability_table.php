<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interviewer_availability', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interviewer_id')->constrained('users');
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->enum('availability_status', ['open', 'booked', 'cancelled'])->default('open')->index();
            $table->foreignId('UID')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('LUID')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interviewer_availability');
    }
};
