<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Every installation of LoMaS is a node. Central is a node that also
     * consolidates. See AGENTS.md section 69.2.
     */
    public function up(): void
    {
        Schema::create('nodes', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Written into every business number and audit entry, so it is
            // permanent once the node is in service.
            $table->string('code', 16)->unique();

            $table->string('name', 100);

            // central | branch
            $table->string('role', 16)->default('branch');

            // Ed25519 public key used to verify this node's outbound bundles.
            // Base64 encoded, filled in by the sync phase.
            $table->text('public_key')->nullable();

            $table->string('timezone', 64)->default('UTC');

            $table->boolean('is_active')->default(true);

            $table->timestamp('last_seen_at')->nullable();

            // The highest sync_events.sequence this node has applied, used to
            // detect a gap when the next bundle arrives.
            $table->unsignedBigInteger('last_applied_serial')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nodes');
    }
};
