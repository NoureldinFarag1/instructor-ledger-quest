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
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instructor_id')->constrained();
            $table->foreignId('subscription_id')->nullable()->constrained();
            $table->foreignId('payout_id')->nullable()->constrained();
            $table->string('type', 30);
            $table->bigInteger('amount');
            $table->date('recognized_through')->nullable(); // earned entries only
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['instructor_id', 'subscription_id', 'recognized_through'], 'uq_ledger_earning_day');

            $table->unique(['payout_id', 'type'], 'uq_ledger_payout_type');

            $table->index(['instructor_id', 'type']);
        });

        DB::statement("ALTER TABLE ledger_entries
            ADD CONSTRAINT chk_ledger_shape CHECK (
                (type = 'earned' AND amount > 0 AND subscription_id IS NOT NULL
                    AND recognized_through IS NOT NULL AND payout_id IS NULL)
                OR (type = 'payout_reserved' AND amount < 0 AND payout_id IS NOT NULL
                    AND subscription_id IS NULL)
                OR (type = 'payout_released' AND amount > 0 AND payout_id IS NOT NULL
                    AND subscription_id IS NULL)
            )");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
