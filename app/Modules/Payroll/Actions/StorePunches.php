<?php

namespace App\Modules\Payroll\Actions;

use App\Modules\Payroll\Enums\PunchSource;
use App\Modules\Payroll\Models\PunchLog;
use Illuminate\Support\Carbon;

/**
 * Saves a list of punches from a device or a CSV upload. [TA-04]
 *
 *   - skips punches that are already stored (same code + time; for a device also the same device)
 *   - links them to employees by access code (PunchLog::linkEmployees)
 *   - written in bulk (insertOrIgnore), and without activity-log entries (see PunchLog)
 *
 * Returns how many NEW punches were stored.
 */
class StorePunches
{
    /**
     * @param  list<array{code: string, punched_at: Carbon}>  $punches
     */
    public function handle(array $punches, PunchSource $source, ?int $deviceId = null, ?string $batch = null, ?int $userId = null): int
    {
        if ($punches === []) {
            return 0;
        }

        // Same code + time twice in one list (e.g. the same row twice in a file): keep one.
        $rows = [];
        foreach ($punches as $p) {
            $at = $p['punched_at']->copy()->startOfSecond()->format('Y-m-d H:i:s');
            $rows[$p['code'].'|'.$at] = ['access_code' => $p['code'], 'punched_at' => $at];
        }

        // Without a device, the unique key (device_id, access_code, punched_at) cannot catch duplicates (NULL device),
        // so look for what is already stored.
        if ($deviceId === null) {
            $existing = PunchLog::query()
                ->whereIn('access_code', array_unique(array_column($rows, 'access_code')))
                ->whereBetween('punched_at', [min(array_column($rows, 'punched_at')), max(array_column($rows, 'punched_at'))])
                ->get(['access_code', 'punched_at'])
                ->mapWithKeys(fn (PunchLog $p) => [$p->access_code.'|'.$p->punched_at->format('Y-m-d H:i:s') => true]);
            $rows = array_diff_key($rows, $existing->all());
        }

        $now = now();
        $stored = 0;
        foreach (array_chunk(array_values($rows), 500) as $chunk) {
            $stored += PunchLog::query()->insertOrIgnore(array_map(fn (array $row) => [
                ...$row,
                'source' => $source->value,
                'device_id' => $deviceId,
                'batch' => $batch,
                'created_by_user_id' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ], $chunk));
        }

        PunchLog::linkEmployees();

        return $stored;
    }
}
