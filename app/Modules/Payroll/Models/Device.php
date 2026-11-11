<?php

namespace App\Modules\Payroll\Models;

use App\Core\Models\TenantModel;
use App\Modules\Hrm\Models\OrgUnit;
use App\Modules\Payroll\Database\Factories\DeviceFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A ZKTeco access-control device. Its punches are pulled every 5 minutes (SyncDevicePunches, 7.3d). [TA-04]
 *
 * @property int $id
 * @property string $name
 * @property string|null $serial_number
 * @property string $ip
 * @property int $port
 * @property int|null $org_unit_id
 * @property bool $is_active
 * @property Carbon|null $last_synced_at
 * @property string|null $last_sync_error
 */
#[UseFactory(DeviceFactory::class)]
class Device extends TenantModel
{
    /** @use HasFactory<DeviceFactory> */
    use HasFactory;

    protected $fillable = ['name', 'serial_number', 'ip', 'port', 'org_unit_id', 'is_active'];

    protected $attributes = ['is_active' => true, 'port' => 4370];

    /** Sync bookkeeping changes every 5 minutes: not worth a history entry each time. */
    protected array $auditExcept = ['last_synced_at', 'last_sync_error'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'last_synced_at' => 'datetime'];
    }

    /** @return BelongsTo<OrgUnit, $this> */
    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class);
    }

    /** @return HasMany<PunchLog, $this> */
    public function punches(): HasMany
    {
        return $this->hasMany(PunchLog::class);
    }
}
