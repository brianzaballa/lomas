<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('borrowers', function (Blueprint $table) {
            $table->uuid('default_area_id')->nullable()->after('node_id');
            $table->index('default_area_id');
            $table->foreign('default_area_id')->references('id')->on('areas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('borrowers', function (Blueprint $table) {
            $table->dropForeign(['default_area_id']);
            $table->dropIndex(['default_area_id']);
            $table->dropColumn('default_area_id');
        });
    }
};
