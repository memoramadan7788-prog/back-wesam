<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('exams');
            $table->enum('question_type', ['mcq', 'written', 'voice', 'coding'])->default('mcq');
            $table->text('content');
            $table->unsignedSmallInteger('points');
            $table->unsignedSmallInteger('order_no');
            $table->enum('source', ['manual', 'ai'])->default('manual');
            $table->string('status')->default('draft')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
