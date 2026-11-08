<?php

namespace App\Modules\Hrm\Models;

use App\Core\Models\TenantModel;
use App\Modules\Hrm\Enums\DocumentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A document of an employee: NID, passport, licence ... with an optional expiry date. [EMP-03]
 *
 *     $doc->name()                    "Passport", or the title for "Other"
 *     $doc->daysToExpiry()            -3 = expired 3 days ago, 12 = expires in 12 days, null = never
 *     EmployeeDocument::expiringWithin(30)->get()   expired or expiring in the next 30 days
 *
 * @property int $id
 * @property int $employee_id
 * @property DocumentType $type
 * @property string|null $title
 * @property string|null $number
 * @property Carbon|null $issue_date
 * @property Carbon|null $expiry_date
 * @property string|null $attachment_path
 * @property string|null $attachment_name
 */
class EmployeeDocument extends TenantModel
{
    /** Days before expiry when the profile starts warning. */
    public const WARN_DAYS = 30;

    protected $fillable = ['type', 'title', 'number', 'issue_date', 'expiry_date', 'attachment_path', 'attachment_name'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['type' => DocumentType::class, 'issue_date' => 'date', 'expiry_date' => 'date'];
    }

    public function name(): string
    {
        return $this->type === DocumentType::Other && $this->title ? $this->title : $this->type->label();
    }

    public function daysToExpiry(): ?int
    {
        return $this->expiry_date ? (int) today()->diffInDays($this->expiry_date, false) : null;
    }

    /**
     * Expired, or expiring within $days from today.
     *
     * @param  Builder<self>  $query
     */
    public function scopeExpiringWithin(Builder $query, int $days): void
    {
        $query->whereNotNull('expiry_date')->where('expiry_date', '<=', today()->addDays($days)->toDateString());
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
