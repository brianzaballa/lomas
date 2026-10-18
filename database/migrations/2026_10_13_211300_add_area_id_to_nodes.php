<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attach a node to an area for collection grouping.
 *
 * It lives in its own migration because the areas table is created after the
 * nodes table, so the foreign key cannot be declared when nodes is created.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('nodes', 'area_id')) {
            return;
        }

        Schema::table('nodes', function (Blueprint $table) {
            $table->uuid('area_id')->nullable()->after('id');
            $table->index('area_id');
            $table->foreign('area_id')->references('id')->on('areas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('nodes', 'area_id')) {
            return;
        }

        Schema::table('nodes', function (Blueprint $table) {
            $table->dropForeign(['area_id']);
            $table->dropIndex(['area_id']);
            $table->dropColumn('area_id');
        });
    }
};
