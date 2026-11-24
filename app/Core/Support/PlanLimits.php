<?php

namespace App\Core\Support;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use LogicException;

/**
 * The current company's plan limits (App\Core\Enums\Plan): how many employees, how much storage.
 *
 *     $limits = app(PlanLimits::class);
 *     if ($problem = $limits->employeeProblem()) { ...show $problem... }          adding 1 employee
 *     if ($problem = $limits->employeeProblem(adding: 25)) { ... }                an import of 25 rows
 *     if ($problem = $limits->storageProblem($file->getSize())) { ... }           before saving a file
 *     $limits->employeeCount();  $limits->storageUsed();                          for "usage" displays
 *
 * Every employee record counts (the founder included, people who have left too: delete a record to
 * free its place). Storage = all files in the company's folder (storage/app/tenants/{code}).
 * Use inside a company (tenant()); with none active it throws.
 */
class PlanLimits
{
    public function employeeCount(): int
    {
        // Straight from the table: no "visible to me" scope, every employee counts.
        return DB::connection('tenant')->table('employees')->count();
    }

    public function employeesLeft(): int
    {
        return max(0, $this->tenant()->plan->maxEmployees() - $this->employeeCount());
    }

    /** Why $adding more employees may not be added, or null when they fit. */
    public function employeeProblem(int $adding = 1): ?string
    {
        $plan = $this->tenant()->plan;

        if ($this->employeeCount() + $adding <= $plan->maxEmployees()) {
            return null;
        }

        return __('Your :plan plan allows up to :max employees and you have :count. Upgrade your plan to add more.', [
            'plan' => $plan->label(),
            'max' => $plan->maxEmployees(),
            'count' => $this->employeeCount(),
        ]);
    }

    /** Bytes used by the company's uploaded files. */
    public function storageUsed(): int
    {
        $disk = Storage::disk('tenant');
        $total = 0;
        foreach ($disk->allFiles() as $file) {
            $total += $disk->size($file);
        }

        return $total;
    }

    /** Why a file of $bytes may not be saved, or null when it fits. */
    public function storageProblem(int $bytes): ?string
    {
        $plan = $this->tenant()->plan;

        if ($this->storageUsed() + $bytes <= $plan->storageBytes()) {
            return null;
        }

        return __('Your :plan plan includes :gb GB of storage and it is full. Delete some files or upgrade your plan.', [
            'plan' => $plan->label(),
            'gb' => $plan->storageGb(),
        ]);
    }

    /** "1.4 GB", "350 MB", "12 KB" */
    public static function formatBytes(int $bytes): string
    {
        foreach (['GB' => 1024 ** 3, 'MB' => 1024 ** 2, 'KB' => 1024] as $unit => $size) {
            if ($bytes >= $size) {
                return round($bytes / $size, 1).' '.$unit;
            }
        }

        return $bytes.' B';
    }

    private function tenant(): Tenant
    {
        return tenant() ?? throw new LogicException('Plan limits need a company (tenant) to be active.');
    }
}
