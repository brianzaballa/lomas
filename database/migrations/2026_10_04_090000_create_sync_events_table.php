<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The append only change log of the node, as described in AGENTS.md
     * section 69.5. One row is written in the same transaction as the business
     * record it describes. Rows are never updated and never deleted.
     */
    public function up(): void
    {
        Schema::create('sync_events', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // The node that wrote the event.
            $table->uuid('node_id');

            // Monotonic per node, starting at 1, never reused. The unique index
            // is what makes importing the same bundle twice a no-op.
            $table->unsignedBigInteger('sequence');

            $table->string('entity_type', 128);
            $table->uuid('entity_id');

            // created | updated | reversed
            $table->string('operation', 16);

            // The row as written, money as exact decimal strings.
            $table->json('payload');

            // Business time on the writing node.
            $table->timestamp('occurred_at');

            // Receipt time on the node that applied the event.
            $table->timestamp('recorded_at')->nullable();

            $table->char('payload_hash', 64);

            // The outbound batch that carried the event, null while pending.
            $table->uuid('batch_id')->nullable();

            $table->timestamps();

            $table->unique(['node_id', 'sequence']);
            $table->index(['entity_type', 'entity_id']);
            $table->index('batch_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_events');
    }
};
