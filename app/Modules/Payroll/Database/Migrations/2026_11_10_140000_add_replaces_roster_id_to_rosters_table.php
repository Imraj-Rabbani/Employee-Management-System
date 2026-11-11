<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A change to an APPROVED roster is a new roster ("Plan a change", 7.2d) that points at the one it changes.
 * Once approved, its cells win: RosterItem::planFor() takes the most recently approved roster. [TA-03]
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rosters', function (Blueprint $table) {
            $table->foreignId('replaces_roster_id')->nullable()->after('org_unit_id')->constrained('rosters')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('rosters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('replaces_roster_id');
        });
    }
};
