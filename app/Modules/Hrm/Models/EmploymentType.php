<?php

namespace App\Modules\Hrm\Models;

use App\Core\Models\TenantModel;
use App\Modules\Hrm\Database\Factories\EmploymentTypeFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Permanent, Contract, Part time ... (legacy `employmenttype`). Also a workflow scope.
 *
 * @property int $id
 * @property string $name
 * @property bool $is_active
 */
#[UseFactory(EmploymentTypeFactory::class)]
class EmploymentType extends TenantModel
{
    /** @use HasFactory<EmploymentTypeFactory> */
    use HasFactory;

    protected $fillable = ['name', 'is_active'];

    protected $attributes = ['is_active' => true];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
