<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cv_match_results', function (Blueprint $table) {
            $table->decimal('score', 5, 2)->nullable()->change();
            $table->json('matched_skills')->nullable()->change();
            $table->json('missing_skills')->nullable()->change();

            $table->json('details')->nullable()->after('summary');
            $table->text('error_message')->nullable()->after('run_status');
            $table->timestamp('finished_at')->nullable()->after('error_message');

            $table->index(['application_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::table('cv_match_results', function (Blueprint $table) {
            $table->dropIndex(['application_id', 'id']);
            $table->dropColumn(['details', 'error_message', 'finished_at']);
        });
       
    }
};
