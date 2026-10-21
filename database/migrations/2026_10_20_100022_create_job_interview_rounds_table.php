<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_interview_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('jobs')->cascadeOnDelete();
            $table->unsignedTinyInteger('round_no');
            $table->enum('interview_type', ['onsite', 'online', 'custom'])->default('onsite');
            $table->unsignedSmallInteger('duration_min');
            $table->foreignId('UID')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('LUID')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_interview_rounds');
    }
};
