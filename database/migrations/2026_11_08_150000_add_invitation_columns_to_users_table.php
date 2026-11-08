<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invitations (5.7): a login created by "Invite" has a random password until its owner sets their own
 * through the emailed link.
 *
 *   invited_at               last time an invitation was sent (null = created some other way)
 *   invitation_accepted_at   when the owner set their password (null while the invitation is open)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('invited_at')->nullable()->after('email_verified_at');
            $table->timestamp('invitation_accepted_at')->nullable()->after('invited_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['invited_at', 'invitation_accepted_at']);
        });
    }
};
