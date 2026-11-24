<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Fiscal years and currencies were tables with their own pages. They are now two plain
 * company settings ("fiscal_year", "currency") in Global Config → Company settings, so the tables go.
 * (Nothing else used them.) The create migrations were removed, so new companies never get them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('fiscal_years');
        Schema::dropIfExists('currencies');
    }

    public function down(): void
    {
        // Not brought back: the old tables were replaced by settings.
    }
};
