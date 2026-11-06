<?php

namespace App\Modules\Core\Livewire;

use App\Core\Models\TenantModel;
use App\Modules\Core\Models\Activity;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * "History" tab: who changed a record, when, and what (old → new). [FND-06]
 *
 *     <livewire:core::history :subject="$role" can="core.roles.manage" :key="'history-'.$role->id" />
 *
 * subject: any TenantModel (they all write to the activity log).
 * can:     permission needed to see this history (normally the same as for editing the record).
 */
class History extends Component
{
    /** Number of entries shown; "Show more" adds PAGE more. */
    public const PAGE = 10;

    #[Locked]
    public string $subjectType = '';

    #[Locked]
    public int $subjectId = 0;

    #[Locked]
    public ?string $can = null;

    public int $limit = self::PAGE;

    public function mount(TenantModel $subject, ?string $can = null): void
    {
        $this->subjectType = $subject->getMorphClass();
        $this->subjectId = (int) $subject->getKey();
        $this->can = $can;
    }

    /** Runs on every request, also "Show more" clicks. */
    public function boot(): void
    {
        if ($this->can !== null) {
            Gate::authorize($this->can);
        }
    }

    public function showMore(): void
    {
        $this->limit += self::PAGE;
    }

    public function render(): View
    {
        if (! is_subclass_of($this->subjectType, TenantModel::class)) {
            throw new InvalidArgumentException('History is only available for tenant models.');
        }

        $query = Activity::query()
            ->where('subject_type', $this->subjectType)
            ->where('subject_id', $this->subjectId);

        $entries = (clone $query)->with('causer')->latest('id')->limit($this->limit)->get();

        return view('core::livewire.history', [
            'entries' => $entries->map(fn (Activity $activity) => [
                'who' => $activity->causer?->getAttribute('name') ?? __('System'),
                'event' => $this->eventLabel((string) $activity->event),
                'when' => $activity->created_at,
                'changes' => $this->changes($activity),
            ]),
            'hasMore' => $this->moreAfter($query, $entries),
        ]);
    }

    /**
     * The changed fields as rows: field label, old value, new value (both as text).
     *
     * @return list<array{field: string, old: string, new: string}>
     */
    private function changes(Activity $activity): array
    {
        $old = (array) ($activity->attribute_changes?->get('old') ?? []);
        $new = (array) ($activity->attribute_changes?->get('attributes') ?? []);
        $rows = [];

        foreach (array_unique([...array_keys($new), ...array_keys($old)]) as $field) {
            $rows[] = [
                'field' => __(Str::headline((string) $field)),
                'old' => $this->asText($old[$field] ?? null),
                'new' => $this->asText($new[$field] ?? null),
            ];
        }

        return $rows;
    }

    private function asText(mixed $value): string
    {
        return match (true) {
            $value === null, $value === '', $value === [] => '—',
            is_bool($value) => $value ? __('Yes') : __('No'),
            is_array($value) => implode(', ', array_map(fn ($item) => is_scalar($item) ? (string) $item : (string) json_encode($item), $value)),
            is_scalar($value) => $this->maybeDate((string) $value),
            default => (string) json_encode($value),
        };
    }

    /** Dates are stored like "2025-07-01T00:00:00.000000Z": show them in the company's date format. */
    private function maybeDate(string $value): string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}([ T]|$)/', $value) ? format_date($value) : $value;
    }

    private function eventLabel(string $event): string
    {
        return match ($event) {
            'created' => __('created this'),
            'updated' => __('changed this'),
            'deleted' => __('deleted this'),
            default => __($event),
        };
    }

    /**
     * @param  Builder<Activity>  $query
     * @param  Collection<int, Activity>  $shown
     */
    private function moreAfter(Builder $query, Collection $shown): bool
    {
        return $shown->count() === $this->limit && $query->count() > $this->limit;
    }
}
