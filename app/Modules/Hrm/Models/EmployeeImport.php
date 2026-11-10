<?php

namespace App\Modules\Hrm\Models;

use App\Core\Models\TenantModel;
use App\Models\User;
use App\Modules\Hrm\Enums\ImportStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An uploaded Excel file of new employees and what happened to it (5.6). [EMP-06]
 *
 * @property int $id
 * @property int $user_id
 * @property string $file_path
 * @property string $file_name
 * @property ImportStatus $status
 * @property int $total_rows
 * @property int $imported_rows
 * @property list<array{row: int, column: string, message: string}>|null $errors
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon $created_at
 */
class EmployeeImport extends TenantModel
{
    protected $fillable = ['user_id', 'file_path', 'file_name', 'status', 'total_rows', 'imported_rows', 'errors', 'started_at', 'finished_at'];

    /** The error list can be long: keep it out of the activity log. */
    protected array $auditExcept = ['errors'];

    protected $attributes = ['status' => 'pending'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ImportStatus::class,
            'errors' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * The uploader (central database).
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
