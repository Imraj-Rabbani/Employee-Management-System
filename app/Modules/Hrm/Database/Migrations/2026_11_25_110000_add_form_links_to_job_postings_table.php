<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The online form people apply through (e.g. a Google Form) and the spreadsheet its answers go to.
 * Only kept as links: HR opens the sheet, downloads it and imports it (Import on the posting / Applicants).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->string('form_url', 500)->nullable()->after('requirements');
            $table->string('responses_url', 500)->nullable()->after('form_url');
        });
    }

    public function down(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->dropColumn(['form_url', 'responses_url']);
        });
    }
};
