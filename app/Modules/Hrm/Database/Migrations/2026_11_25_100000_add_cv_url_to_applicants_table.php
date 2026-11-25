<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * applicants.cv_url: a LINK to the CV kept somewhere else (e.g. the Google Drive file a Google Form
 * upload made). Imported from a form's sheet; HR can also type it. The uploaded file stays in cv_path.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->string('cv_url', 500)->nullable()->after('cv_name');
        });
    }

    public function down(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->dropColumn('cv_url');
        });
    }
};
