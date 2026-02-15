<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration renames required_courses to required_credits in curriculum_concentrations.
     * The field now represents the number of credits required from a concentration,
     * rather than the number of courses.
     */
    public function up(): void
    {
        Schema::table('curriculum_concentrations', function (Blueprint $table) {
            $table->renameColumn('required_courses', 'required_credits');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('curriculum_concentrations', function (Blueprint $table) {
            $table->renameColumn('required_credits', 'required_courses');
        });
    }
};
