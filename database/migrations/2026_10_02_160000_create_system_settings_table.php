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
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('business_name');
            $table->text('business_address')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('currency', 3)->default('PHP');
            $table->string('currency_symbol', 8)->default('PHP');
            // Node qualified formats, so two offline nodes never issue the same
            // number. See AGENTS.md sections 21, 22 and 69.3.
            $table->string('loan_number_format')->default('LN-{node}-{year}-{seq:6}');
            $table->string('borrower_number_format')->default('BR-{node}-{seq:6}');

            // Configuration distribution, see AGENTS.md section 69.8. The
            // settings row is configuration, so it is owned and published by
            // central and read by the nodes.
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('distributed_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
