<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('jobs');
            $table->foreignId('salary_record_id')->nullable()->constrained('salary_records')->nullOnDelete();
            $table->decimal('range_min', 12, 2);
            $table->decimal('range_max', 12, 2);
            $table->string('currency', 3)->default('EGP');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_recommendations');
    }
};
