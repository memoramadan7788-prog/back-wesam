<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_nominations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('jobs')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('company_employees')->cascadeOnDelete();
            $table->foreignId('nominated_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('nominated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unique(['job_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_nominations');
    }
};
