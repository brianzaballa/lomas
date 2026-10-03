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
        Schema::create('borrowers', function (Blueprint $table) {
            // Assigned by the creating node, never a shared counter, so a branch
            // and the hub can both work offline. See AGENTS.md section 69.3.
            $table->uuid('id')->primary();

            // The node that registered this borrower. Borrowers are owned by one
            // node and are never written by central. See AGENTS.md section 69.4.
            $table->uuid('node_id');

            // The business identifier shown to staff and printed on documents.
            // It carries the node code, so the unique index is global.
            $table->string('borrower_number', 32)->unique();

            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->string('suffix', 20)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('mobile_number', 20);
            $table->string('address', 255);

            // active | inactive, see App\Enums\BorrowerStatus.
            $table->string('status', 20)->default('active');

            $table->timestamps();

            // Supports the search fields listed in AGENTS.md section 60.
            $table->index('last_name');
            $table->index('mobile_number');
            $table->index('status');
            $table->index('node_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('borrowers');
    }
};
