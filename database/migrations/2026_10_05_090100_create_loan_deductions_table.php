<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The exact deductions charged to one loan.
     *
     * This is the snapshot required by AGENTS.md section 8. Every figure the
     * calculation used is copied here, so a later settings change can never
     * recalculate a loan that was encoded under the old configuration.
     *
     * It is a domain table, so it takes a uuid key assigned by the creating
     * node plus node_id, per sections 42 and 66.16.
     */
    public function up(): void
    {
        Schema::create('loan_deductions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('node_id');
            $table->uuid('loan_id');

            // A custom charge added to a single loan belongs to no configured
            // type, and neither does interest: its rate lives on the product.
            $table->uuid('deduction_type_id')->nullable();

            // Copied from the configuration at the time of encoding, so the
            // original calculation stays reproducible.
            $table->string('name', 100);
            $table->string('code', 32)->nullable();
            $table->string('calculation_method', 40);

            // Only the value that belongs to the calculation method is set.
            $table->decimal('rate', 5, 2)->nullable();
            $table->decimal('fixed_amount', 15, 2)->nullable();

            // The gross loan amount the calculation was applied to, and the
            // peso amount actually deducted.
            $table->decimal('base_amount', 15, 2);
            $table->decimal('amount', 15, 2);

            // Set when an encoder replaced the calculated amount by hand. It is
            // what makes an overridden figure explainable later.
            $table->decimal('override_amount', 15, 2)->nullable();

            $table->boolean('is_interest')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['loan_id', 'sort_order']);
            $table->index(['node_id', 'created_at']);
            $table->index('deduction_type_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_deductions');
    }
};
