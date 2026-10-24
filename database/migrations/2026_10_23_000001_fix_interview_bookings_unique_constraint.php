<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 0.3: Fix interview_bookings unique constraints.
 *
 * The blanket UNIQUE on availability_id prevents re-booking a cancelled/soft-deleted slot.
 * The composite UNIQUE on [interviewer_id, start_at] also prevents re-booking the same
 * interviewer at the same time after cancellation.
 *
 * This migration drops both unique indexes. Uniqueness is enforced at the application level
 * (only active bookings are checked before creating a new one).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('interview_bookings', function (Blueprint $table) {
            try {
                $table->dropUnique(['availability_id']);
            } catch (\Exception $e) {
                // Index may not exist in test/SQLite environments
            }

            try {
                $table->dropUnique(['interviewer_id', 'start_at']);
            } catch (\Exception $e) {
                // Index may not exist in test/SQLite environments
            }
        });
    }

    public function down(): void
    {
        Schema::table('interview_bookings', function (Blueprint $table) {
            $table->unique('availability_id');
            $table->unique(['interviewer_id', 'start_at']);
        });
    }
};
