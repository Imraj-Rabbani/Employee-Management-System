<?php

namespace App\Modules\Hrm\Models;

use App\Core\Models\TenantModel;
use App\Modules\Hrm\Database\Factories\CostCenterFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Where salary cost is booked (legacy `costcenters`). account_code feeds the accounting export (12.4).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $function_code
 * @property string|null $account_code
 * @property bool $is_active
 */
#[UseFactory(CostCenterFactory::class)]
class CostCenter extends TenantModel
{
    /** @use HasFactory<CostCenterFactory> */
    use HasFactory;

    protected $fillable = ['code', 'name', 'function_code', 'account_code', 'is_active'];

    protected $attributes = ['is_active' => true];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
