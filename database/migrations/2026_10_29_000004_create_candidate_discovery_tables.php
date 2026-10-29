<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_saved_searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('candidate_profiles')->cascadeOnDelete();
            $table->string('name');
            $table->json('filters');
            $table->boolean('weekly_alerts')->default(true);
            $table->boolean('is_paused')->default(false);
            $table->timestamp('last_alerted_at')->nullable();
            $table->timestamps();
            $table->index(['candidate_id', 'is_paused']);
        });

        Schema::create('candidate_hidden_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('candidate_profiles')->cascadeOnDelete();
            $table->foreignId('job_id')->constrained('jobs')->cascadeOnDelete();
            $table->string('reason')->nullable();
            $table->timestamps();
            $table->unique(['candidate_id', 'job_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_hidden_jobs');
        Schema::dropIfExists('candidate_saved_searches');
    }
};
