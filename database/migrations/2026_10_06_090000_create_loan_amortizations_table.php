<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The frozen collection schedule of a released loan.
     *
     * The schedule is generated once, at release, and never regenerated. It
     * records only the amounts that were scheduled at that moment; paid and
     * remaining amounts are sums of payment allocations, so every peso is
     * traceable to a payment. See AGENTS.md sections 14 and 63.1.9.
     *
     * It is a domain table, so it takes a uuid key assigned by the creating
     * node plus node_id, per sections 42 and 66.16.
     */
    public function up(): void
    {
        Schema::create('loan_amortizations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('node_id');
            $table->uuid('loan_id');

            $table->unsignedInteger('installment_number');
            $table->date('due_date');

            // Interest is withheld at release and every installment of the
            // current product is principal, so interest_due stays zero. It is
            // present so a product that collects interest over the term does
            // not have to change payment history later. See section 63.1.2.
            $table->decimal('principal_due', 15, 2);
            $table->decimal('interest_due', 15, 2)->default(0);
            $table->decimal('total_due', 15, 2);

            // Scheduled amounts only. These are read from payment allocations,
            // which is why they start at zero and equal total_due.
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('remaining_amount', 15, 2);

            // pending | partially_paid | paid, see App\Models\LoanAmortization.
            $table->string('status', 20)->default('pending');

            $table->timestamps();

            $table->unique(['loan_id', 'installment_number']);
            $table->index(['loan_id', 'due_date']);
            $table->index(['node_id', 'due_date']);
            $table->index(['node_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_amortizations');
    }
};
