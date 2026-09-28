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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('plan');
            $table->unsignedBigInteger('amount');
            $table->unsignedSmallInteger('instructor_share_bps');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->date('refunded_on')->nullable();
            $table->timestamps();

            $table->index('ends_on');
        });

        DB::statement('ALTER TABLE subscriptions
        ADD CONSTRAINT chk_sub_amount CHECK (amount > 0),
        ADD CONSTRAINT chk_sub_share CHECK (instructor_share_bps <= 10000),
        ADD CONSTRAINT chk_sub_term CHECK (ends_on > starts_on),
        ADD CONSTRAINT chk_sub_refund CHECK (refunded_on IS NULL OR (refunded_on >= starts_on AND refunded_on <= ends_on))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
