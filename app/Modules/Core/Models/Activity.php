<?php

namespace App\Modules\Core\Models;

use Spatie\Activitylog\Models\Activity as SpatieActivity;

/**
 * One activity log entry, stored in the CURRENT company's database.
 * The package's own model would use the default (central) connection; this one uses "tenant".
 * Registered as "activity_model" in config/activitylog.php.
 */
class Activity extends SpatieActivity
{
    protected $connection = 'tenant';
}
