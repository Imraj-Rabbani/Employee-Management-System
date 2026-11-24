<?php

namespace App\Core\Support;

use RuntimeException;

/** Thrown by TenantUploads when a file would take the company over its plan storage (PlanLimits). */
class StorageLimitReached extends RuntimeException {}
