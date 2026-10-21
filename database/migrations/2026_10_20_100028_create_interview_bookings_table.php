<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interview_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interview_id')->constrained('interviews');
            $table->foreignId('interviewer_id')->constrained('users');
            $table->foreignId('availability_id')->unique()->constrained('interviewer_availability');
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->string('booking_status')->default('booked')->index();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->unique(['interviewer_id', 'start_at']);
            $table->foreignId('UID')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('LUID')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interview_bookings');
    }
};
