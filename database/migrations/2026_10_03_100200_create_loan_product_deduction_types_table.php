<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which deduction types a loan product charges.
     *
     * This is configuration, so it carries a uuid assigned by central and no
     * node_id, exactly like loan_products and loan_deduction_types. Central
     * owns the definition and distributes it; a branch reads it and never
     * writes it. See AGENTS.md sections 42 and 69.4.
     */
    public function up(): void
    {
        Schema::create('loan_product_deduction_types', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('loan_product_id');
            $table->uuid('loan_deduction_type_id');

            // The set of linked types is the whole configuration: a type that
            // is not linked is not charged. Order follows the form.
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();

            // Named explicitly: the generated name would be 81 characters and
            // MySQL caps an identifier at 64. SQLite does not enforce the cap,
            // so this only fails against the real database.
            $table->unique(
                ['loan_product_id', 'loan_deduction_type_id'],
                'loan_product_deduction_types_unique',
            );
            $table->index('loan_deduction_type_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_product_deduction_types');
    }
};
