<?php

namespace App\Modules\Hrm\Models;

use App\Core\Models\TenantModel;
use App\Modules\Hrm\Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * A branch office (legacy `branches`).
 *
 * @property int $id
 * @property string $name
 * @property string|null $location
 * @property bool $is_active
 */
#[UseFactory(BranchFactory::class)]
class Branch extends TenantModel
{
    /** @use HasFactory<BranchFactory> */
    use HasFactory;

    protected $fillable = ['name', 'location', 'is_active'];

    protected $attributes = ['is_active' => true];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
