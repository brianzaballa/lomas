<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Number sequences are drawn from the local node's own row while it is
     * offline. Central never allocates on a branch's behalf. See AGENTS.md
     * section 69.3.
     */
    public function up(): void
    {
        Schema::create('sequences', function (Blueprint $table) {
            $table->id();

            // Intentionally not a foreign key. A node may be retired while its
            // numbers stay on historical records, so provenance is never blocked
            // by referential integrity.
            $table->uuid('node_id');

            // borrower | loan | payment
            $table->string('entity', 32);

            // The value handed out next.
            $table->unsignedBigInteger('next_value')->default(1);

            $table->timestamps();

            $table->unique(['node_id', 'entity']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sequences');
    }
};
