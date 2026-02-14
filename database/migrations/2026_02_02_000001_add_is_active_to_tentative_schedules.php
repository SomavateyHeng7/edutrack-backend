<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tentative_schedules', function (Blueprint $table) {
            $table->boolean('is_active')->default(false)->after('is_published');
            
            // Add unique constraint: only one active schedule per department
            // This will be enforced at the application level to allow null departments
        });
        
        // Add comment for the constraint
        DB::statement("COMMENT ON COLUMN tentative_schedules.is_active IS 'Only one active schedule per department for student view'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tentative_schedules', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
