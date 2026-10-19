<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Domain\Access\Actions\AssignRoles;
use App\Domain\Access\Models\User;
use App\Domain\Organization\Models\Branch;
use App\Domain\People\Enums\EmployeeStatus;
use App\Domain\People\Models\Employee;
use App\Domain\Training\Actions\BookSession;
use App\Domain\Training\Actions\ChangeCourseStatus;
use App\Domain\Training\Actions\ChangeLearningPathStatus;
use App\Domain\Training\Actions\CompleteModule;
use App\Domain\Training\Actions\Enrol;
use App\Domain\Training\Actions\IssueCertificate;
use App\Domain\Training\Actions\SaveCourse;
use App\Domain\Training\Actions\SaveCourseModules;
use App\Domain\Training\Actions\SaveLearningPath;
use App\Domain\Training\Actions\SaveSession;
use App\Domain\Training\Actions\SeedTrainingDefaults;
use App\Domain\Training\Actions\SubmitQuizAttempt;
use App\Domain\Training\Data\CourseInputData;
use App\Domain\Training\Data\LearningPathInputData;
use App\Domain\Training\Data\ModulesInputData;
use App\Domain\Training\Data\SessionInputData;
use App\Domain\Training\Enums\CourseStatus;
use App\Domain\Training\Enums\EnrollmentSource;
use App\Domain\Training\Enums\ModuleKind;
use App\Domain\Training\Models\Course;
use App\Domain\Training\Models\CourseModule;
use App\Domain\Training\Models\Enrollment;
use App\Domain\Training\Models\TrainingSession;
use App\Support\Actors\Actor;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Demo training (Phase 7): a trainer sign-in, four courses (one mandatory for the Dubai branch,
 * one that needs the manager's approval), a learning path, and learners part-way through or
 * finished (7a); then a classroom course with booked sessions, and certificates (7b), added to a
 * 7a demo too. Runs on an existing demo company; everything goes through the real actions.
 *
 *   php artisan db:seed --class="Database\Seeders\Demo\TrainingDemoSeeder"
 */
final class TrainingDemoSeeder extends Seeder
{
    public const string TRAINER = 'trainer@mawred.test';

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('The demo seeder never runs in production.');
        }

        $password = config('seeding.demo_password');
        $learner = User::query()->where('email', 'employee@mawred.test')->first();

        if (! is_string($password) || ! $learner instanceof User) {
            throw new RuntimeException('Seed the demo company (DemoSeeder) with DEMO_PASSWORD first.');
        }

        // Enrolment approvals and audience assignment run on the queue; keep them inline here.
        config(['queue.default' => 'sync']);
        resolve(SeedTrainingDefaults::class)->handle();

        if (! User::query()->where('email', self::TRAINER)->exists()) {
            $this->seedCatalog($password, $learner);
        }

        if (TrainingSession::query()->exists()) {
            $this->command->info('Training demo data is already there.');

            return;
        }

        $this->seedSessionsAndCertificates(User::query()->where('email', self::TRAINER)->firstOrFail());
    }

    /**
     * Phase 7b: a classroom course with two sessions next week (a few seats booked), and
     * certificates for the people who finished Customer service essentials.
     */
    private function seedSessionsAndCertificates(User $trainer): void
    {
        $actor = Actor::user($trainer);
        $barista = $this->course($actor, [
            'title' => ['en' => 'Barista masterclass', 'ar' => 'دورة الباريستا المتقدمة'],
            'summary' => ['en' => 'Two hours behind the bar with our head barista.', 'ar' => 'ساعتان خلف البار مع رئيس الباريستا.'],
            'category' => 'skills', 'durationMinutes' => 150, 'validityMonths' => 24,
        ], [
            ['kind' => 'text', 'title' => ['en' => 'Before the class', 'ar' => 'قبل الدورة'], 'body' => ['en' => 'Bring an apron and closed shoes.', 'ar' => 'أحضر مريلة وحذاءً مغلقًا.']],
        ]);
        $timezone = Branch::query()->where('code', 'DXB')->value('timezone');
        $timezone = is_string($timezone) ? $timezone : 'Asia/Dubai';
        $sessions = [];

        foreach ([[7, 'Dubai Mall store, back of house'], [9, 'Head office training room']] as [$days, $place]) {
            $start = CarbonImmutable::now($timezone)->addDays($days)->setTime(10, 0);
            $sessions[] = resolve(SaveSession::class)->handle($actor, $barista, SessionInputData::from([
                'startsAt' => $start->toIso8601String(), 'endsAt' => $start->addHours(2)->toIso8601String(), 'timezone' => $timezone,
                'location' => $place, 'capacity' => 6, 'trainerId' => $trainer->ulid,
            ]));
        }

        foreach (Employee::query()->where('status', EmployeeStatus::Active->value)->whereNotNull('user_id')->orderBy('id')->limit(4)->get() as $i => $employee) {
            $enrollment = resolve(Enrol::class)->handle($actor, $employee, $barista, EnrollmentSource::Assigned);
            resolve(BookSession::class)->handle($actor, $enrollment, $sessions[$i % 2]);
        }

        foreach (Enrollment::query()->where('status', 'completed')->get() as $done) {
            resolve(IssueCertificate::class)->handle($done);
        }

        $this->command->info('Training sessions and certificates seeded.');
    }

    private function seedCatalog(string $password, User $learner): void
    {
        $trainer = User::query()->forceCreate(['name' => 'Tala Trainer', 'email' => self::TRAINER, 'password' => $password, 'status' => 'active', 'email_verified_at' => now(), 'locale' => 'en']);
        resolve(AssignRoles::class)->handle(Actor::system('demo-seed'), $trainer, ['trainer' => [], 'employee' => []]);
        $actor = Actor::user($trainer);
        $dubai = Branch::query()->where('code', 'DXB')->firstOrFail();

        $food = $this->course($actor, [
            'title' => ['en' => 'Food safety basics', 'ar' => 'أساسيات سلامة الغذاء'],
            'summary' => ['en' => 'Keep food and guests safe, every shift.', 'ar' => 'حافظ على سلامة الطعام والضيوف في كل مناوبة.'],
            'description' => ['en' => "Everyone who handles food in our stores takes this course once a year.\n\n- Clean hands and surfaces\n- Safe temperatures\n- Allergens", 'ar' => "يأخذ هذه الدورة سنويًا كل من يتعامل مع الطعام في متاجرنا.\n\n- نظافة اليدين والأسطح\n- درجات الحرارة الآمنة\n- مسببات الحساسية"],
            'category' => 'safety', 'durationMinutes' => 30, 'validityMonths' => 12,
            'audience' => ['branches' => [$dubai->ulid]], 'dueDays' => 30,
        ], [
            ['kind' => 'text', 'title' => ['en' => 'Clean hands', 'ar' => 'نظافة اليدين'], 'durationMinutes' => 5, 'body' => ['en' => "Wash your hands for 20 seconds with soap.\n\n- Before handling food\n- After breaks\n- After touching money", 'ar' => "اغسل يديك 20 ثانية بالصابون.\n\n- قبل لمس الطعام\n- بعد الاستراحة\n- بعد لمس النقود"]],
            ['kind' => 'video', 'title' => ['en' => 'Safe temperatures', 'ar' => 'درجات الحرارة الآمنة'], 'durationMinutes' => 8, 'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
            ['kind' => 'quiz', 'title' => ['en' => 'Quick check', 'ar' => 'اختبار سريع'], 'durationMinutes' => 5, 'questions' => [
                ['type' => 'single', 'prompt' => ['en' => 'How long should you wash your hands?', 'ar' => 'كم ثانية تغسل يديك؟'], 'options' => [
                    ['id' => 'five', 'label' => ['en' => '5 seconds', 'ar' => '5 ثوانٍ'], 'correct' => false],
                    ['id' => 'twenty', 'label' => ['en' => '20 seconds', 'ar' => '20 ثانية'], 'correct' => true],
                ]],
                ['type' => 'true_false', 'prompt' => ['en' => 'Raw chicken goes on the top shelf of the fridge.', 'ar' => 'يوضع الدجاج النيء في الرف العلوي من الثلاجة.'], 'options' => [
                    ['id' => 'true', 'label' => ['en' => 'True', 'ar' => 'صحيح'], 'correct' => false],
                    ['id' => 'false', 'label' => ['en' => 'False', 'ar' => 'خطأ'], 'correct' => true],
                ]],
            ]],
        ]);
        $service = $this->course($actor, [
            'title' => ['en' => 'Customer service essentials', 'ar' => 'أساسيات خدمة العملاء'],
            'summary' => ['en' => 'Greet, listen and solve problems with a smile.', 'ar' => 'رحّب واستمع وحُلّ المشكلات بابتسامة.'],
            'category' => 'skills', 'durationMinutes' => 45,
        ], [
            ['kind' => 'text', 'title' => ['en' => 'The first ten seconds', 'ar' => 'الثواني العشر الأولى'], 'body' => ['en' => 'Make eye contact, smile and greet every customer within ten seconds.', 'ar' => 'انظر في عيني العميل وابتسم ورحّب به خلال عشر ثوانٍ.']],
            ['kind' => 'text', 'title' => ['en' => 'Handling complaints', 'ar' => 'التعامل مع الشكاوى'], 'body' => ['en' => "Listen, apologise, act, follow up.\n\n- Don’t argue\n- Offer a choice", 'ar' => "استمع واعتذر وتصرّف وتابع.\n\n- لا تجادل\n- قدّم خيارًا"]],
        ]);
        $this->course($actor, [
            'title' => ['en' => 'Leading a shift', 'ar' => 'قيادة المناوبة'],
            'summary' => ['en' => 'For supervisors: plan, brief and close a shift.', 'ar' => 'للمشرفين: خطّط للمناوبة وأطلع الفريق وأغلقها.'],
            'category' => 'leadership', 'durationMinutes' => 90, 'requiresApproval' => true,
        ], [
            ['kind' => 'text', 'title' => ['en' => 'The shift briefing', 'ar' => 'إحاطة المناوبة'], 'body' => ['en' => 'Five minutes, three priorities, one thank-you.', 'ar' => 'خمس دقائق، ثلاث أولويات، وشكر واحد.']],
        ]);
        $this->course($actor, [
            'title' => ['en' => 'Fire safety', 'ar' => 'السلامة من الحرائق'],
            'summary' => ['en' => 'Know your exits and your extinguishers.', 'ar' => 'اعرف مخارجك وطفايات الحريق.'],
            'category' => 'safety', 'durationMinutes' => 20, 'validityMonths' => 24,
        ], [
            ['kind' => 'text', 'title' => ['en' => 'Raise the alarm', 'ar' => 'أطلق الإنذار'], 'body' => ['en' => 'Pull the nearest call point, then leave by the nearest exit.', 'ar' => 'اسحب أقرب نقطة إنذار، ثم اخرج من أقرب مخرج.']],
        ]);

        $path = resolve(SaveLearningPath::class)->handle($actor, LearningPathInputData::from([
            'title' => ['en' => 'New store starter', 'ar' => 'بداية المتجر الجديد'],
            'description' => ['en' => 'The first two courses for everyone joining a store.', 'ar' => 'أول دورتين لكل من ينضم إلى متجر.'],
            'courses' => [$food->ulid, $service->ulid],
        ]));
        resolve(ChangeLearningPathStatus::class)->handle($actor, $path, CourseStatus::Published);

        // The demo employee is half-way through food safety; two colleagues finished customer service.
        $employee = Employee::query()->where('user_id', $learner->id)->firstOrFail();
        $enrollment = resolve(Enrol::class)->handle($actor, $employee, $food, EnrollmentSource::Assigned);
        resolve(CompleteModule::class)->handle($enrollment, $food->modules()->where('kind', ModuleKind::Text->value)->firstOrFail());

        foreach (Employee::query()->where('status', EmployeeStatus::Active->value)->whereKeyNot($employee->id)->orderBy('id')->limit(2)->get() as $colleague) {
            $done = resolve(Enrol::class)->handle($actor, $colleague, $service, EnrollmentSource::Assigned);
            $service->modules()->get()->each(fn (CourseModule $module) => resolve(CompleteModule::class)->handle($done, $module));
        }

        $quiz = $food->modules()->where('kind', ModuleKind::Quiz->value)->firstOrFail();
        $passer = Employee::query()->where('current_branch_id', $dubai->id)->whereKeyNot($employee->id)->orderBy('id')->first();

        if ($passer instanceof Employee) {
            $their = resolve(Enrol::class)->handle($actor, $passer, $food, EnrollmentSource::Assigned);
            resolve(SubmitQuizAttempt::class)->handle($their, $quiz, [$quiz->questions()->firstOrFail()->ulid => ['five']]);
        }
    }

    /**
     * @param  array<string, mixed>  $details
     * @param  list<array<string, mixed>>  $modules
     */
    private function course(Actor $actor, array $details, array $modules): Course
    {
        $course = resolve(SaveCourse::class)->handle($actor, CourseInputData::from($details));
        resolve(SaveCourseModules::class)->handle($actor, $course, ModulesInputData::from(['modules' => $modules]));

        return resolve(ChangeCourseStatus::class)->handle($actor, $course->fresh() ?? $course, CourseStatus::Published);
    }
}
