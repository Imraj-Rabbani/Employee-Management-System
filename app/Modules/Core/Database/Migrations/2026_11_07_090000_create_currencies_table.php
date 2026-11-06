<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TENANT table: currencies the company works with (legacy `currencies`). [ORG-01, FND-03]
 * The company's DEFAULT currency is still the "currency" setting (Company Settings);
 * this table adds the symbol and an exchange rate against that default (default = 1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->char('code', 3)->unique();          // ISO 4217, e.g. BDT
            $table->string('name', 100);
            $table->string('symbol', 10)->nullable();   // ৳
            // 1 unit of this currency = X units of the default currency. Decimal, not float: no rounding surprises.
            $table->decimal('exchange_rate', 18, 6)->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
