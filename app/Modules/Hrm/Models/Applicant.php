<?php

namespace App\Modules\Hrm\Models;

use App\Core\Models\TenantModel;
use App\Modules\Hrm\Database\Factories\ApplicantFactory;
use App\Modules\Hrm\Enums\Gender;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A person who applied for a job, entered by HR (the company's talent pool). [REC-03]
 *
 *     Applicant::normalizePhone('+880 1711-000000')    '01711000000'
 *     Applicant::duplicatesOf($email, $phone)          people already entered with that email or phone
 *     $applicant->applications                         one per posting they applied to
 *
 * email / phone are normalised whenever they are SET (the Attribute mutators below), so the unique
 * indexes and the duplicate check compare like with like.
 *
 * @property int $id
 * @property string $first_name
 * @property string|null $last_name
 * @property-read string $name
 * @property string|null $email
 * @property string|null $phone
 * @property Gender|null $gender
 * @property Carbon|null $birth_date
 * @property string|null $national_id
 * @property string|null $address
 * @property string|null $current_employer
 * @property string|null $current_designation
 * @property string|null $expected_salary
 * @property string|null $cv_path
 * @property string|null $cv_name
 * @property string|null $notes
 * @property int|null $created_by_user_id
 * @property Carbon $created_at
 */
#[UseFactory(ApplicantFactory::class)]
class Applicant extends TenantModel
{
    /** @use HasFactory<ApplicantFactory> */
    use HasFactory;

    protected $fillable = [
        'first_name', 'last_name', 'email', 'phone', 'gender', 'birth_date', 'national_id', 'address',
        'current_employer', 'current_designation', 'expected_salary', 'notes', 'created_by_user_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'birth_date' => 'date',
            'expected_salary' => 'decimal:2',
        ];
    }

    // ---- normalising email / phone -----------------------------------------------------------

    /** "  Rahim@Mail.COM " → "rahim@mail.com"; empty → null. */
    public static function normalizeEmail(?string $email): ?string
    {
        $email = mb_strtolower(trim((string) $email));

        return $email === '' ? null : $email;
    }

    /**
     * Digits only, and a Bangladesh country code dropped so all ways of writing a number match:
     * "+880 1711-000000", "8801711000000", "01711 000000" → "01711000000". Empty → null.
     */
    public static function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';
        if (str_starts_with($digits, '880') && strlen($digits) === 13) {
            $digits = substr($digits, 2);   // 880 1711000000 → 0 1711000000
        }

        return $digits === '' ? null : $digits;
    }

    /** @return Attribute<string|null, string|null> */
    protected function email(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => self::normalizeEmail($value));
    }

    /** @return Attribute<string|null, string|null> */
    protected function phone(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => self::normalizePhone($value));
    }

    /** @return Attribute<string, never> */
    protected function name(): Attribute
    {
        return Attribute::make(get: fn () => trim($this->first_name.' '.$this->last_name));
    }

    /**
     * People already entered with this email OR phone (normalised first), except $exceptId.
     *
     * @return Collection<int, self>
     */
    public static function duplicatesOf(?string $email, ?string $phone, ?int $exceptId = null): Collection
    {
        $email = self::normalizeEmail($email);
        $phone = self::normalizePhone($phone);

        if ($email === null && $phone === null) {
            return new Collection;
        }

        return self::query()
            ->where(function ($q) use ($email, $phone) {
                $q->when($email, fn ($q) => $q->orWhere('email', $email))
                    ->when($phone, fn ($q) => $q->orWhere('phone', $phone));
            })
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->get();
    }

    // ---- relations -----------------------------------------------------------------------------

    /** @return HasMany<ApplicantEducation, $this> */
    public function educations(): HasMany
    {
        return $this->hasMany(ApplicantEducation::class)->orderByDesc('passing_year');
    }

    /** @return HasMany<ApplicantWorkHistory, $this> */
    public function workHistories(): HasMany
    {
        return $this->hasMany(ApplicantWorkHistory::class)->orderByDesc('from_date');
    }

    /** @return HasMany<Application, $this> */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class)->latest('applied_on')->latest('id');
    }
}
