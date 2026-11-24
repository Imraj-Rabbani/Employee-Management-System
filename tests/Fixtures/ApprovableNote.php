<?php

namespace Tests\Fixtures;

use App\Core\Models\TenantModel;
use App\Modules\Workflow\Concerns\HasApprovals;
use App\Modules\Workflow\Contracts\Approvable;
use App\Modules\Workflow\Models\Approval;

/**
 * Test-only approvable document, used until real ones (leave, loans ...) exist.
 * Uses the "notes" table from tests/Fixtures/TenantMigrations.
 */
class ApprovableNote extends TenantModel implements Approvable
{
    use HasApprovals;

    protected $table = 'notes';

    public $timestamps = false;

    protected $fillable = ['text'];

    /**
     * What approvalContext() returns; tests set it, e.g. ['entity_ids' => [2], 'amount' => 900].
     *
     * @var array{employee_id?: int|null, entity_ids?: list<int>, employment_type_id?: int|null, amount?: float|int|string|null}
     */
    public array $context = [];

    /**
     * Context by note id, for a note the engine loads again from the database (respond() asking the
     * next level): there $context is empty. Tests set it with ApprovableNote::$contexts[$note->id] = [...].
     *
     * @var array<int, array<string, mixed>>
     */
    public static array $contexts = [];

    /**
     * Every onApproved / onRejected call, e.g. ['approved:3'], so tests can check it ran exactly once.
     *
     * @var list<string>
     */
    public static array $handled = [];

    public function approvalType(): string
    {
        return 'note';
    }

    public function approvalSubject(): string
    {
        return 'Note: '.$this->getAttribute('text');
    }

    public function approvalContext(): array
    {
        return $this->context ?: (static::$contexts[$this->getKey()] ?? []);
    }

    public function onApproved(Approval $final): void
    {
        if ($this->getAttribute('text') === 'explode') {
            throw new \RuntimeException('The handler failed.');   // tests the rollback
        }

        static::$handled[] = 'approved:'.$this->getKey();
        $this->update(['text' => 'approved']);
    }

    public function onRejected(Approval $final): void
    {
        static::$handled[] = 'rejected:'.$this->getKey();
        $this->update(['text' => 'rejected']);
    }
}
