<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How one payment settled one installment.
     *
     * Posting a payment writes one allocation row per installment it touches, so
     * every peso of a balance is traceable to a payment and a reversal only has
     * to touch one payment and its allocations. See AGENTS.md sections 30 and
     * 63.1.5.
     *
     * It is a domain table, so it takes a uuid key assigned by the creating node
     * plus node_id, per sections 42 and 66.16.
     */
    public function up(): void
    {
        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('node_id');

            $table->uuid('payment_id');
            $table->uuid('loan_amortization_id');

            // principal | interest | penalty | other. Every installment of the
            // current product is principal, but the column already carries the
            // values a later product will need. See section 63.1.11.
            $table->string('component', 16);

            $table->decimal('amount', 15, 2);

            $table->timestamps();

            $table->index('payment_id');
            $table->index('loan_amortization_id');
            $table->index(['node_id', 'component']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
    }
};
