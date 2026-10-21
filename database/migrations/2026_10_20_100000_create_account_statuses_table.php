<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedBigInteger('UID')->nullable();
            $table->unsignedBigInteger('LUID')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::table('account_statuses')->insert([
            ['id' => 1, 'name' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Deactivated', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => 'Blocked', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'name' => 'Deleted', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('account_statuses');
    }
};

