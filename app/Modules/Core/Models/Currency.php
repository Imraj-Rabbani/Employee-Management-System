<?php

namespace App\Modules\Core\Models;

use App\Core\Models\TenantModel;
use App\Modules\Core\Database\Factories\CurrencyFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * A currency the company works with (legacy `currencies`). [ORG-01]
 *
 *     Currency::defaultCode() "BDT": the company's default currency (setting "currency", BDT if not chosen yet)
 *     Currency::default()     its row, or null
 *
 * @property int $id
 * @property string $code ISO 4217, e.g. BDT
 * @property string $name
 * @property string|null $symbol
 * @property string $exchange_rate decimal string, e.g. "1.000000"
 * @property bool $is_active
 */
#[UseFactory(CurrencyFactory::class)]
class Currency extends TenantModel
{
    /** @use HasFactory<CurrencyFactory> */
    use HasFactory;

    protected $fillable = ['code', 'name', 'symbol', 'exchange_rate', 'is_active'];

    protected $attributes = ['exchange_rate' => 1, 'is_active' => true];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['exchange_rate' => 'decimal:6', 'is_active' => 'boolean'];
    }

    public static function defaultCode(): string
    {
        return (string) (settings('currency') ?: 'BDT');
    }

    public static function default(): ?self
    {
        return static::where('code', static::defaultCode())->first();
    }
}
