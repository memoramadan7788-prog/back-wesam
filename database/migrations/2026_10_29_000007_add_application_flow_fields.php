<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->json('answers')->nullable()->after('cover_note');
            $table->decimal('expected_salary', 12, 2)->nullable()->after('answers');
            $table->string('portfolio_url', 2048)->nullable()->after('expected_salary');
            $table->boolean('keep_in_talent_pool')->default(false)->after('portfolio_url');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn([
                'answers',
                'expected_salary',
                'portfolio_url',
                'keep_in_talent_pool',
            ]);
        });
    }
};
