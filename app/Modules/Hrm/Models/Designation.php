<?php

namespace App\Modules\Hrm\Models;

use App\Core\Models\TenantModel;
use App\Modules\Hrm\Database\Factories\DesignationFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Job title on paper, e.g. "Senior Officer" (legacy `designations`).
 *
 * @property int $id
 * @property string|null $code
 * @property string $name
 * @property bool $is_active
 */
#[UseFactory(DesignationFactory::class)]
class Designation extends TenantModel
{
    /** @use HasFactory<DesignationFactory> */
    use HasFactory;

    protected $fillable = ['code', 'name', 'is_active'];

    protected $attributes = ['is_active' => true];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
