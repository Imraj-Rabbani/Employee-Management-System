<?php

namespace App\Modules\Hrm\Livewire\Recruitment;

use App\Modules\Hrm\Actions\ImportApplicants;
use App\Modules\Hrm\Models\JobPosting;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use LogicException;

/**
 * Import applicants onto a job posting from a spreadsheet (modal "applicant-import"), e.g. the Google Sheet
 * of a Google Form downloaded as .csv / .xlsx. Opened with $dispatch('import-applicants').
 *
 *   On a posting's page:   <livewire:...applicant-import :posting-id="$posting->id" />  → that posting, fixed
 *   On the Applicants list: <livewire:...applicant-import />                         → HR chooses the posting
 *
 *   1. Posting (list only) + file: the file is read at once; columns are matched to fields by their headers.
 *      A guide lists the questions the Google Form should ask.
 *   2. Check: HR can change the matching; the preview shows what happens to each line and redraws with every change.
 *   3. Import: the usable lines go in (all or none), the pipeline / list redraws.
 *
 * The rules are in Hrm\Actions\ImportApplicants. Permission: both pages need hrm.recruitment.manage.
 */
class ApplicantImport extends Component
{
    use WithFileUploads;

    /** true on a posting's page: the posting cannot be changed. */
    #[Locked]
    public bool $postingFixed = false;

    /** The posting the people apply to ('' = not chosen yet). */
    public int|string $postingId = '';

    public ?TemporaryUploadedFile $file = null;

    #[Locked]
    public string $fileName = '';

    /** @var list<string> the file's header row */
    #[Locked]
    public array $headers = [];

    /** @var list<array{line: int, cells: list<string>}> */
    #[Locked]
    public array $rows = [];

    /** @var array<string, int|string> field => column number ('' = not used) */
    public array $mapping = [];

    public function mount(?int $postingId = null): void
    {
        $this->postingFixed = $postingId !== null;
        $this->postingId = $postingId ?? '';
    }

    #[On('import-applicants')]
    public function open(): void
    {
        $this->startOver();
        if (! $this->postingFixed) {
            $this->postingId = '';
        }
        Flux::modal('applicant-import')->show();
    }

    public function startOver(): void
    {
        $this->reset('file', 'fileName', 'headers', 'rows', 'mapping');
        $this->resetValidation();
    }

    /** A file was chosen: read it now, so the next step can show its columns. */
    public function updatedFile(ImportApplicants $import): void
    {
        $this->validate(['file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:5120']], [], ['file' => __('file')]);

        try {
            $sheet = $import->read((string) $this->file?->getRealPath(), (string) $this->file?->getClientOriginalExtension());
        } catch (LogicException $e) {
            $this->addError('file', $e->getMessage());

            return;
        }

        $this->fileName = (string) $this->file?->getClientOriginalName();
        $this->headers = $sheet['headers'];
        $this->rows = $sheet['rows'];
        $guessed = $import->guessMapping($this->headers);
        $fields = array_keys(ImportApplicants::fields());
        $this->mapping = array_combine($fields, array_map(fn (string $field) => $guessed[$field] ?? '', $fields));
        $this->reset('file');   // the cells are kept; the uploaded file is not needed any more
    }

    public function import(ImportApplicants $import): void
    {
        $posting = $this->posting();
        if ($posting === null) {
            $this->addError('postingId', __('Choose the job posting they apply to.'));

            return;
        }
        if (($this->mapping['first_name'] ?? '') === '') {
            $this->addError('mapping.first_name', __('Choose the column with the name.'));

            return;
        }

        try {
            $counts = $import->handle($this->rows, $this->mapping, $posting, Auth::id());
        } catch (LogicException $e) {
            $this->addError('import', $e->getMessage());

            return;
        }

        Flux::modal('applicant-import')->close();
        $this->startOver();
        $this->dispatch('applicants-imported');
        $this->dispatch('toast', variant: 'success', message: implode(' ', array_filter([
            trans_choice(':count new applicant added.|:count new applicants added.', $counts['added']),
            $counts['existing'] > 0 ? trans_choice(':count already entered person applied.|:count already entered people applied.', $counts['existing']) : null,
            $counts['skipped'] > 0 ? trans_choice(':count line skipped.|:count lines skipped.', $counts['skipped']) : null,
            __('Onto :no.', ['no' => $posting->number()]),
        ])));
    }

    /**
     * The chosen posting, or null. On the posting's page: that one (even when it stopped taking applications,
     * so the window can say why). On the list: only one of postingOptions().
     */
    private function posting(): ?JobPosting
    {
        if ($this->postingId === '') {
            return null;
        }
        if ($this->postingFixed) {
            return JobPosting::query()->visibleTo(Auth::user())->findOrFail((int) $this->postingId);
        }

        return array_key_exists((int) $this->postingId, $this->postingOptions())
            ? JobPosting::query()->visibleTo(Auth::user())->find((int) $this->postingId)
            : null;
    }

    /**
     * Postings the user can see that take applications today. "JOB-0003 · Sales Officer (Sales)"
     *
     * @return array<int, string>
     */
    private function postingOptions(): array
    {
        return JobPosting::query()->visibleTo(Auth::user())->where('status', 'open')
            ->with('manpowerRequest.entity')->orderByDesc('id')->get()
            ->filter(fn (JobPosting $p) => $p->isAcceptingApplications())
            ->mapWithKeys(fn (JobPosting $p) => [$p->id => $p->number().' · '.$p->title.' ('.($p->manpowerRequest->entity->name ?? '?').')'])
            ->all();
    }

    public function render(ImportApplicants $import): View
    {
        $posting = $this->posting();
        $results = $posting === null || $this->headers === [] || ($this->mapping['first_name'] ?? '') === ''
            ? [] : $import->check($this->rows, $this->mapping, $posting);
        $counts = array_count_values(array_column($results, 'status'));

        return view('hrm::livewire.recruitment.applicant-import', [
            'posting' => $posting,
            'postings' => $this->postingFixed ? [] : $this->postingOptions(),
            'fields' => ImportApplicants::fields(),
            'questions' => ImportApplicants::formQuestions(),
            'results' => $results,
            'counts' => ['new' => 0, 'existing' => 0, 'skip' => 0, 'error' => 0, ...$counts],
            'problem' => $posting ? $import->problem($posting) : null,
        ]);
    }
}
