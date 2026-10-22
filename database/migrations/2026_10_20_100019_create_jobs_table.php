<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('category_id')->nullable()->constrained('job_categories');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->enum('work_mode', ['onsite', 'remote', 'hybrid'])->default('onsite');
            $table->string('employment_type')->default('full_time');
            $table->decimal('salary_min', 12, 2)->nullable();
            $table->decimal('salary_max', 12, 2)->nullable();
            $table->string('currency', 3)->default('EGP');
            $table->string('salary_period')->default('month');
            $table->unsignedTinyInteger('experience_years')->nullable();
            $table->string('department')->nullable();
            $table->unsignedSmallInteger('openings_count')->default(1);
            $table->dateTime('deadline')->nullable();
            $table->enum('visibility', ['public', 'private'])->default('public')->index();
            $table->enum('private_apply_mode', ['all_employees', 'nominated_only'])->nullable();
            $table->enum('status', ['draft', 'published', 'closed', 'archived'])->default('draft')->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            if (\Illuminate\Support\Facades\DB::getDriverName() !== 'sqlite') {
                $table->fullText(['title', 'description']);
            }
            $table->foreignId('UID')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('LUID')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobs');
    }
};
