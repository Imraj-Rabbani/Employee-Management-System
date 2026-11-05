<?php

namespace App\Modules\Core\Models;

use App\Core\Models\TenantModel;

/**
 * One company setting (tenant DB). Use the Settings service instead of this model directly,
 * so the cache stays correct.
 *
 * @property int $id
 * @property string $key
 * @property mixed $value
 * @property bool $autoload
 */
class Setting extends TenantModel
{
    protected $fillable = ['key', 'value', 'autoload'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'value' => 'json',
            'autoload' => 'boolean',
        ];
    }
}
