<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('loan_deduction_types', function (Blueprint $table) {
            // Assigned by the node that created the type, which for
            // configuration is always central. See AGENTS.md section 69.3.
            $table->uuid('id')->primary();

            $table->string('name', 100);
            $table->string('code', 32)->unique();
            $table->text('description')->nullable();

            // A percentage uses rate, a fixed amount uses fixed_amount. Only the
            // field that belongs to the calculation method is stored.
            $table->string('calculation_method', 40);
            $table->decimal('rate', 5, 2)->nullable();
            $table->decimal('fixed_amount', 15, 2)->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);

            // Configuration distribution, see AGENTS.md section 69.8.
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('distributed_at')->nullable();

            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_deduction_types');
    }
};
