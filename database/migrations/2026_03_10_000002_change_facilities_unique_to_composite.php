<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $indexes = DB::select("SHOW INDEX FROM facilities WHERE Key_name = 'facilities_name_unique'");
        if (!empty($indexes)) {
            Schema::table('facilities', function (Blueprint $table) {
                $table->dropUnique(['name']);
            });
        }

        $compositeExists = DB::select("SHOW INDEX FROM facilities WHERE Key_name = 'facilities_name_type_region_district_unique'");
        if (empty($compositeExists)) {
            DB::statement('ALTER TABLE facilities ADD UNIQUE facilities_name_type_region_district_unique (name(100), facility_type(80), region(80), district(80))');
        }
    }

    public function down(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            $table->dropUnique('facilities_name_type_region_district_unique');
            $table->unique('name');
        });
    }
};
