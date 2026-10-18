<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The period the Notes Collection Report is posted in.
     *
     * weekly: Monday to Saturday.
     * decile: the 1st to 10th, the 11th to 20th, and the 21st to the last day
     * of the month.
     *
     * It is configuration, so it is owned and published by central and read by
     * the nodes, like the rest of the settings row. See AGENTS.md sections 41
     * and 69.8.
     */
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->string('ncr_period_mode', 16)->default('weekly');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn('ncr_period_mode');
        });
    }
};
