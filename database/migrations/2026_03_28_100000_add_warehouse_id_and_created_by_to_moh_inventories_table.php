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
        Schema::table('moh_inventories', function (Blueprint $table) {
            if (!Schema::hasColumn('moh_inventories', 'warehouse_id')) {
                $table->after('date', function (Blueprint $table) {
                    $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->onDelete('cascade');
                });
            }
            if (!Schema::hasColumn('moh_inventories', 'created_by')) {
                $table->after('rejection_reason', function (Blueprint $table) {
                    $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('cascade');
                });
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('moh_inventories', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropColumn('warehouse_id');
            $table->dropForeign(['created_by']);
            $table->dropColumn('created_by');
        });
    }
};
