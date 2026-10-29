<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_application_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('candidate_profiles')->cascadeOnDelete();
            $table->foreignId('job_id')->constrained('jobs')->cascadeOnDelete();
            $table->foreignId('cv_id')->nullable()->constrained('candidate_cvs')->nullOnDelete();
            $table->json('answers')->nullable();
            $table->decimal('expected_salary', 12, 2)->nullable();
            $table->string('portfolio_url', 2048)->nullable();
            $table->text('cover_note')->nullable();
            $table->boolean('keep_in_talent_pool')->default(false);
            $table->boolean('consent')->default(false);
            $table->timestamps();
            $table->unique(['candidate_id', 'job_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_application_drafts');
    }
};
