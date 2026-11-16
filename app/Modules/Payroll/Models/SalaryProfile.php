<?php

namespace App\Modules\Payroll\Models;

use App\Core\Models\TenantModel;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\Grade;
use App\Modules\Payroll\Database\Factories\SalaryProfileFactory;
use App\Modules\Payroll\Enums\ProfileValueType;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * A salary structure, usually per grade: what each head is worth next to the basic (legacy `salaryprofiles`).
 * Each employee can have one (employees.salary_profile_id). Work out the heads with SalaryProfileCalculator.
 *
 *     $profile->syncItems([$houseRent->id => ['value_type' => 'percent_basic', 'value' => '50'], ...])
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int|null $grade_id
 * @property string|null $detail
 * @property bool $is_active
 */
#[UseFactory(SalaryProfileFactory::class)]
class SalaryProfile extends TenantModel
{
    /** @use HasFactory<SalaryProfileFactory> */
    use HasFactory;

    protected $fillable = ['code', 'name', 'grade_id', 'detail', 'is_active'];

    protected $attributes = ['is_active' => true];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * The items as plain values, in head id order: [salary_head_id => ['value_type' => 'percent_basic', 'value' => '50.0000']].
     *
     * @return array<int, array{value_type: string, value: string}>
     */
    public function values(): array
    {
        return $this->items()->orderBy('salary_head_id')->get()
            ->mapWithKeys(fn (SalaryProfileItem $i) => [$i->salary_head_id => ['value_type' => $i->value_type->value, 'value' => $i->value()]])
            ->all();
    }

    /**
     * Makes the profile hold exactly these heads with these values: missing heads are removed, new ones added,
     * changed ones updated. ONE readable history entry on the profile (the item rows log nothing themselves),
     * like LeaveProfile::syncEntitlements().
     *
     * @param  array<int, array{value_type: string, value: string|int|float}>  $items  salary_head_id => value
     */
    public function syncItems(array $items): void
    {
        ksort($items);
        $old = $this->readable();

        $this->getConnection()->transaction(function () use ($items) {
            $this->items()->whereNotIn('salary_head_id', array_keys($items))->delete();
            foreach ($items as $headId => $item) {
                $type = ProfileValueType::from($item['value_type']);
                $this->items()->updateOrCreate(['salary_head_id' => $headId], [
                    'value_type' => $type,
                    'amount' => $type->usesRate() ? null : (string) $item['value'],
                    'rate' => $type->usesRate() ? (string) $item['value'] : null,
                ]);
            }
        });

        $new = $this->readable();
        if ($old !== $new) {
            activity()->performedOn($this)->event('updated')
                ->withChanges(['old' => ['items' => $old], 'attributes' => ['items' => $new]])
                ->log('updated');
        }
    }

    /** "HR 50% of basic · MED 750.00", for the list (eager load items.head there). */
    public function summary(): string
    {
        return $this->describe($this->loadMissing('items.head')->items) ?: '—';
    }

    /** The same, fresh from the database, for the History tab. */
    private function readable(): string
    {
        return $this->describe($this->items()->with('head')->get());
    }

    /** @param  Collection<int, SalaryProfileItem>  $items */
    private function describe(Collection $items): string
    {
        return $items
            ->sortBy(fn (SalaryProfileItem $i) => [$i->head->sequence, $i->head->name])
            ->map(fn (SalaryProfileItem $i) => $i->head->code.' '.$i->describe())
            ->implode(' · ');
    }

    /** @return HasMany<SalaryProfileItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(SalaryProfileItem::class);
    }

    /** @return BelongsTo<Grade, $this> */
    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    /** @return HasMany<Employee, $this> */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
