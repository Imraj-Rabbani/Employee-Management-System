<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Workflow redesign (2026-11-24, the user's design):
 *
 *   - The approval types (leave, manpower request ...) are registered by the code (each module's migration);
 *     the screen only CONFIGURES them. Each type has ONE workflow = its levels and settings.
 *     → workflows.approval_type_id becomes unique; the scope columns (entity, employment type,
 *       minimum amount) and the workflow's own name go.
 *   - Approver of a level: Employee's Reporting Supervisor, Employee's Entity Head (of a type),
 *     Specific Entity Head (one chosen entity), Specific People (one chosen employee).
 *     "Anyone with a role" is gone → approver_role_id goes, approver_entity_id is new.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Keep one workflow per type: the active one, else the most general one, else the oldest.
        //    The others are removed (their levels go with them: cascade); one that has approval
        //    history cannot be removed, so the migration stops with a message instead.
        foreach (DB::table('workflows')->pluck('approval_type_id')->unique() as $typeId) {
            $workflows = DB::table('workflows')->where('approval_type_id', $typeId)
                ->orderByDesc('is_active')
                ->orderByRaw('(scope_entity_id IS NOT NULL) + (scope_employment_type_id IS NOT NULL) + (min_amount IS NOT NULL)')
                ->orderBy('id')
                ->pluck('id');

            foreach ($workflows->slice(1) as $extraId) {
                $used = DB::table('approvals')
                    ->whereIn('workflow_step_id', DB::table('workflow_steps')->where('workflow_id', $extraId)->select('id'))
                    ->exists();
                if ($used) {
                    throw new RuntimeException("Workflow {$extraId} has approval history; merge it by hand before migrating.");
                }
                DB::table('workflows')->where('id', $extraId)->delete();
            }
        }

        // 2. "Anyone with a role" levels become "Specific People" with nobody chosen yet: the
        //    administrator picks the person on the Workflow screen (history of those levels stays).
        DB::table('workflow_steps')->where('approver_type', 'role')
            ->update(['approver_type' => 'employee', 'approver_employee_id' => null, 'approver_role_id' => null]);

        Schema::table('workflows', function (Blueprint $table) {
            $table->dropForeign(['scope_entity_id']);
            $table->dropForeign(['scope_employment_type_id']);
            $table->dropColumn(['name', 'scope_entity_id', 'scope_employment_type_id', 'min_amount']);
            $table->unique('approval_type_id');
        });

        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approver_role_id');

            // Filled only when approver_type = specific_entity_head, e.g. Branch "Gulshan".
            $table->foreignId('approver_entity_id')->nullable()->after('approver_entity_type_id')
                ->constrained('entities')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approver_entity_id');
            $table->foreignId('approver_role_id')->nullable()->after('approver_employee_id')->constrained('roles')->nullOnDelete();
        });

        DB::table('workflow_steps')->where('approver_type', 'specific_entity_head')
            ->update(['approver_type' => 'entity_head']);

        Schema::table('workflows', function (Blueprint $table) {
            // The foreign key on approval_type_id uses this index too: drop the FK, then the unique, then add the FK back.
            $table->dropForeign(['approval_type_id']);
            $table->dropUnique(['approval_type_id']);
            $table->foreign('approval_type_id')->references('id')->on('approval_types')->restrictOnDelete();

            $table->string('name', 100)->default('')->after('approval_type_id');
            $table->foreignId('scope_entity_id')->nullable()->after('name')->constrained('entities')->restrictOnDelete();
            $table->foreignId('scope_employment_type_id')->nullable()->after('scope_entity_id')->constrained('employment_types')->restrictOnDelete();
            $table->decimal('min_amount', 15, 2)->nullable()->after('scope_employment_type_id');
        });
    }
};
