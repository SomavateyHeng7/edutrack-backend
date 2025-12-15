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
        Schema::table('curriculum_courses', function (Blueprint $table) {
            $table->boolean('override_requires_permission')->nullable()->after('position');
            $table->boolean('override_summer_only')->nullable()->after('override_requires_permission');
            $table->boolean('override_requires_senior_standing')->nullable()->after('override_summer_only');
            $table->integer('override_min_credit_threshold')->nullable()->after('override_requires_senior_standing');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('curriculum_courses', function (Blueprint $table) {
            $table->dropColumn([
                'override_requires_permission',
                'override_summer_only',
                'override_requires_senior_standing',
                'override_min_credit_threshold'
            ]);
        });
    }
};
