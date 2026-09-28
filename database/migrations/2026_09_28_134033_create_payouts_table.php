<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instructor_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('idempotency_key')->unique();
            $table->string('status', 20)->default('pending');
            $table->string('provider_reference')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        DB::statement("ALTER TABLE payouts
            ADD CONSTRAINT chk_payout_amount CHECK (amount > 0),
            ADD CONSTRAINT chk_payout_status CHECK (status IN ('pending','processing','succeeded','failed','unknown'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payouts');
    }
};
