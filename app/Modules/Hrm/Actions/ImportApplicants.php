<?php

namespace App\Modules\Hrm\Actions;

use App\Modules\Hrm\Enums\Gender;
use App\Modules\Hrm\Models\Applicant;
use App\Modules\Hrm\Models\JobPosting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

/**
 * Applicants from a spreadsheet (e.g. the Google Sheet behind a Google Form), onto ONE job posting.
 *
 *     $import = app(ImportApplicants::class);
 *     ['headers' => $h, 'rows' => $rows] = $import->read($path, 'csv');   // the file's cells, as text
 *     $mapping = $import->guessMapping($h);       // ['email' => 2, 'phone' => 3, ...] (column numbers)
 *     $results = $import->check($rows, $mapping, $posting);   // the preview: what happens to each line
 *     $counts = $import->handle($rows, $mapping, $posting, auth()->id());
 *
 * Each line becomes one of:
 *   new        a person not entered yet: created, then applied to the posting
 *   existing   same email or phone as someone entered: THAT person is applied (their details are not changed)
 *   skip       already applied to this posting, or the same person as an earlier line
 *   error      cannot be used (no name, no email and no phone, an email that is not an email ...)
 * Optional values that cannot be read (a date like "next year") are left empty with a warning; the line still goes in.
 */
class ImportApplicants
{
    public const MAX_ROWS = 1000;

    /**
     * Applicant fields that can be filled from a column, in the order shown. 'first_name' is required.
     *
     * @return array<string, string>
     */
    public static function fields(): array
    {
        return [
            'first_name' => __('Name (or first name)'),
            'last_name' => __('Last name'),
            'email' => __('Email'),
            'phone' => __('Phone'),
            'gender' => __('Gender'),
            'birth_date' => __('Date of birth'),
            'national_id' => __('National ID'),
            'address' => __('Address'),
            'current_employer' => __('Current employer'),
            'current_designation' => __('Current designation'),
            'expected_salary' => __('Expected salary'),
            'cv_url' => __('CV link'),
            'notes' => __('Notes'),
        ];
    }

    /**
     * The questions a Google Form should ask, so every column is matched by itself on import.
     * field => [question title, Google Forms question type, required?]. Shown as the guide in the Import window.
     *
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function formQuestions(): array
    {
        return [
            'first_name' => [__('Full name'), __('Short answer'), __('Required')],
            'email' => [__('Email'), __('Short answer, response validation: Text → Email'), __('Email or phone required')],
            'phone' => [__('Phone number'), __('Short answer'), __('Email or phone required')],
            'gender' => [__('Gender'), __('Multiple choice: Male, Female'), __('Optional')],
            'birth_date' => [__('Date of birth'), __('Date'), __('Optional')],
            'national_id' => [__('National ID'), __('Short answer'), __('Optional')],
            'address' => [__('Present address'), __('Paragraph'), __('Optional')],
            'current_employer' => [__('Current employer'), __('Short answer'), __('Optional')],
            'current_designation' => [__('Current designation'), __('Short answer'), __('Optional')],
            'expected_salary' => [__('Expected salary (monthly)'), __('Short answer, response validation: Number'), __('Optional')],
            'cv_url' => [__('Upload your CV'), __('File upload: PDF or document, 1 file'), __('Optional (applicants must sign in to Google)')],
            'notes' => [__('Anything else you want us to know?'), __('Paragraph'), __('Optional')],
        ];
    }

    /** Longest text kept per field (the column sizes). Longer text is cut, with a warning. */
    private const MAX_LENGTH = [
        'first_name' => 100, 'last_name' => 100, 'national_id' => 30, 'address' => 500,
        'current_employer' => 200, 'current_designation' => 100, 'cv_url' => 500, 'notes' => 5000,
    ];

    /**
     * Words that tell which column is which (Google Form headers are the questions: "Full name", "Email address" ...).
     * Fields are tried in THIS order, so "Email address" is taken by email before address looks for "address".
     */
    private const GUESSES = [
        'email' => ['email', 'e-mail'],
        'phone' => ['phone', 'mobile', 'contact number', 'cell'],
        'last_name' => ['last name', 'surname', 'family name'],
        'first_name' => ['full name', 'first name', 'your name', 'name'],
        'cv_url' => ['cv', 'resume', 'résumé'],
        'expected_salary' => ['expected salary', 'salary'],
        'birth_date' => ['date of birth', 'birth date', 'birthday', 'dob'],
        'national_id' => ['national id', 'nid'],
        'gender' => ['gender', 'sex'],
        'current_employer' => ['current employer', 'current company', 'employer', 'company', 'organization', 'organisation'],
        'current_designation' => ['current designation', 'designation', 'job title', 'current position'],
        'address' => ['present address', 'address'],
        'notes' => ['notes', 'note', 'comments', 'anything else'],
    ];

    /**
     * The file's first sheet as text. The first non-empty line is the header row; empty lines are left out.
     *
     * @return array{headers: list<string>, rows: list<array{line: int, cells: list<string>}>}
     *
     * @throws LogicException when the file cannot be read, has no header row or too many lines
     */
    public function read(string $path, string $extension): array
    {
        try {
            $reader = IOFactory::createReader(match (strtolower($extension)) {
                'csv', 'txt' => 'Csv',
                'xls' => 'Xls',
                default => 'Xlsx',
            });
            $reader->setReadDataOnly(true);
            if ($reader instanceof Csv) {
                // Keep "01711000000" as text: the default binder would make it the number 1711000000.
                $reader->setValueBinder(new StringValueBinder);
            }
            $cells = $reader->load($path)->getActiveSheet()->toArray(null, true, false, false);
        } catch (Throwable) {
            throw new LogicException(__('The file could not be read. Use a .csv or .xlsx file (in Google Sheets: File → Download).'));
        }

        $headers = null;
        $rows = [];
        foreach ($cells as $index => $row) {
            $row = array_map(fn ($cell) => $this->text($cell), $row);
            if (implode('', $row) === '') {
                continue;
            }
            if ($headers === null) {
                $headers = $row;

                continue;
            }
            $rows[] = ['line' => $index + 1, 'cells' => $row];
        }

        if ($headers === null) {
            throw new LogicException(__('The file is empty.'));
        }
        if (count($rows) > self::MAX_ROWS) {
            throw new LogicException(__('The file has :count lines; import at most :max at a time.', ['count' => count($rows), 'max' => self::MAX_ROWS]));
        }

        // Empty columns at the end (spreadsheets often have them) are dropped; unnamed ones get a letter.
        while ($headers !== [] && end($headers) === '' && collect($rows)->every(fn ($r) => ($r['cells'][count($headers) - 1] ?? '') === '')) {
            array_pop($headers);
        }
        foreach ($headers as $i => $header) {
            $headers[$i] = $header !== '' ? $header : __('Column :letter', ['letter' => chr(65 + $i % 26)]);
        }

        return ['headers' => array_values($headers), 'rows' => $rows];
    }

    /**
     * Which column fills which field, guessed from the headers. Unmatched fields are left out.
     *
     * @param  list<string>  $headers
     * @return array<string, int>
     */
    public function guessMapping(array $headers): array
    {
        $normal = array_map(fn ($h) => trim((string) preg_replace('/[^\pL\pN@\-]+/u', ' ', mb_strtolower($h))), $headers);
        $mapping = [];

        foreach (self::GUESSES as $field => $words) {
            foreach ($words as $word) {
                foreach ($normal as $column => $header) {
                    if (! in_array($column, $mapping, true) && preg_match('/\b'.preg_quote($word, '/').'\b/u', $header)) {
                        $mapping[$field] = $column;

                        continue 3;   // next field
                    }
                }
            }
        }

        return $mapping;
    }

    /**
     * The preview: what will happen to each line. Looks people up with two queries in total, not per line.
     *
     * @param  list<array{line: int, cells: list<string>}>  $rows
     * @param  array<string, int|string|null>  $mapping  field => column number ('' / null = not used)
     * @return list<array{line: int, status: string, name: string, contact: string, message: ?string, warnings: list<string>, values: array<string, mixed>, applicant_id: ?int}>
     */
    public function check(array $rows, array $mapping, JobPosting $posting): array
    {
        $mapping = array_filter($mapping, fn ($column) => $column !== '' && $column !== null);
        $read = array_map(fn ($row) => ['line' => $row['line'], ...$this->values($row['cells'], $mapping)], $rows);

        // Everyone already entered with one of these emails / phones, and who applied to this posting already.
        $emails = array_values(array_filter(array_column(array_column($read, 'values'), 'email')));
        $phones = array_values(array_filter(array_column(array_column($read, 'values'), 'phone')));
        $known = Applicant::query()->where(fn ($q) => $q->whereIn('email', $emails ?: ['-'])->orWhereIn('phone', $phones ?: ['-']))
            ->get(['id', 'email', 'phone']);
        $byEmail = $known->whereNotNull('email')->pluck('id', 'email');
        $byPhone = $known->whereNotNull('phone')->pluck('id', 'phone');
        $applied = $posting->applications()->pluck('applicant_id')->flip();

        $seen = [];   // email / phone → line it was first used on
        $results = [];
        foreach ($read as $row) {
            $values = $row['values'];
            $result = [
                'line' => $row['line'],
                'name' => trim(($values['first_name'] ?? '').' '.($values['last_name'] ?? '')),
                'contact' => $values['email'] ?? $values['phone'] ?? '',
                'warnings' => $row['warnings'],
                'values' => $values,
                'applicant_id' => null,
            ];

            if ($row['error'] !== null) {
                $results[] = [...$result, 'status' => 'error', 'message' => $row['error']];

                continue;
            }

            $keys = array_filter(['e:'.$values['email'] => $values['email'], 'p:'.$values['phone'] => $values['phone']]);
            $earlier = collect(array_keys($keys))->map(fn ($k) => $seen[$k] ?? null)->filter()->first();
            if ($earlier !== null) {
                $results[] = [...$result, 'status' => 'skip', 'message' => __('Same person as line :line.', ['line' => $earlier])];

                continue;
            }
            foreach (array_keys($keys) as $key) {
                $seen[$key] = $row['line'];
            }

            $ids = array_values(array_unique(array_filter([
                $values['email'] ? ($byEmail[$values['email']] ?? null) : null,
                $values['phone'] ? ($byPhone[$values['phone']] ?? null) : null,
            ])));

            $results[] = match (true) {
                count($ids) > 1 => [...$result, 'status' => 'error',
                    'message' => __('The email and the phone belong to two different people already entered.')],
                count($ids) === 1 && $applied->has($ids[0]) => [...$result, 'status' => 'skip', 'applicant_id' => $ids[0],
                    'message' => __('Already applied to this posting.')],
                count($ids) === 1 => [...$result, 'status' => 'existing', 'applicant_id' => $ids[0],
                    'message' => __('Already entered: added to this posting, their details are not changed.')],
                default => [...$result, 'status' => 'new', 'message' => null],
            };
        }

        return $results;
    }

    /** Why nothing can be imported onto this posting now, or null. */
    public function problem(JobPosting $posting): ?string
    {
        return $posting->isAcceptingApplications() ? null : __(':no is not taking applications (:state).', [
            'no' => $posting->number(), 'state' => $posting->applicationWindow(),
        ]);
    }

    /**
     * Saves the "new" and "existing" lines of check(), all in ONE transaction (all of them or none).
     *
     * @param  list<array{line: int, cells: list<string>}>  $rows
     * @param  array<string, int|string|null>  $mapping
     * @return array{added: int, existing: int, skipped: int}
     */
    public function handle(array $rows, array $mapping, JobPosting $posting, ?int $byUserId): array
    {
        if (($problem = $this->problem($posting)) !== null) {
            throw new LogicException($problem);
        }

        $results = $this->check($rows, $mapping, $posting);   // again: someone may have added people since the preview
        $apply = app(ApplyToPosting::class);

        DB::connection('tenant')->transaction(function () use ($results, $posting, $byUserId, $apply) {
            foreach ($results as $result) {
                if ($result['status'] === 'new') {
                    $applicant = Applicant::create([...$result['values'], 'created_by_user_id' => $byUserId]);
                } elseif ($result['status'] === 'existing') {
                    $applicant = Applicant::findOrFail($result['applicant_id']);
                } else {
                    continue;
                }
                $apply->handle($applicant, $posting, $byUserId);
            }
        });

        $count = fn (string $status) => count(array_filter($results, fn ($r) => $r['status'] === $status));

        return ['added' => $count('new'), 'existing' => $count('existing'), 'skipped' => $count('skip') + $count('error')];
    }

    // ---- one line -------------------------------------------------------------------------------

    /**
     * The applicant values of one line, plus the reason it cannot be used (error) and notes on values left out.
     *
     * @param  list<string>  $cells
     * @param  array<string, int|string>  $mapping
     * @return array{values: array<string, mixed>, error: ?string, warnings: list<string>}
     */
    private function values(array $cells, array $mapping): array
    {
        $raw = fn (string $field): string => isset($mapping[$field]) ? trim($cells[(int) $mapping[$field]] ?? '') : '';
        $labels = self::fields();
        $warnings = [];
        $values = array_fill_keys(array_keys($labels), null);

        foreach (self::MAX_LENGTH as $field => $max) {
            $text = $raw($field);
            if (mb_strlen($text) > $max) {
                $warnings[] = __(':field cut to :max characters.', ['field' => $labels[$field], 'max' => $max]);
                $text = mb_substr($text, 0, $max);
            }
            $values[$field] = $text === '' ? null : $text;
        }

        $values['email'] = Applicant::normalizeEmail($raw('email'));
        $values['phone'] = $this->phone($raw('phone'));

        if ($values['cv_url'] !== null && ! (preg_match('#^https?://#i', $values['cv_url']) && filter_var($values['cv_url'], FILTER_VALIDATE_URL))) {
            $warnings[] = __('CV link ":value" is not a web link, left empty.', ['value' => $values['cv_url']]);
            $values['cv_url'] = null;
        }

        if (($text = $raw('gender')) !== '') {
            $values['gender'] = match (mb_strtolower($text)) {
                'm', 'male', 'man' => Gender::Male,
                'f', 'female', 'woman' => Gender::Female,
                default => null,
            };
            if ($values['gender'] === null) {
                $warnings[] = __('Gender ":value" not understood, left empty.', ['value' => $text]);
            }
        }

        if (($text = $raw('birth_date')) !== '') {
            $values['birth_date'] = $this->date($text);
            if ($values['birth_date'] === null) {
                $warnings[] = __('Date of birth ":value" not understood (use 25/11/1995 or 1995-11-25), left empty.', ['value' => $text]);
            }
        }

        if (($text = $raw('expected_salary')) !== '') {
            // "৳ 30,000/-", "Tk. 30000", "30000 BDT" → 30000. A range or "negotiable" is not a number.
            $number = preg_replace('/(৳|tk\.?|bdt|taka|\/-|,|\s)/iu', '', $text);
            $values['expected_salary'] = is_numeric($number) && (float) $number >= 0 && (float) $number <= 999999999 ? $number : null;
            if ($values['expected_salary'] === null) {
                $warnings[] = __('Expected salary ":value" is not a number, left empty.', ['value' => $text]);
            }
        }

        $error = match (true) {
            $values['first_name'] === null => __('No name.'),
            $values['email'] === null && $values['phone'] === null => __('No email and no phone.'),
            $values['email'] !== null && (! filter_var($values['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($values['email']) > 150) => __('Email ":value" is not valid.', ['value' => $raw('email')]),
            $values['phone'] !== null && (! preg_match('/^[0-9+\-\s()]{6,}$/', $raw('phone')) || strlen($values['phone']) > 30) => __('Phone ":value" is not valid.', ['value' => $raw('phone')]),
            default => null,
        };

        return ['values' => $values, 'error' => $error, 'warnings' => $warnings];
    }

    /** Like Applicant::normalizePhone, and a mobile number that lost its first 0 in a spreadsheet gets it back. */
    private function phone(string $text): ?string
    {
        $phone = Applicant::normalizePhone($text);

        return $phone !== null && strlen($phone) === 10 && str_starts_with($phone, '1') ? '0'.$phone : $phone;
    }

    /** "25/11/1995", "1995-11-25", "25 Nov 1995" or an Excel date number → "1995-11-25"; null when not a past date. */
    private function date(string $text): ?string
    {
        $date = null;
        if (is_numeric($text)) {
            $date = Carbon::instance(ExcelDate::excelToDateTimeObject((float) $text));
        } else {
            foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'j M Y', 'j F Y', 'M j, Y', 'F j, Y'] as $format) {
                try {
                    $try = Carbon::createFromFormat('!'.$format, $text);
                } catch (Throwable) {
                    continue;
                }
                if ($try !== null && $try->format($format) === $text) {
                    $date = $try;

                    break;
                }
            }
        }

        return $date !== null && $date->year >= 1900 && $date->isBefore(today()) ? $date->toDateString() : null;
    }

    /** A cell as text: whole numbers without ".0", nothing → "". */
    private function text(mixed $cell): string
    {
        if (is_float($cell) && floor($cell) === $cell && abs($cell) < 1e15) {
            return sprintf('%.0f', $cell);
        }

        return trim((string) $cell);
    }
}
