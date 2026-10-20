<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cv_match_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('applications');
            $table->foreignId('cv_id')->constrained('candidate_cvs');
            $table->decimal('score', 5, 2);
            $table->json('matched_skills');
            $table->json('missing_skills');
            $table->text('summary')->nullable();
            $table->string('model_version')->nullable();
            $table->string('request_id')->nullable();
            $table->enum('run_status', ['pending', 'ok', 'failed', 'timeout'])->default('pending')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cv_match_results');
    }
};
