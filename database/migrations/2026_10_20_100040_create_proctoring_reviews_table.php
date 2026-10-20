<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proctoring_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('proctoring_events');
            $table->foreignId('reviewer_id')->constrained('users');
            $table->string('decision');
            $table->timestamp('reviewed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proctoring_reviews');
    }
};
