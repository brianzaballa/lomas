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
        Schema::create('loan_products', function (Blueprint $table) {
            // Assigned by the node that created the product, which for
            // configuration is always central. See AGENTS.md section 69.3.
            $table->uuid('id')->primary();

            $table->string('name', 100);
            $table->string('code', 32)->unique();
            $table->text('description')->nullable();

            // The term counts valid collection days, never calendar days.
            $table->unsignedInteger('term');
            $table->string('term_unit', 20)->default('collection_days');
            $table->string('payment_frequency', 20)->default('daily');

            // Collection calendar rules, see App\Domain\Loans\CollectionCalendar
            // when that service is added.
            $table->boolean('exclude_sundays')->default(true);
            $table->boolean('exclude_saturdays')->default(true);

            // When false, the first collection is the day after the release date.
            $table->boolean('release_date_is_day_one')->default(false);

            // Interest is withheld at release, so the schedule collects the
            // principal only. See AGENTS.md section 63.1.2.
            $table->string('interest_method', 40)->default('percentage_of_loan_amount');
            $table->decimal('interest_rate', 5, 2)->default(0);

            $table->boolean('is_active')->default(true);

            // Configuration distribution, see AGENTS.md section 69.8. A branch
            // reads the product and cannot change it; the version tells the
            // branch how far behind central it is, and the loan records the
            // version it was encoded under.
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('distributed_at')->nullable();

            $table->timestamps();

            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_products');
    }
};
