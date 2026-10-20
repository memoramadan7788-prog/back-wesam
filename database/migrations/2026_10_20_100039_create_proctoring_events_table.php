<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proctoring_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained('exam_attempts');
            $table->string('event_type');
            $table->decimal('confidence', 5, 2)->nullable();
            $table->string('model_version');
            $table->timestamp('occurred_at')->useCurrent()->useCurrentOnUpdate();
            $table->string('evidence_key', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proctoring_events');
    }
};
