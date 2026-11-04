<?php

namespace App\Models;

use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A company using the app. Lives in the central database (ems_clients).
 * Each tenant has its own database, named in $database (e.g. ems_tenant_sss).
 */
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    /** Always the central database, whichever connection is the default. */
    protected $connection = 'central';

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
     * Settings for the "tenant" connection when this tenant is active: the tenant
     * connection from config/database.php, with this tenant's database (and its own
     * host/login, if set) filled in.
     *
     * @return array<string, mixed>
     */
    public function connectionConfig(): array
    {
        /** @var array<string, mixed> $base */
        $base = config('database.connections.tenant');

        return array_merge($base, array_filter([
            'database' => $this->database,
            'host' => $this->db_host,
            'port' => $this->db_port,
            'username' => $this->db_username,
            'password' => $this->db_password,
        ], fn ($value) => $value !== null && $value !== ''));
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
