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
        // MySQL cannot drop an index while a foreign key is using it as the
        // supporting index. Drop and recreate the FK around the index change.
        $this->attempt(function () {
            Schema::table('interview_bookings', function (Blueprint $table) {
                $table->dropForeign('interview_bookings_availability_id_foreign');
            });
        });

        $this->attempt(function () {
            Schema::table('interview_bookings', function (Blueprint $table) {
                $table->dropForeign('interview_bookings_interviewer_id_foreign');
            });
        });

        $this->attempt(function () {
            Schema::table('interview_bookings', function (Blueprint $table) {
                $table->dropUnique('interview_bookings_availability_id_unique');
            });
        });

        $this->attempt(function () {
            Schema::table('interview_bookings', function (Blueprint $table) {
                $table->dropUnique('interview_bookings_interviewer_id_start_at_unique');
            });
        });

        $this->attempt(function () {
            Schema::table('interview_bookings', function (Blueprint $table) {
                $table->index('availability_id', 'interview_bookings_availability_id_index');
            });
        });

        $this->attempt(function () {
            Schema::table('interview_bookings', function (Blueprint $table) {
                $table->foreign('availability_id', 'interview_bookings_availability_id_foreign')
                    ->references('id')
                    ->on('interviewer_availability');
            });
        });

        $this->attempt(function () {
            Schema::table('interview_bookings', function (Blueprint $table) {
                $table->foreign('interviewer_id', 'interview_bookings_interviewer_id_foreign')
                    ->references('id')
                    ->on('users');
            });
        });
    }

    public function down(): void
    {
        $this->attempt(function () {
            Schema::table('interview_bookings', function (Blueprint $table) {
                $table->dropForeign('interview_bookings_availability_id_foreign');
            });
        });

        $this->attempt(function () {
            Schema::table('interview_bookings', function (Blueprint $table) {
                $table->dropForeign('interview_bookings_interviewer_id_foreign');
            });
        });

        $this->attempt(function () {
            Schema::table('interview_bookings', function (Blueprint $table) {
                $table->dropIndex('interview_bookings_availability_id_index');
            });
        });

        $this->attempt(function () {
            Schema::table('interview_bookings', function (Blueprint $table) {
                $table->unique('availability_id', 'interview_bookings_availability_id_unique');
            });
        });

        $this->attempt(function () {
            Schema::table('interview_bookings', function (Blueprint $table) {
                $table->unique(
                    ['interviewer_id', 'start_at'],
                    'interview_bookings_interviewer_id_start_at_unique'
                );
            });
        });

        $this->attempt(function () {
            Schema::table('interview_bookings', function (Blueprint $table) {
                $table->foreign('availability_id', 'interview_bookings_availability_id_foreign')
                    ->references('id')
                    ->on('interviewer_availability');
            });
        });

        $this->attempt(function () {
            Schema::table('interview_bookings', function (Blueprint $table) {
                $table->foreign('interviewer_id', 'interview_bookings_interviewer_id_foreign')
                    ->references('id')
                    ->on('users');
            });
        });
    }

    private function attempt(\Closure $operation): void
    {
        try {
            $operation();
        } catch (\Throwable) {
            // Keep the migration retryable after a partial DDL execution.
        }
    }
};
