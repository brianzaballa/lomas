<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A collection day: the unit a set of payments is posted as.
     *
     * An encoder prepares an open collection day, adding payments to it over as
     * many sittings as needed, including for a day that has already passed.
     * Posting the day closes it as a unit, and no payment is added, edited or
     * deleted afterwards. Corrections are reversals plus replacements. See
     * AGENTS.md section 63.1.7.
     *
     * It is a domain table, so it takes a uuid key assigned by the creating node
     * plus node_id, per sections 42 and 66.16.
     */
    public function up(): void
    {
        Schema::create('collections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('node_id');

            // The collection day being posted, not necessarily today.
            $table->date('business_date');

            // open | posted, see App\Models\Collection.
            $table->string('status', 16)->default('open');

            // Local user ids are recorded for the node's own screens; the audit
            // trail carries the consolidatable actor label. See section 69.10.
            $table->unsignedBigInteger('prepared_by')->nullable();
            $table->timestamp('prepared_at')->nullable();
            $table->unsignedBigInteger('posted_by')->nullable();
            $table->timestamp('posted_at')->nullable();

            // Denormalised for the day's header; always recomputed from the
            // payments it holds, so it can never drift from them.
            $table->unsignedInteger('payment_count')->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);

            $table->timestamps();

            // One collection day per node. This is what makes posting idempotent
            // and stops a day being posted twice.
            $table->unique(['node_id', 'business_date']);
            $table->index(['node_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collections');
    }
};
