<?php

use App\Modules\Hrm\Models\ProfileUpdateRequest;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * An employee asks to change their own personal details; the change is applied when the workflow
 * approves it (legacy `profileupdaterequests`). [EMP-08]
 *
 *   field_changes  JSON {field: {old, new}}: only the fields that change, with the value at request time
 *   applied_at  set by onApproved() when the new values are written to the employee
 *
 * Also registers the approval type "profile_update", so a workflow can be set up for it
 * (Workflow → Setup). Without an active workflow, requests cannot be sent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_update_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('requested_by_user_id')->nullable()->index();   // central users.id
            $table->json('field_changes');   // NOT "changes": Eloquent models already have a $changes property
            $table->string('reason', 500)->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
        });

        if (! DB::table('approval_types')->where('key', 'profile_update')->exists()) {
            DB::table('approval_types')->insert([
                'key' => 'profile_update',
                'name' => 'Profile update request',
                'model_class' => ProfileUpdateRequest::class,
                'is_chained' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('approval_types')->where('key', 'profile_update')->delete();
        Schema::dropIfExists('profile_update_requests');
    }
};
