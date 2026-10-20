<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interview_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interview_id')->constrained('interviews');
            $table->foreignId('evaluator_id')->constrained('users');
            $table->decimal('numeric_score', 4, 2);
            $table->text('internal_notes');
            $table->text('candidate_feedback')->nullable();
            $table->timestamp('feedback_shared_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interview_evaluations');
    }
};
