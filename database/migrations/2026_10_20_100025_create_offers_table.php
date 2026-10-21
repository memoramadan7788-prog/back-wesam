<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('applications');
            $table->decimal('salary_amount', 12, 2);
            $table->string('currency', 3)->default('EGP');
            $table->string('salary_period')->default('month');
            $table->date('start_date');
            $table->enum('status', ['sent', 'accepted', 'rejected', 'expired'])->default('sent')->index();
            $table->foreignId('sent_by')->constrained('users');
            $table->timestamp('sent_at')->useCurrent()->useCurrentOnUpdate();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('UID')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('LUID')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
