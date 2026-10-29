<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_onboardings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->unique()->constrained('candidate_profiles')->cascadeOnDelete();
            $table->json('steps')->nullable();
            $table->unsignedTinyInteger('completion_percent')->default(0);
            $table->foreignId('UID')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('LUID')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('candidate_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->unique()->constrained('candidate_profiles')->cascadeOnDelete();
            $table->json('desired_titles')->nullable();
            $table->string('location')->nullable();
            $table->string('employment_type')->nullable();
            $table->decimal('min_salary', 12, 2)->nullable();
            $table->string('work_model')->nullable();
            $table->boolean('open_to_relocation')->default(false);
            $table->boolean('include_agency')->default(false);
            $table->foreignId('UID')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('LUID')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('candidate_notification_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->unique()->constrained('candidate_profiles')->cascadeOnDelete();
            $table->boolean('weekly_digest')->default(false);
            $table->boolean('status_updates')->default(true);
            $table->boolean('interview_reminders')->default(true);
            $table->boolean('messages')->default(true);
            $table->boolean('marketing')->default(false);
            $table->foreignId('UID')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('LUID')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('candidate_privacy_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->unique()->constrained('candidate_profiles')->cascadeOnDelete();
            $table->string('visibility')->default('employers_only');
            $table->boolean('allow_agency_recruiters')->default(false);
            $table->boolean('show_in_public_search')->default(false);
            $table->foreignId('UID')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('LUID')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_privacy_settings');
        Schema::dropIfExists('candidate_notification_settings');
        Schema::dropIfExists('candidate_preferences');
        Schema::dropIfExists('candidate_onboardings');
    }
};
