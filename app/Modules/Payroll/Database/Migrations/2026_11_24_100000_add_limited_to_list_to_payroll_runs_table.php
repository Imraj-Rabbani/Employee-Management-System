<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 10.9: a run made for a list of employee codes ("only these employees") keeps to that list when it is calculated
 * again: nobody new is added to it (people who are no longer in scope still leave it). A run for a whole scope
 * (entity / company) takes in joiners when it is calculated again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->boolean('limited_to_list')->default(false)->after('entity_id');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_runs', fn (Blueprint $table) => $table->dropColumn('limited_to_list'));
    }
};
