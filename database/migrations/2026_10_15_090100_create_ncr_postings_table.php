<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The posting of one Notes Collection Report period.
     *
     * Posting a period is the sign-off that its figures have been collected
     * and checked. While a period is posted, no payment dated inside it may be
     * staged, posted, edited or reversed; unposting restores that, with an
     * audit trail on both transitions. See AGENTS.md sections 47 and 48.
     *
     * It is a domain table, so it takes a uuid key assigned by the creating
     * node plus node_id, per sections 42 and 66.16.
     */
    public function up(): void
    {
        Schema::create('ncr_postings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('node_id');

            // The period being posted, resolved at posting time from the
            // period mode in effect. The dates, not the mode, are what the
            // lock and the report match against.
            $table->date('period_start');
            $table->date('period_end');

            // open | posted, see App\Models\NcrPosting.
            $table->string('status', 16)->default('open');

            // Local user ids are recorded for the node's own screens; the audit
            // trail carries the consolidatable actor label. See section 69.10.
            $table->unsignedBigInteger('posted_by')->nullable();
            $table->timestamp('posted_at')->nullable();

            $table->timestamps();

            // One posting per period per node: posting twice is idempotent, and
            // the unique index is what stops two rows racing to lock the same
            // period.
            $table->unique(['node_id', 'period_start', 'period_end']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ncr_postings');
    }
};
