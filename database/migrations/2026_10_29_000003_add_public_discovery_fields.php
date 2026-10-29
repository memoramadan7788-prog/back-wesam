<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jobs', function (Blueprint $table) {
            $table->string('requisition_code')->nullable()->unique()->after('title');
            $table->enum('hiring_model', ['direct', 'agency'])->default('direct')->after('requisition_code');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->text('description')->nullable()->after('industry');
            $table->text('culture')->nullable()->after('description');
            $table->json('benefits')->nullable()->after('culture');
            $table->json('locations')->nullable()->after('benefits');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['description', 'culture', 'benefits', 'locations']);
        });

        Schema::table('jobs', function (Blueprint $table) {
            $table->dropUnique(['requisition_code']);
            $table->dropColumn(['requisition_code', 'hiring_model']);
        });
    }
};
