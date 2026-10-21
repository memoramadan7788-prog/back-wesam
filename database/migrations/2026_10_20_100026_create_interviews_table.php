<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('applications');
            $table->unsignedTinyInteger('round_no');
            $table->enum('interview_type', ['onsite', 'online', 'custom'])->default('onsite');
            $table->string('status')->default('pending')->index();
            $table->string('meeting_link', 2048)->nullable();
            $table->string('location')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('UID')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('LUID')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interviews');
    }
};
