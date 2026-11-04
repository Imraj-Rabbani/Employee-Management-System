<?php

namespace Tests\Fixtures;

use App\Core\Models\TenantModel;

/**
 * Test-only tenant model, used until real tenant models (employees...) exist.
 * Its "notes" table is created in each test tenant database by the tests.
 */
class Note extends TenantModel
{
    public $timestamps = false;

    protected $fillable = ['text'];
}
