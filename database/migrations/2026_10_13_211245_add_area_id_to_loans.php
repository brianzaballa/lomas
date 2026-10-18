<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->uuid('area_id')->nullable()->after('node_id');
            $table->index('area_id');
            $table->foreign('area_id')->references('id')->on('areas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropForeign(['area_id']);
            $table->dropIndex(['area_id']);
            $table->dropColumn('area_id');
        });
    }
};
