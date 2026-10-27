<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        DB::table('role_categories')->insert([
            [
                'name' => 'user',
                'description' => 'Platform-level user roles such as candidate.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'company',
                'description' => 'Roles assigned within a company.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        Schema::table('roles', function (Blueprint $table): void {
            $table->foreignId('role_category_id')
                ->nullable()
                ->after('id')
                ->constrained('role_categories')
                ->nullOnDelete();
        });

        $userCategoryId = DB::table('role_categories')->where('name', 'user')->value('id');
        $companyCategoryId = DB::table('role_categories')->where('name', 'company')->value('id');

        DB::table('roles')
            ->where('scope', 'company')
            ->update(['role_category_id' => $companyCategoryId]);

        DB::table('roles')
            ->where('scope', 'platform')
            ->update(['role_category_id' => $userCategoryId]);
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->dropForeign(['role_category_id']);
            $table->dropColumn('role_category_id');
        });

        Schema::dropIfExists('role_categories');
    }
};
