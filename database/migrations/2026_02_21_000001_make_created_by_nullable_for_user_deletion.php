<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Make created_by_id and assigned_by_id nullable so users can be deleted
     * without violating foreign key constraints.
     */
    public function up(): void
    {
        Schema::table('blacklists', function (Blueprint $table) {
            $table->string('created_by_id')->nullable()->change();
        });

        Schema::table('concentrations', function (Blueprint $table) {
            $table->string('created_by_id')->nullable()->change();
        });

        Schema::table('curricula', function (Blueprint $table) {
            $table->string('created_by_id')->nullable()->change();
        });

        Schema::table('department_course_types', function (Blueprint $table) {
            $table->string('assigned_by_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('blacklists', function (Blueprint $table) {
            $table->string('created_by_id')->nullable(false)->change();
        });

        Schema::table('concentrations', function (Blueprint $table) {
            $table->string('created_by_id')->nullable(false)->change();
        });

        Schema::table('curricula', function (Blueprint $table) {
            $table->string('created_by_id')->nullable(false)->change();
        });

        Schema::table('department_course_types', function (Blueprint $table) {
            $table->string('assigned_by_id')->nullable(false)->change();
        });
    }
};
