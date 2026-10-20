<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_review_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_result_id')->constrained('cv_match_results');
            $table->foreignId('reviewer_id')->constrained('users');
            $table->enum('decision', ['accept', 'override'])->default('accept');
            $table->text('reason')->nullable();
            $table->timestamp('decided_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_review_decisions');
    }
};
