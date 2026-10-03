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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // The node the entry was written on. Two nodes both have a user 1, so
            // the node plus the actor label is the identity. See AGENTS.md
            // sections 47 and 69.10.
            $table->uuid('node_id');

            // A local user id is kept for convenience only, never as the
            // identity. Users are node local and are never synchronised.
            $table->unsignedBigInteger('user_id')->nullable();

            // The actor as staff know them, e.g. "MNL1 - admin@lomas.test".
            $table->string('user_label', 191)->nullable();

            $table->string('action', 64);

            // The morph pointer is a reference, not a key of its own, so it is
            // stored as text: audited domain records are keyed by uuid, while
            // audited users and roles are node local and keyed by integer.
            $table->string('auditable_type', 191)->nullable();
            $table->string('auditable_id', 36)->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('reason')->nullable();
            $table->string('ip_address', 45)->nullable();

            // The business time of the action on the writing node, which is not
            // the same as the moment the entry arrives at central.
            $table->timestamp('occurred_at');

            $table->timestamp('created_at')->useCurrent();

            $table->index('action');
            $table->index('created_at');
            $table->index('node_id');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
