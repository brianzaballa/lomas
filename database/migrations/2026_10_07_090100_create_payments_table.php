<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A single cash receipt against a loan.
     *
     * A payment is a historical transaction and is never deleted. While its
     * collection day is open it is staged as pending; posting the day makes it
     * posted; a correction makes it reversed and its allocations stop counting.
     * The excess over the amount owed is kept here as unapplied_amount rather
     * than pushed into a future installment. See AGENTS.md sections 29, 31, 32,
     * 33, 63.1.5, 63.1.6 and 63.1.8.
     *
     * It is a domain table, so it takes a uuid key assigned by the creating node
     * plus node_id, per sections 42 and 66.16.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('node_id');
            $table->string('payment_number', 64)->unique();

            $table->uuid('loan_id');
            $table->uuid('collection_id');

            // The collection day the payment settles. It always equals the
            // collection day of its posting, so a payment can never silently
            // move to another day. See section 63.1.7.
            $table->date('payment_date');

            $table->decimal('amount', 15, 2);
            $table->decimal('allocated_amount', 15, 2)->default(0);
            $table->decimal('unapplied_amount', 15, 2)->default(0);

            $table->string('payment_method', 32)->default('cash');
            $table->string('reference_number')->nullable();
            $table->unsignedBigInteger('received_by')->nullable();

            // pending | posted | reversed, see App\Models\Payment.
            $table->string('status', 16)->default('pending');

            // Reversal never overwrites the original: a replacement payment
            // points back at the payment it corrects, and the original keeps its
            // row with a reason, an actor and a timestamp. See section 33.
            $table->uuid('reversal_of_payment_id')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->unsignedBigInteger('reversed_by')->nullable();
            $table->string('reversal_reason')->nullable();

            $table->timestamps();

            $table->index(['loan_id', 'payment_date']);
            $table->index(['loan_id', 'status']);
            $table->index(['node_id', 'payment_date']);
            $table->index(['node_id', 'status']);
            $table->index('collection_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
