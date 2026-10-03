<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The record of every bundle that crossed a node boundary, as described in
     * AGENTS.md section 69.6. An applied batch is retained, never deleted: it is
     * the evidence of what the receiving node was given and when.
     */
    public function up(): void
    {
        Schema::create('sync_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // inbound | outbound
            $table->string('direction', 16);

            // The node the data came from, or went to.
            $table->uuid('peer_node_id');

            $table->char('manifest_hash', 64);

            $table->boolean('signature_valid')->default(false);

            $table->unsignedInteger('event_count')->default(0);

            $table->unsignedBigInteger('first_sequence')->nullable();
            $table->unsignedBigInteger('last_sequence')->nullable();

            // verified | rejected | partial
            $table->string('status', 16)->default('verified');

            // Why a bundle was refused. A rejection is visible and retryable.
            $table->text('reject_reason')->nullable();

            $table->timestamp('received_at');

            $table->timestamp('applied_at')->nullable();

            $table->string('applied_by', 191)->nullable();

            $table->string('file_name', 191)->nullable();

            $table->timestamps();

            $table->index('peer_node_id');
            $table->index(['direction', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_batches');
    }
};
