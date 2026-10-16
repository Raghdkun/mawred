<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Domain\Access\Actions\AssignRoles;
use App\Domain\Access\Actions\SyncRoleGrants;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\User;
use App\Domain\Access\Support\DefaultRoles;
use App\Domain\Forms\Actions\StoreFormUpload;
use App\Domain\Forms\Models\FormVersion;
use App\Domain\Hiring\Actions\ChangePostingStatus;
use App\Domain\Hiring\Actions\MoveApplication;
use App\Domain\Hiring\Actions\RaiseRequisition;
use App\Domain\Hiring\Actions\RejectApplication;
use App\Domain\Hiring\Actions\SaveJobPosting;
use App\Domain\Hiring\Actions\SaveOffer;
use App\Domain\Hiring\Actions\SaveRequisitionTeam;
use App\Domain\Hiring\Actions\SaveScorecardCriteria;
use App\Domain\Hiring\Actions\ScheduleInterview;
use App\Domain\Hiring\Actions\SeedHiringDefaults;
use App\Domain\Hiring\Actions\SendOffer;
use App\Domain\Hiring\Actions\SubmitApplication;
use App\Domain\Hiring\Actions\SubmitOffer;
use App\Domain\Hiring\Actions\SubmitScorecard;
use App\Domain\Hiring\Actions\TagCandidate;
use App\Domain\Hiring\Data\InterviewInputData;
use App\Domain\Hiring\Data\JobPostingInputData;
use App\Domain\Hiring\Data\OfferInputData;
use App\Domain\Hiring\Enums\ApplicationSource;
use App\Domain\Hiring\Enums\MessageChannel;
use App\Domain\Hiring\Enums\PostingStatus;
use App\Domain\Hiring\Enums\Recommendation;
use App\Domain\Hiring\Enums\RejectionReason;
use App\Domain\Hiring\Models\Interview;
use App\Domain\Hiring\Models\JobApplication;
use App\Domain\Hiring\Models\JobPosting;
use App\Domain\Hiring\Models\JobRequisition;
use App\Domain\Hiring\Models\PipelineStage;
use App\Domain\Hiring\Models\ScorecardCriterion;
use App\Domain\Hiring\Support\CandidateMatcher;
use App\Domain\Hiring\Support\HiringSettings;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Department;
use App\Domain\People\Models\Employee;
use App\Domain\Requests\Models\EmployeeRequest;
use App\Domain\Workflows\Engine\WorkflowEngine;
use App\Domain\Workflows\Enums\Decision;
use App\Domain\Workflows\Enums\TaskStatus;
use App\Domain\Workflows\Models\WorkflowTask;
use App\Support\Actors\Actor;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Demo hiring (Phase 6): a recruiter sign-in, two approved requisitions with live postings and
 * candidates across the board (one flagged by screening, one possible duplicate), and one
 * requisition still awaiting approval; then interviews, a scorecard and an offer out for
 * signature (6b), added to a 6a demo too. Runs on an existing demo company; everything goes through
 * the real actions.
 *
 *   php artisan db:seed --class="Database\Seeders\Demo\HiringDemoSeeder"
 */
final class HiringDemoSeeder extends Seeder
{
    public const string RECRUITER = 'recruiter@mawred.test';

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('The demo seeder never runs in production.');
        }

        $password = config('seeding.demo_password');
        $manager = User::query()->where('email', 'branch.manager@mawred.test')->first();

        if (! is_string($password) || ! $manager instanceof User) {
            throw new RuntimeException('Seed the demo company (DemoSeeder) with DEMO_PASSWORD first.');
        }

        // Approval steps run on the queue after each commit; run them inline so requisitions open
        // as the seeder goes (no outer transaction, or the steps would wait for it).
        config(['queue.default' => 'sync']);

        if (! User::query()->where('email', self::RECRUITER)->exists()) {
            $this->seed($manager, $password);
        }

        if (Interview::query()->exists()) {
            $this->command->info('Hiring demo data is already there.');

            return;
        }

        $this->seedInterviewsAndOffers($manager, User::query()->where('email', self::RECRUITER)->firstOrFail());
    }

    /**
     * Phase 6b: Layla had her first interview yesterday (one scorecard in, one to go), Priya has a
     * booking link waiting, and Omar's offer went through the hiring manager and HR and is with him.
     */
    private function seedInterviewsAndOffers(User $manager, User $recruiter): void
    {
        resolve(SeedHiringDefaults::class)->handle();
        $actor = Actor::user($recruiter);
        $colleague = User::query()->whereKeyNot($manager->id)->whereIn('id', Employee::query()->whereNotNull('user_id')->select('user_id'))->orderBy('id')->firstOrFail();
        $application = function (string $email): JobApplication {
            $candidate = CandidateMatcher::sameEmail($email) ?? throw new RuntimeException("Demo candidate {$email} is missing.");

            return JobApplication::query()->where('candidate_id', $candidate->id)->firstOrFail();
        };

        $layla = $application('layla.m@example.com');
        $requisition = $layla->requisition()->firstOrFail();

        if (! ScorecardCriterion::query()->where('job_requisition_id', $requisition->id)->exists()) {
            resolve(SaveScorecardCriteria::class)->handle($actor, $requisition, array_map(fn (array $c): array => ['name' => ['en' => $c[0], 'ar' => $c[1]]], SeedHiringDefaults::criteria()));
        }

        $timezone = $requisition->branch()->firstOrFail()->timezone;
        $interview = resolve(ScheduleInterview::class)->handle($actor, $layla, InterviewInputData::from([
            'title' => 'First interview', 'durationMinutes' => 45, 'mode' => 'onsite', 'location' => 'Dubai Mall store, back office',
            'panel' => [$manager->ulid, $colleague->ulid], 'timezone' => $timezone,
            'startsAt' => CarbonImmutable::now($timezone)->subDay()->setTime(10, 0)->toIso8601String(),
        ]));
        $ratings = [];

        foreach (ScorecardCriterion::query()->where('job_requisition_id', $requisition->id)->orderBy('position')->get() as $i => $criterion) {
            $ratings[$criterion->ulid] = ['rating' => $i === 0 ? 4 : 3, 'note' => null];
        }

        resolve(SubmitScorecard::class)->handle(Actor::user($manager), $interview, $ratings, Recommendation::Yes, 'Warm with customers and knows the brands. Check weekend availability.', true);

        resolve(ScheduleInterview::class)->handle($actor, $application('priya.n@example.com'), InterviewInputData::from([
            'title' => 'Phone screen', 'durationMinutes' => 30, 'mode' => 'phone', 'panel' => [$recruiter->ulid], 'timezone' => $timezone,
        ]));

        $omar = $application('omar.h@example.com');
        $hiringManager = Employee::query()->where('user_id', $manager->id)->first();
        $offer = resolve(SaveOffer::class)->handle($actor, $omar, OfferInputData::from([
            'jobTitle' => 'Senior Sales Associate',
            'startOn' => CarbonImmutable::now($timezone)->addMonth()->startOfMonth()->toDateString(),
            'salary' => ['amount_minor' => 950000, 'currency' => 'AED'],
            'branchId' => $requisition->branch()->firstOrFail()->ulid,
            'managerId' => $hiringManager?->ulid,
            'expiresOn' => CarbonImmutable::now($timezone)->addWeeks(2)->toDateString(),
        ]));
        $offer = resolve(SubmitOffer::class)->handle($actor, $offer);

        while ($task = WorkflowTask::query()->where('workflow_instance_id', $offer->fresh()?->workflow_instance_id)->where('status', TaskStatus::Open->value)->first()) {
            resolve(WorkflowEngine::class)->decide($task, Decision::Approved, User::query()->findOrFail($task->assignee_user_id));
        }

        resolve(SendOffer::class)->handle($actor, $offer->fresh() ?? $offer, MessageChannel::Email);
    }

    private function seed(User $manager, string $password): void
    {
        $actor = Actor::system('demo-seed');
        $recruiter = User::query()->forceCreate(['name' => 'Reem Recruiter', 'email' => self::RECRUITER, 'password' => $password, 'status' => 'active', 'email_verified_at' => now(), 'locale' => 'en']);
        resolve(AssignRoles::class)->handle($actor, $recruiter, ['recruiter' => []]);
        // Roles seeded before Phase 6 keep their grants; bring the managers' up to date for the demo.
        foreach (['branch_manager', 'department_manager'] as $name) {
            resolve(SyncRoleGrants::class)->handle(Role::query()->where('name', $name)->firstOrFail(), DefaultRoles::definitions()[$name]['grants']);
        }

        $branch = Branch::query()->where('code', 'DXB')->firstOrFail();
        $sales = Department::query()->where('code', 'SALES')->first();

        $barista = $this->approved($manager, ['title' => 'Senior Sales Associate', 'branch' => $branch->ulid, 'department' => $sales?->ulid, 'headcount' => 2, 'reason' => 'new', 'salary_min' => ['amount_minor' => 800000, 'currency' => 'AED'], 'salary_max' => ['amount_minor' => 1100000, 'currency' => 'AED'], 'justification' => 'Second shift for the Dubai Mall store.']);
        $driver = $this->approved($manager, ['title' => 'Delivery Driver', 'branch' => $branch->ulid, 'headcount' => 1, 'reason' => 'replacement', 'justification' => 'Replacing a driver who left in September.']);
        resolve(RaiseRequisition::class)->handle(Actor::user($manager), ['title' => 'Store Supervisor', 'branch' => $branch->ulid, 'headcount' => 1, 'reason' => 'new', 'justification' => 'New store opening in Q1.']);

        foreach ([$barista, $driver] as $requisition) {
            resolve(SaveRequisitionTeam::class)->handle($actor, $requisition, [
                ['user' => $manager->ulid, 'role' => 'hiring_manager'],
                ['user' => $recruiter->ulid, 'role' => 'recruiter'],
            ]);
        }

        $sales = $this->posting($recruiter, $barista, ['en' => 'Senior Sales Associate', 'ar' => 'مندوب مبيعات أول'], ['en' => "Help customers find what they love at our Dubai Mall store.\n\n- Two years of retail experience\n- Arabic and English\n- Weekend shifts", 'ar' => "ساعد عملاءنا في العثور على ما يحبونه في متجرنا في دبي مول.\n\n- خبرة سنتين في البيع بالتجزئة\n- العربية والإنجليزية\n- مناوبات في عطلة نهاية الأسبوع"], true);
        $this->posting($recruiter, $driver, ['en' => 'Delivery Driver', 'ar' => 'سائق توصيل'], ['en' => "Deliver orders across Dubai on time.\n\n- UAE driving licence\n- Clean record", 'ar' => "توصيل الطلبات في أنحاء دبي في الوقت المحدد.\n\n- رخصة قيادة إماراتية\n- سجل نظيف"], false);

        $people = [
            ['Layla Al-Mansoori', 'layla.m@example.com', '+971501110001', 'yes', 4, 'interview', ['Arabic', 'Retail']],
            ['Omar Haddad', 'omar.h@example.com', '+971501110002', 'yes', 6, 'offer', ['Retail']],
            ['Priya Nair', 'priya.n@example.com', '+971501110003', 'yes', 3, 'screening', []],
            ['Daniel Ortiz', 'daniel.o@example.com', '+971501110004', 'sponsorship', 1, null, []],
            ['Sara Youssef', 'sara.y@example.com', '+971501110005', 'yes', 2, null, ['Arabic']],
            ['Ahmed Karim', 'ahmed.k@example.com', '+971501110006', 'yes', 0, 'rejected', []],
            ['Layla Mansoori', 'layla.mansoori@example.com', '+971501110001', 'yes', 4, null, []],
        ];

        foreach ($people as $i => [$name, $email, $phone, $permit, $years, $stage, $tags]) {
            $application = resolve(SubmitApplication::class)->handle(Actor::system('careers'), $barista, $sales, [
                'full_name' => $name, 'email' => $email, 'phone' => $phone, 'location' => 'Dubai',
                'right_to_work' => $permit, 'experience_years' => $years, 'cv' => [$this->cv($name, $i)],
                'cover_note' => 'I love helping customers and know the store well.',
            ], ApplicationSource::Careers, ['privacy' => true, 'talentPool' => $i % 2 === 0], hash('sha256', "demo-{$i}"));

            if ($tags !== []) {
                resolve(TagCandidate::class)->handle(Actor::user($recruiter), $application->candidate()->firstOrFail(), $tags);
            }

            $this->advance($recruiter, $application, $stage);
        }
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function approved(User $manager, array $values): JobRequisition
    {
        $requisition = resolve(RaiseRequisition::class)->handle(Actor::user($manager), array_filter($values, fn (mixed $v): bool => $v !== null));
        $request = EmployeeRequest::query()->findOrFail($requisition->request_id);

        while ($task = WorkflowTask::query()->where('workflow_instance_id', $request->workflow_instance_id)->where('status', TaskStatus::Open->value)->first()) {
            $approver = User::query()->findOrFail($task->assignee_user_id);
            resolve(WorkflowEngine::class)->decide($task, Decision::Approved, $approver);
        }

        return $requisition->fresh() ?? $requisition;
    }

    /**
     * @param  array{en: string, ar: string}  $title
     * @param  array{en: string, ar: string}  $description
     */
    private function posting(User $recruiter, JobRequisition $requisition, array $title, array $description, bool $screening): JobPosting
    {
        $posting = resolve(SaveJobPosting::class)->handle(Actor::user($recruiter), $requisition, JobPostingInputData::from([
            'title' => $title,
            'description' => $description,
            'summary' => ['en' => 'Full time in Dubai', 'ar' => 'دوام كامل في دبي'],
            'showSalary' => true,
            'knockouts' => $screening ? [
                ['field' => 'right_to_work', 'rule' => ['op' => 'in', 'args' => [['ref' => 'field.right_to_work'], ['value' => ['sponsorship']]]]],
                ['field' => 'experience_years', 'rule' => ['op' => 'lt', 'args' => [['ref' => 'field.experience_years'], 2]]],
            ] : [],
        ]));

        return resolve(ChangePostingStatus::class)->handle(Actor::user($recruiter), $posting, PostingStatus::Published);
    }

    private function advance(User $recruiter, JobApplication $application, ?string $stage): void
    {
        if ($stage === null) {
            return;
        }

        if ($stage === 'rejected') {
            resolve(RejectApplication::class)->handle(Actor::user($recruiter), $application, RejectionReason::NotQualified);

            return;
        }

        foreach (['screening', 'interview', 'offer'] as $kind) {
            resolve(MoveApplication::class)->handle(Actor::user($recruiter), $application, PipelineStage::query()->where('job_requisition_id', $application->job_requisition_id)->where('kind', $kind)->firstOrFail());

            if ($kind === $stage) {
                return;
            }
        }
    }

    private function cv(string $name, int $i): string
    {
        $form = resolve(HiringSettings::class)->defaultForm() ?? throw new RuntimeException('Run the production seeder first.');
        $version = FormVersion::query()->findOrFail($form->published_version_id);
        $file = UploadedFile::fake()->createWithContent('cv-'.str($name)->slug().'.pdf', $this->pdf($name));

        return resolve(StoreFormUpload::class)->handle($form, $version, 'cv', $file, null, hash('sha256', "demo-{$i}"))->ulid;
    }

    /** A one-page PDF with the candidate's name, so the CV viewer has something to show. */
    private function pdf(string $name): string
    {
        $text = 'BT /F1 24 Tf 72 720 Td ('.addcslashes($name, '()\\').') Tj 0 -36 Td /F1 12 Tf (Curriculum vitae - demo) Tj ET';
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($text)." >>\nstream\n{$text}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $n => $body) {
            $offsets[] = strlen($pdf);
            $pdf .= ($n + 1)." 0 obj\n{$body}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= 'xref'."\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= str_pad((string) $offset, 10, '0', STR_PAD_LEFT)." 00000 n \n";
        }

        return $pdf.'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }
}
