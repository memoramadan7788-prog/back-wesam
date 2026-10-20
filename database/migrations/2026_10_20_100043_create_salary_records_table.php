<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('job_categories')->nullOnDelete();
            $table->string('job_title');
            $table->string('location')->nullable();
            $table->string('currency', 3)->default('EGP');
            $table->decimal('amount_min', 12, 2);
            $table->decimal('amount_max', 12, 2);
            $table->string('experience_level');
            $table->string('source');
            $table->timestamp('recorded_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_records');
    }
};
