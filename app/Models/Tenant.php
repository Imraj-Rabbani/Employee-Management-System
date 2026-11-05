<?php

namespace App\Models;

use App\Modules\Core\Models\FiscalYear;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * A company using the app. Lives in the central database (ems_clients).
 * Each tenant has its own database, named in $database (e.g. ems_tenant_sss).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $database
 * @property string|null $db_host
 * @property int|null $db_port
 * @property string|null $db_username
 * @property string|null $db_password
 * @property list<string>|null $modules
 * @property array<string, mixed>|null $plugins
 * @property bool $is_active
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    /** Always the central database, whichever connection is the default. */
    protected $connection = 'central';

    /**
     * Modules a super admin can switch on or off per company (key => label).
     * Core (settings, users, roles) and Self Service are always on, so they are not listed.
     */
    public const OPTIONAL_MODULES = [
        'hrm' => 'HRM',
        'payroll' => 'Payroll',
        'workflow' => 'Workflow',
    ];

    protected $fillable = [
        'code',
        'name',
        'database',
        'db_host',
        'db_port',
        'db_username',
        'db_password',
        'modules',
        'plugins',
        'is_active',
        'expires_at',
    ];

    /** Never include the DB password when a tenant is turned into an array/JSON. */
    protected $hidden = [
        'db_password',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'db_password' => 'encrypted',
            'modules' => 'array',
            'plugins' => 'array',
            'is_active' => 'boolean',
            'expires_at' => 'date',
        ];
    }

    /**
     * Settings for the "tenant" connection when this tenant is active: the base
     * settings (the original "tenant" connection from config/database.php) with this
     * tenant's database, and its own host/login if set, filled in.
     *
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>
     */
    public function connectionConfig(array $base): array
    {
        return array_merge($base, array_filter([
            'database' => $this->database,
            'host' => $this->db_host,
            'port' => $this->db_port,
            'username' => $this->db_username,
            'password' => $this->db_password,
        ], fn ($value) => $value !== null && $value !== ''));
    }

    /**
     * Can this company use the module? Modules that are not optional (core) are always on.
     * "hrm", "payroll", "workflow" must be switched on by the super admin (Platform admin → Edit company).
     */
    public function hasModule(string $module): bool
    {
        if (! array_key_exists($module, self::OPTIONAL_MODULES)) {
            return true;
        }

        return in_array($module, $this->modules ?? [], true);
    }

    /**
     * Why this company's users may not work right now, or null when they may.
     * Used at login (FortifyServiceProvider) and on every request (IdentifyTenant).
     */
    public function accessProblem(): ?string
    {
        if (! $this->is_active) {
            return __('This company account is inactive.');
        }

        if ($this->expires_at?->isPast()) {
            return __('This company subscription has expired.');
        }

        return null;
    }

    /**
     * This company's active fiscal year (from its own database). Only works for the current tenant,
     * because only the current tenant's database is connected.
     */
    public function activeFiscalYear(): ?FiscalYear
    {
        if (tenant()?->id !== $this->id) {
            throw new LogicException("Tenant [{$this->code}] is not the current tenant.");
        }

        return FiscalYear::active();
    }

    /** The database name every new tenant gets: ems_tenant_{code}. */
    public static function databaseNameFor(string $code): string
    {
        return 'ems_tenant_'.strtolower($code);
    }

    /**
     * The logins of this company.
     *
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
