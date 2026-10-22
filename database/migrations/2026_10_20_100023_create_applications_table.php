<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->nullable()->constrained('jobs');
            $table->foreignId('candidate_id')->constrained('candidate_profiles');
            $table->foreignId('cv_id')->nullable()->constrained('candidate_cvs');
            $table->text('cover_note')->nullable();
            $table->timestamp('consent_at')->nullable();
            $table->string('status')->default('submitted')->index();
            $table->unsignedTinyInteger('attempt_no')->default(1);
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->unique(['job_id', 'candidate_id', 'attempt_no']);
            $table->foreignId('UID')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('LUID')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
