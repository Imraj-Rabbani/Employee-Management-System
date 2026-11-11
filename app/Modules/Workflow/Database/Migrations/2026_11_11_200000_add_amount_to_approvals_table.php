<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task 7.7: a number the approver enters when approving, e.g. the overtime hours they recommend / approve.
 * Only for documents that ask for it (Contracts\AsksApproverForAmount); null for every other approval.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approvals', function (Blueprint $table) {
            $table->decimal('amount', 12, 2)->nullable()->after('comment');
        });
    }

    public function down(): void
    {
        Schema::table('approvals', fn (Blueprint $table) => $table->dropColumn('amount'));
    }
};
