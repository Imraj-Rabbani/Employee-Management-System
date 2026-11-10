<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\UserRole;
use App\Modules\Hrm\Actions\MoveApplication;
use App\Modules\Hrm\Enums\ApplicationStage;
use App\Modules\Hrm\Enums\EmployeeStatus;
use App\Modules\Hrm\Enums\Gender;
use App\Modules\Hrm\Enums\InterviewRecommendation;
use App\Modules\Hrm\Enums\InterviewStatus;
use App\Modules\Hrm\Enums\JobPostingStatus;
use App\Modules\Hrm\Enums\ManpowerRequestType;
use App\Modules\Hrm\Enums\PaymentMethod;
use App\Modules\Hrm\Models\Applicant;
use App\Modules\Hrm\Models\Application;
use App\Modules\Hrm\Models\Branch;
use App\Modules\Hrm\Models\CostCenter;
use App\Modules\Hrm\Models\Designation;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\EmploymentType;
use App\Modules\Hrm\Models\Grade;
use App\Modules\Hrm\Models\Interview;
use App\Modules\Hrm\Models\InterviewFactor;
use App\Modules\Hrm\Models\JobOffer;
use App\Modules\Hrm\Models\JobPosting;
use App\Modules\Hrm\Models\ManpowerRequest;
use App\Modules\Hrm\Models\OrgUnit;
use App\Modules\Hrm\Models\OrgUnitType;
use App\Modules\Hrm\Models\Position;
use App\Modules\Hrm\Models\RejectionReason;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Sample data so every built page has something to show. FOR LOCAL / DEMO COMPANIES ONLY.
 *
 *     php artisan tenants:seed --tenant=demo --class=DemoDataSeeder
 *
 * Creates: cost centers, branches, employment types, grades, designations, an org tree
 * (Company → Divisions → Departments → Sections), positions, ~30 employees with supervisors,
 * interview factors, rejection reasons, manpower requests, job postings, applicants and
 * applications at every pipeline stage, interviews with scores, and one approved offer.
 *
 * Also gives the company's first login the Administrator role (if it has no role) and links it
 * to the CEO employee, so "My Profile" and "My Interviews" have data.
 *
 * Runs only when the company has no employees yet, so it never mixes with real data.
 */
class DemoDataSeeder extends Seeder
{
    private const FIRST_MALE = ['Rahim', 'Karim', 'Tanvir', 'Arif', 'Sajid', 'Imran', 'Faisal', 'Nabil', 'Rafiq', 'Shakil', 'Mehedi', 'Rakib', 'Zahid', 'Sohel', 'Kamal'];

    private const FIRST_FEMALE = ['Nusrat', 'Farhana', 'Sadia', 'Tasnim', 'Ayesha', 'Sharmin', 'Nadia', 'Rumana', 'Lamia', 'Shirin', 'Mitu', 'Fariha'];

    private const LAST = ['Hossain', 'Rahman', 'Ahmed', 'Islam', 'Chowdhury', 'Khan', 'Uddin', 'Akter', 'Haque', 'Sarker', 'Alam', 'Begum', 'Talukder', 'Mia'];

    private int $code = 1000;

    private ?int $userId = null;

    public function run(): void
    {
        if (Employee::withoutGlobalScopes()->exists()) {
            $this->command?->warn('This company already has employees: demo data skipped.');

            return;
        }

        $this->call(TenantDatabaseSeeder::class);   // roles, currency, org unit levels

        DB::connection('tenant')->transaction(function () {
            $this->userId = $this->giveFirstLoginAdmin();

            $org = $this->organization();
            $employees = $this->employees($org);
            $this->recruitment($org, $employees);
        });

        $this->command?->info('Demo data created.');
    }

    /** The company's first login gets Administrator (if it has no role yet). Returns its id. */
    private function giveFirstLoginAdmin(): ?int
    {
        $user = User::where('tenant_id', tenant()->id)->orderBy('id')->first();
        if ($user === null) {
            return null;
        }

        if (! UserRole::where('user_id', $user->id)->exists()) {
            UserRole::create(['user_id' => $user->id, 'role_id' => Role::administrator()->id]);
        }

        return $user->id;
    }

    /** @return array<string, mixed> */
    private function organization(): array
    {
        $cc = collect([
            ['CC-100', 'Head Office', '5100'],
            ['CC-200', 'Factory', '5200'],
            ['CC-300', 'Sales & Distribution', '5300'],
        ])->mapWithKeys(fn ($c) => [$c[0] => CostCenter::create(['code' => $c[0], 'name' => $c[1], 'account_code' => $c[2]])]);

        $branches = collect([
            ['Dhaka HQ', 'Gulshan-2, Dhaka'],
            ['Chattogram', 'Agrabad C/A, Chattogram'],
            ['Gazipur Factory', 'Board Bazar, Gazipur'],
        ])->map(fn ($b) => Branch::create(['name' => $b[0], 'location' => $b[1]]))->values();

        $types = collect(['Permanent', 'Contract', 'Probation', 'Intern'])
            ->mapWithKeys(fn ($n) => [$n => EmploymentType::create(['name' => $n])]);

        $grades = collect([
            ['G-1', 'Grade 1 (Executive Mgmt)', 1, 250000, 500000],
            ['G-2', 'Grade 2 (Senior Mgmt)', 2, 150000, 250000],
            ['G-3', 'Grade 3 (Management)', 3, 90000, 150000],
            ['G-4', 'Grade 4 (Senior Staff)', 4, 60000, 90000],
            ['G-5', 'Grade 5 (Staff)', 5, 35000, 60000],
            ['G-6', 'Grade 6 (Junior Staff)', 6, 18000, 35000],
        ])->mapWithKeys(fn ($g) => [$g[0] => Grade::create(['code' => $g[0], 'name' => $g[1], 'sequence' => $g[2], 'salary_min' => $g[3], 'salary_max' => $g[4]])]);

        $designations = collect([
            'CEO' => 'Chief Executive Officer', 'HOD' => 'Head of Division', 'MGR' => 'Manager',
            'AM' => 'Assistant Manager', 'SE' => 'Senior Executive', 'EX' => 'Executive',
            'OFF' => 'Officer', 'ENG' => 'Software Engineer', 'ACC' => 'Accountant', 'INT' => 'Intern',
        ])->mapWithKeys(fn ($name, $code) => [$code => Designation::create(['code' => 'D-'.$code, 'name' => $name])]);

        $type = OrgUnitType::orderBy('sequence')->pluck('id')->values();   // Company, Division, Department, Section
        $unit = fn (string $code, string $name, int $level, ?OrgUnit $parent, string $cc) => OrgUnit::create([
            'code' => $code, 'name' => $name, 'org_unit_type_id' => $type[$level] ?? $type->last(),
            'parent_id' => $parent?->id, 'cost_center_id' => $cc,
        ]);

        $company = $unit('DC', 'Demo Company Ltd.', 0, null, $cc['CC-100']->id);
        $ops = $unit('OPS', 'Operations', 1, $company, $cc['CC-200']->id);
        $corp = $unit('CORP', 'Corporate Services', 1, $company, $cc['CC-100']->id);
        $units = [
            'company' => $company, 'ops' => $ops, 'corp' => $corp,
            'production' => $unit('PRD', 'Production', 2, $ops, $cc['CC-200']->id),
            'supply' => $unit('SCM', 'Supply Chain', 2, $ops, $cc['CC-200']->id),
            'sales' => $unit('SAL', 'Sales & Marketing', 2, $ops, $cc['CC-300']->id),
            'finance' => $unit('FIN', 'Finance & Accounts', 2, $corp, $cc['CC-100']->id),
            'hr' => $unit('HR', 'Human Resources', 2, $corp, $cc['CC-100']->id),
            'it' => $unit('IT', 'Information Technology', 2, $corp, $cc['CC-100']->id),
        ];
        $units['assembly'] = $unit('PRD-A', 'Assembly Line', 3, $units['production'], $cc['CC-200']->id);
        $units['qc'] = $unit('PRD-Q', 'Quality Control', 3, $units['production'], $cc['CC-200']->id);

        return compact('cc', 'branches', 'types', 'grades', 'designations', 'units');
    }

    /**
     * @param  array<string, mixed>  $org
     * @return array<string, Employee>
     */
    private function employees(array $org): array
    {
        $u = $org['units'];
        $hq = $org['branches'][0];
        $factory = $org['branches'][2];

        // [key, unit, designation, grade, supervisor key, is unit head, branch]
        $plan = [
            ['ceo', 'company', 'CEO', 'G-1', null, true, $hq],
            ['ops_head', 'ops', 'HOD', 'G-2', 'ceo', true, $hq],
            ['corp_head', 'corp', 'HOD', 'G-2', 'ceo', true, $hq],
            ['prd_mgr', 'production', 'MGR', 'G-3', 'ops_head', true, $factory],
            ['scm_mgr', 'supply', 'MGR', 'G-3', 'ops_head', true, $factory],
            ['sal_mgr', 'sales', 'MGR', 'G-3', 'ops_head', true, $org['branches'][1]],
            ['fin_mgr', 'finance', 'MGR', 'G-3', 'corp_head', true, $hq],
            ['hr_mgr', 'hr', 'MGR', 'G-3', 'corp_head', true, $hq],
            ['it_mgr', 'it', 'MGR', 'G-3', 'corp_head', true, $hq],
            ['asm_lead', 'assembly', 'AM', 'G-4', 'prd_mgr', true, $factory],
            ['qc_lead', 'qc', 'AM', 'G-4', 'prd_mgr', true, $factory],
        ];
        // Staff: [unit, designation, grade, supervisor, branch, how many]
        $staff = [
            ['assembly', 'OFF', 'G-6', 'asm_lead', $factory, 4],
            ['qc', 'EX', 'G-5', 'qc_lead', $factory, 2],
            ['supply', 'SE', 'G-4', 'scm_mgr', $factory, 2],
            ['sales', 'EX', 'G-5', 'sal_mgr', $org['branches'][1], 3],
            ['finance', 'ACC', 'G-5', 'fin_mgr', $hq, 2],
            ['hr', 'SE', 'G-4', 'hr_mgr', $hq, 1],
            ['hr', 'EX', 'G-5', 'hr_mgr', $hq, 1],
            ['it', 'ENG', 'G-4', 'it_mgr', $hq, 3],
            ['it', 'INT', 'G-6', 'it_mgr', $hq, 1],
        ];

        $people = [];
        $positions = [];
        foreach ($plan as [$key, $unitKey, $desig, $grade, $boss, $isHead, $branch]) {
            $unit = $u[$unitKey];
            $position = Position::create([
                'code' => 'P-'.strtoupper($key), 'name' => $org['designations'][$desig]->name.' — '.$unit->name,
                'org_unit_id' => $unit->id, 'grade_id' => $org['grades'][$grade]->id,
                'parent_id' => $boss ? $positions[$boss]->id : null, 'is_vacant' => false,
            ]);
            $positions[$key] = $position;

            $people[$key] = $this->employee($org, $unit, $desig, $grade, $boss ? $people[$boss] : null, $branch, 'Permanent', $position);
            $position->update(['employee_id' => $people[$key]->id]);
            if ($isHead) {
                $unit->update(['head_employee_id' => $people[$key]->id]);
            }
        }

        // One vacant position, so the position tree shows a gap to fill.
        Position::create([
            'code' => 'P-IT-SE', 'name' => 'Senior Software Engineer — Information Technology', 'org_unit_id' => $u['it']->id,
            'grade_id' => $org['grades']['G-4']->id, 'parent_id' => $positions['it_mgr']->id, 'is_vacant' => true,
        ]);

        $i = 0;
        foreach ($staff as [$unitKey, $desig, $grade, $boss, $branch, $count]) {
            for ($n = 0; $n < $count; $n++, $i++) {
                $type = match (true) {
                    $desig === 'INT' => 'Intern',
                    $i % 7 === 3 => 'Contract',
                    $i % 7 === 5 => 'Probation',
                    default => 'Permanent',
                };
                $people["staff_$i"] = $this->employee($org, $u[$unitKey], $desig, $grade, $people[$boss], $branch, $type);
            }
        }

        // A few statuses other than Active, for the list filters.
        $people['staff_1']->update(['status' => EmployeeStatus::Suspended]);
        $people['staff_6']->update(['status' => EmployeeStatus::Separated, 'separation_date' => now()->subMonths(2)->toDateString()]);

        // The first login = the CEO, so My Profile / My Interviews show data.
        if ($this->userId !== null) {
            $people['ceo']->forceFill(['user_id' => $this->userId])->save();
        }

        return $people;
    }

    /** @param  array<string, mixed>  $org */
    private function employee(array $org, OrgUnit $unit, string $desig, string $grade, ?Employee $boss, Branch $branch, string $type, ?Position $position = null): Employee
    {
        $male = fake()->boolean(60);
        $first = fake()->randomElement($male ? self::FIRST_MALE : self::FIRST_FEMALE);
        $last = fake()->randomElement(self::LAST);
        $code = 'E-'.++$this->code;

        $joined = Carbon::instance(fake()->dateTimeBetween($desig === 'INT' ? '-4 months' : '-9 years', '-3 months'));

        return Employee::create([
            'code' => $code,
            'first_name' => $first,
            'last_name' => $last,
            'gender' => $male ? Gender::Male : Gender::Female,
            'birth_date' => fake()->dateTimeBetween('-55 years', '-22 years')->format('Y-m-d'),
            'nationality' => 'Bangladeshi',
            'national_id' => fake()->numerify('##########'),
            'mobile' => '01'.fake()->randomElement(['7', '8', '9', '3', '5']).fake()->numerify('########'),
            'office_email' => strtolower($first.'.'.$last.'.'.$this->code).'@demo.test',
            'join_date' => $joined->toDateString(),
            'org_unit_id' => $unit->id,
            'employment_type_id' => $org['types'][$type]->id,
            'designation_id' => $org['designations'][$desig]->id,
            'grade_id' => $org['grades'][$grade]->id,
            'position_id' => $position?->id,
            'branch_id' => $branch->id,
            'cost_center_id' => $unit->cost_center_id,
            'supervisor_id' => $boss?->id,
            'payment_method' => $type === 'Intern' ? PaymentMethod::Cash : PaymentMethod::Bank,
            'status' => EmployeeStatus::Active,
        ]);
    }

    /**
     * @param  array<string, mixed>  $org
     * @param  array<string, Employee>  $people
     */
    private function recruitment(array $org, array $people): void
    {
        $u = $org['units'];
        $d = $org['designations'];
        $g = $org['grades'];

        $factors = collect([
            ['Communication', 20, true], ['Technical skill', 35, true], ['Attitude', 15, false],
            ['Relevant experience', 20, true], ['Leadership', 10, false],
        ])->map(fn ($f, $i) => InterviewFactor::create(['name' => $f[0], 'weight' => $f[1], 'is_required' => $f[2], 'sequence' => $i + 1]));

        $reasons = collect(['Not enough experience', 'Salary expectation too high', 'Did not attend interview', 'Accepted another offer', 'Failed technical test'])
            ->mapWithKeys(fn ($n) => [$n => RejectionReason::create(['name' => $n])]);

        $request = function (OrgUnit $unit, Designation $desig, Grade $grade, int $headcount, int $min, int $max, string $why, bool $approved, ?Employee $replaces = null) use ($org) {
            $r = ManpowerRequest::create([
                'org_unit_id' => $unit->id, 'designation_id' => $desig->id, 'grade_id' => $grade->id,
                'employment_type_id' => $org['types']['Permanent']->id,
                'type' => $replaces ? ManpowerRequestType::Replacement : ManpowerRequestType::New,
                'replaces_employee_id' => $replaces?->id,
                'headcount' => $headcount, 'salary_min' => $min, 'salary_max' => $max,
                'needed_by' => now()->addMonths(2)->toDateString(), 'justification' => $why,
                'requested_by_user_id' => $this->userId,
            ]);
            if ($approved) {
                $r->forceFill(['approved_at' => now()->subWeeks(3)])->save();
            }

            return $r;
        };

        $reqIt = $request($u['it'], $d['ENG'], $g['G-4'], 2, 60000, 85000, 'New ERP project needs two more developers.', true);
        $reqSales = $request($u['sales'], $d['EX'], $g['G-5'], 2, 35000, 50000, 'Expansion into Sylhet region.', true);
        $reqQc = $request($u['qc'], $d['EX'], $g['G-5'], 1, 35000, 45000, 'Replacement for a separated QC executive.', true, $people['staff_6']);
        $reqFin = $request($u['finance'], $d['AM'], $g['G-4'], 1, 65000, 80000, 'Month-end closing workload.', true);
        $request($u['hr'], $d['OFF'], $g['G-6'], 1, 22000, 30000, 'Recruitment coordinator for the hiring season.', false);

        $posting = fn (ManpowerRequest $r, string $title, JobPostingStatus $status, int $openedDaysAgo) => JobPosting::create([
            'manpower_request_id' => $r->id, 'title' => $title,
            'description' => fake()->paragraphs(2, true),
            'requirements' => "- Bachelor's degree in a relevant subject\n- ".fake()->numberBetween(2, 5)." years of experience\n- Good communication in Bangla and English",
            'openings' => $r->headcount, 'open_date' => now()->subDays($openedDaysAgo)->toDateString(),
            'close_date' => now()->subDays($openedDaysAgo)->addDays(30)->toDateString(),
            'status' => $status, 'created_by_user_id' => $this->userId,
        ]);

        $pIt = $posting($reqIt, 'Software Engineer (Laravel)', JobPostingStatus::Open, 18);
        $pSales = $posting($reqSales, 'Sales Executive — Sylhet', JobPostingStatus::Open, 10);
        $pQc = $posting($reqQc, 'Quality Control Executive', JobPostingStatus::Closed, 60);
        $posting($reqFin, 'Assistant Manager, Finance', JobPostingStatus::Draft, 0);

        $move = app(MoveApplication::class);
        $panel = [$people['it_mgr'], $people['ceo']];

        // [posting, stages to walk through, reason when rejected]
        $pipeline = [
            [$pIt, [ApplicationStage::Shortlisted, ApplicationStage::Interview, ApplicationStage::Offered], null],
            [$pIt, [ApplicationStage::Shortlisted, ApplicationStage::Interview], null],
            [$pIt, [ApplicationStage::Shortlisted, ApplicationStage::Interview], null],
            [$pIt, [ApplicationStage::Shortlisted], null],
            [$pIt, [], null],
            [$pIt, [], null],
            [$pIt, [ApplicationStage::Rejected], 'Not enough experience'],
            [$pSales, [ApplicationStage::Shortlisted], null],
            [$pSales, [], null],
            [$pSales, [], null],
            [$pSales, [ApplicationStage::Withdrawn], 'Accepted another offer'],
            [$pQc, [ApplicationStage::Shortlisted, ApplicationStage::Interview, ApplicationStage::Offered, ApplicationStage::Hired], null],
            [$pQc, [ApplicationStage::Shortlisted, ApplicationStage::Rejected], 'Salary expectation too high'],
        ];

        foreach ($pipeline as $i => [$post, $stages, $reason]) {
            $applicant = $this->applicant();
            $app = Application::create([
                'applicant_id' => $applicant->id, 'job_posting_id' => $post->id,
                'applied_on' => Carbon::parse($post->open_date)->addDays($i % 5)->toDateString(),
                'created_by_user_id' => $this->userId,
            ]);

            foreach ($stages as $stage) {
                if ($stage === ApplicationStage::Offered) {
                    $this->offer($app, $post, $org);
                }
                if ($stage === ApplicationStage::Hired) {
                    $app->forceFill(['hired_employee_id' => $this->hire($applicant, $org)->id])->save();
                    $post->manpowerRequest->increment('hired_count');
                }
                $move->bySystem($app->refresh(), $stage, $reason ? $reasons[$reason]->id : null, 'Demo data', $this->userId);
                if ($stage === ApplicationStage::Interview) {
                    $this->interview($app, $post === $pQc ? [$people['qc_lead'], $people['prd_mgr']] : $panel, $factors, completed: $i !== 2);
                }
            }
        }
    }

    private function applicant(): Applicant
    {
        $male = fake()->boolean();
        $first = fake()->randomElement($male ? self::FIRST_MALE : self::FIRST_FEMALE);
        $last = fake()->randomElement(self::LAST);

        $applicant = Applicant::create([
            'first_name' => $first, 'last_name' => $last,
            'email' => strtolower($first.'.'.$last).fake()->unique()->numberBetween(10, 999).'@mail.test',
            'phone' => '017'.fake()->unique()->numerify('########'),
            'gender' => $male ? Gender::Male : Gender::Female,
            'birth_date' => fake()->dateTimeBetween('-38 years', '-22 years')->format('Y-m-d'),
            'address' => fake()->randomElement(['Mirpur', 'Uttara', 'Dhanmondi', 'Mohammadpur', 'Banani']).', Dhaka',
            'current_employer' => fake()->randomElement(['BRAC', 'Grameenphone', 'Walton', 'PRAN-RFL', 'Square Group', 'bKash', null]),
            'current_designation' => fake()->randomElement(['Executive', 'Junior Engineer', 'Officer', null]),
            'expected_salary' => fake()->randomElement([35000, 45000, 60000, 75000, 90000]),
            'created_by_user_id' => $this->userId,
        ]);

        $applicant->educations()->create([
            'degree' => fake()->randomElement(['BSc', 'BBA', 'MBA', 'MSc']),
            'institution' => fake()->randomElement(['University of Dhaka', 'BUET', 'North South University', 'BRAC University', 'Chittagong University']),
            'subject' => fake()->randomElement(['Computer Science', 'Accounting', 'Marketing', 'Electrical Engineering']),
            'score' => fake()->randomFloat(2, 2.8, 3.9), 'out_of' => 4,
            'passing_year' => fake()->numberBetween(2010, 2023),
        ]);

        return $applicant;
    }

    /** @param  iterable<InterviewFactor>  $factors */
    private function interview(Application $app, array $panel, iterable $factors, bool $completed): void
    {
        $interview = Interview::create([
            'application_id' => $app->id, 'round' => 1,
            'scheduled_at' => $completed ? now()->subDays(5)->setTime(11, 0) : now()->addDays(2)->setTime(15, 0),
            'duration_minutes' => 45, 'location' => 'Board room, Dhaka HQ',
            'status' => $completed ? InterviewStatus::Completed : InterviewStatus::Scheduled,
            'created_by_user_id' => $this->userId,
        ]);

        foreach ($panel as $employee) {
            $seat = $interview->panelists()->create(['employee_id' => $employee->id]);
            if (! $completed) {
                continue;
            }
            foreach ($factors as $factor) {
                $seat->scores()->create(['interview_factor_id' => $factor->id, 'score' => fake()->numberBetween(3, 5), 'weight' => $factor->weight]);
            }
            $seat->forceFill([
                'recommendation' => fake()->randomElement([InterviewRecommendation::Recommend, InterviewRecommendation::Consider]),
                'comment' => 'Good fundamentals, communicates clearly.',
                'submitted_at' => now()->subDays(5),
            ])->save();
        }
    }

    /** @param  array<string, mixed>  $org */
    private function offer(Application $app, JobPosting $post, array $org): void
    {
        $req = $post->manpowerRequest;
        $offer = JobOffer::create([
            'application_id' => $app->id, 'gross_salary' => $req->salary_max - 5000,
            'grade_id' => $req->grade_id, 'designation_id' => $req->designation_id,
            'employment_type_id' => $org['types']['Probation']->id,
            'joining_date' => now()->addMonth()->startOfMonth()->toDateString(),
            'expires_on' => now()->addWeek()->toDateString(),
            'terms' => 'Six months probation. Festival bonus as per company policy.',
            'created_by_user_id' => $this->userId,
        ]);
        $offer->forceFill(['approved_at' => now()->subDays(2)])->save();
    }

    /** @param  array<string, mixed>  $org */
    private function hire(Applicant $applicant, array $org): Employee
    {
        return Employee::create([
            'code' => 'E-'.++$this->code,
            'first_name' => $applicant->first_name, 'last_name' => $applicant->last_name,
            'gender' => $applicant->gender, 'birth_date' => $applicant->birth_date,
            'mobile' => $applicant->phone, 'personal_email' => $applicant->email,
            'join_date' => now()->subWeeks(2)->toDateString(),
            'org_unit_id' => $org['units']['qc']->id,
            'employment_type_id' => $org['types']['Probation']->id,
            'designation_id' => $org['designations']['EX']->id,
            'grade_id' => $org['grades']['G-5']->id,
            'branch_id' => $org['branches'][2]->id,
            'cost_center_id' => $org['units']['qc']->cost_center_id,
            'supervisor_id' => $org['units']['qc']->head_employee_id,
            'payment_method' => PaymentMethod::Bank,
            'status' => EmployeeStatus::Active,
        ]);
    }
}
