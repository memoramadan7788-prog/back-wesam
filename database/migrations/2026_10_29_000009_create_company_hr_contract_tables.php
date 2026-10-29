<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jobs', function (Blueprint $table) {
            $table->text('requirements')->nullable();
            $table->json('benefits')->nullable();
            $table->foreignId('requisition_owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('hiring_manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('allow_agency_submissions')->default(false);
            $table->boolean('publish_to_search')->default(true);
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->string('size')->nullable();
            $table->string('headquarters')->nullable();
            $table->string('timezone')->nullable();
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->string('source')->nullable()->index();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decision')->nullable();
            $table->text('decision_note')->nullable();
        });

        Schema::create('job_hiring_team', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('jobs')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role')->default('member');
            $table->timestamps();
            $table->unique(['job_id', 'user_id']);
        });

        Schema::create('application_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('company_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
            $table->string('email');
            $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->string('status')->default('pending')->index();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('company_talent_pool', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('candidate_id')->nullable()->constrained('candidate_profiles')->nullOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('source')->nullable();
            $table->string('relationship')->nullable();
            $table->json('tags')->nullable();
            $table->timestamp('consent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('offer_letter_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->text('body')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('offers', function (Blueprint $table) {
            $table->decimal('bonus', 12, 2)->nullable();
            $table->decimal('equity', 8, 4)->nullable();
            $table->json('additional_terms')->nullable();
            $table->string('employment_type')->nullable();
            $table->string('work_model')->nullable();
            $table->foreignId('offer_letter_template_id')->nullable()->constrained('offer_letter_templates')->nullOnDelete();
            $table->text('candidate_message')->nullable();
            $table->boolean('require_esignature')->default(false);
            $table->string('approval_status')->default('not_required')->index();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
        });

        Schema::create('company_workspaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained('companies')->cascadeOnDelete();
            $table->string('display_name')->nullable();
            $table->string('slug')->nullable()->unique();
            $table->string('size')->nullable();
            $table->string('timezone')->nullable();
            $table->string('headquarters')->nullable();
            $table->text('description')->nullable();
            $table->boolean('setup_completed')->default(false);
            $table->json('onboarding_steps')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropForeign(['offer_letter_template_id']);
            $table->dropForeign(['approved_by']);
            $table->dropColumn(['bonus', 'equity', 'additional_terms', 'employment_type', 'work_model', 'offer_letter_template_id', 'candidate_message', 'require_esignature', 'approval_status', 'approved_by', 'approved_at']);
        });
        Schema::dropIfExists('company_workspaces');
        Schema::dropIfExists('offer_letter_templates');
        Schema::dropIfExists('company_talent_pool');
        Schema::dropIfExists('company_invitations');
        Schema::dropIfExists('application_notes');
        Schema::dropIfExists('job_hiring_team');
        Schema::table('applications', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
            $table->dropColumn(['source', 'owner_id', 'decision', 'decision_note']);
        });
        Schema::table('jobs', function (Blueprint $table) {
            $table->dropForeign(['requisition_owner_id']);
            $table->dropForeign(['hiring_manager_id']);
            $table->dropColumn(['requirements', 'benefits', 'requisition_owner_id', 'hiring_manager_id', 'allow_agency_submissions', 'publish_to_search']);
        });
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['size', 'headquarters', 'timezone']);
        });
    }
};
