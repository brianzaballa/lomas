<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Loans are encoded as drafts in Phase 4. Release and amortization rows are
     * added later once the release workflow lands.
     */
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('node_id');
            $table->string('loan_number', 64)->unique();
            $table->uuid('borrower_id');
            $table->uuid('loan_product_id');
            $table->string('status', 32)->default('draft');
            $table->date('application_date');
            $table->date('release_date')->nullable();
            $table->decimal('gross_loan_amount', 15, 2);
            $table->decimal('interest_amount', 15, 2)->default(0);
            $table->decimal('deductions_amount', 15, 2)->default(0);
            $table->decimal('net_proceeds_amount', 15, 2);
            $table->decimal('total_amount_payable', 15, 2);
            $table->timestamps();

            $table->index(['node_id', 'status']);
            $table->index(['borrower_id', 'created_at']);
            $table->index(['loan_product_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
