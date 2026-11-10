<?php

namespace App\Modules\Core\Database\Seeders;

use App\Modules\Core\Models\Currency;
use Illuminate\Database\Seeder;

/**
 * Makes sure the company's default currency (setting "currency", BDT if not chosen yet) has a row, rate 1.
 */
class CurrencySeeder extends Seeder
{
    /** Symbols for the currencies in config/currencies.php. */
    private const SYMBOLS = [
        'BDT' => '৳', 'INR' => '₹', 'PKR' => 'Rs', 'LKR' => 'Rs', 'NPR' => 'Rs', 'KES' => 'KSh', 'UGX' => 'USh',
        'TZS' => 'TSh', 'AED' => 'د.إ', 'SAR' => '﷼', 'MYR' => 'RM', 'SGD' => 'S$', 'USD' => '$', 'EUR' => '€', 'GBP' => '£',
    ];

    public function run(): void
    {
        $code = Currency::defaultCode();

        Currency::firstOrCreate(['code' => $code], [
            'name' => config("currencies.{$code}", $code),
            'symbol' => self::SYMBOLS[$code] ?? null,
            'exchange_rate' => 1,
        ]);
    }
}
