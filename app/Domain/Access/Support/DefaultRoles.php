<?php

declare(strict_types=1);

namespace App\Domain\Access\Support;

use App\Domain\Access\Enums\FieldAccess;
use App\Domain\Access\Enums\HomeLayout;
use App\Domain\Access\Enums\Scope;

/**
 * The seeded roles (brief §6). They are editable after seeding; `is_system` only prevents the
 * role itself from being deleted or renamed. HR configures the real grants.
 */
final class DefaultRoles
{
    public const string SUPER_ADMIN = 'super_admin';

    public const string INTEGRATION_BOT = 'integration_bot';

    /**
     * @return array<string, array{
     *     label: array{en: string, ar: string},
     *     description: array{en: string, ar: string},
     *     rank: int,
     *     home: HomeLayout,
     *     requires_2fa: bool,
     *     super_admin?: bool,
     *     api_only?: bool,
     *     grants: array<string, Scope>,
     *     fields?: array<string, FieldAccess>,
     * }>
     */
    public static function definitions(): array
    {
        $allSensitive = array_fill_keys(PermissionCatalog::SENSITIVE_FIELDS, FieldAccess::Write);

        return [
            self::SUPER_ADMIN => [
                'label' => ['en' => 'Super Admin', 'ar' => 'المشرف العام'],
                'description' => ['en' => 'Full access to everything, including impersonation.', 'ar' => 'صلاحيات كاملة على كل شيء، بما في ذلك انتحال الهوية.'],
                'rank' => 100,
                'home' => HomeLayout::HrAdmin,
                'requires_2fa' => true,
                'super_admin' => true,
                'grants' => [],
            ],
            'hr_admin' => [
                'label' => ['en' => 'HR Admin', 'ar' => 'مدير الموارد البشرية'],
                'description' => ['en' => 'Runs HR across the company and configures modules.', 'ar' => 'يدير الموارد البشرية على مستوى الشركة ويهيّئ الوحدات.'],
                'rank' => 90,
                'home' => HomeLayout::HrAdmin,
                'requires_2fa' => true,
                'grants' => self::grantAll([
                    'setup', 'organization', 'people', 'access', 'policies', 'forms', 'workflows', 'requests',
                    'leave', 'scheduling', 'attendance', 'calendar', 'hiring', 'training', 'reports',
                    'notifications', 'integrations', 'audit', 'system',
                ], Scope::All),
                'fields' => $allSensitive,
            ],
            'hr_officer' => [
                'label' => ['en' => 'HR Officer', 'ar' => 'أخصائي الموارد البشرية'],
                'description' => ['en' => 'Day-to-day HR for the branches they are assigned to.', 'ar' => 'العمليات اليومية للموارد البشرية في الفروع المسندة إليه.'],
                'rank' => 80,
                'home' => HomeLayout::HrAdmin,
                'requires_2fa' => true,
                'grants' => [
                    'organization.view' => Scope::All,
                    ...self::grant('people', ['view', 'create', 'update', 'approve', 'export'], Scope::Branch),
                    ...self::grant('requests', ['view', 'approve', 'export'], Scope::Branch),
                    ...self::grant('leave', ['view', 'create', 'update', 'approve', 'export'], Scope::Branch),
                    ...self::grant('attendance', ['view', 'create', 'update', 'approve', 'export'], Scope::Branch),
                    ...self::grant('scheduling', ['view', 'export'], Scope::Branch),
                    ...self::grant('calendar', ['view', 'create', 'update'], Scope::Branch),
                    ...self::grant('training', ['view'], Scope::Branch),
                    ...self::grant('reports', ['view', 'export'], Scope::Branch),
                    'policies.view' => Scope::All,
                ],
                'fields' => [
                    'employee.national_id' => FieldAccess::Write,
                    'employee.passport' => FieldAccess::Write,
                    'employee.residency' => FieldAccess::Write,
                    'employee.date_of_birth' => FieldAccess::Write,
                    'employee.personal_contact' => FieldAccess::Write,
                ],
            ],
            'recruiter' => [
                'label' => ['en' => 'Recruiter', 'ar' => 'مسؤول التوظيف'],
                'description' => ['en' => 'Manages requisitions, postings and candidates.', 'ar' => 'يدير طلبات التوظيف والإعلانات والمرشحين.'],
                'rank' => 60,
                'home' => HomeLayout::Manager,
                'requires_2fa' => false,
                'grants' => [
                    ...self::grant('hiring', ['view', 'create', 'update', 'delete', 'approve', 'export', 'configure'], Scope::All),
                    ...self::grant('calendar', ['view', 'create', 'update'], Scope::All),
                    'organization.view' => Scope::All,
                    'reports.view' => Scope::All,
                ],
            ],
            'trainer' => [
                'label' => ['en' => 'Trainer', 'ar' => 'مدرّب'],
                'description' => ['en' => 'Runs courses, sessions, quizzes and certificates.', 'ar' => 'يدير الدورات والجلسات والاختبارات والشهادات.'],
                'rank' => 50,
                'home' => HomeLayout::Manager,
                'requires_2fa' => false,
                'grants' => [
                    ...self::grant('training', ['view', 'create', 'update', 'delete', 'approve', 'export'], Scope::All),
                    ...self::grant('calendar', ['view', 'create', 'update'], Scope::All),
                    'people.view' => Scope::All,
                ],
            ],
            'branch_manager' => [
                'label' => ['en' => 'Branch Manager', 'ar' => 'مدير الفرع'],
                'description' => ['en' => 'Leads a branch: approvals, rosters and attendance.', 'ar' => 'يقود الفرع: الموافقات والجداول والحضور.'],
                'rank' => 70,
                'home' => HomeLayout::Manager,
                'requires_2fa' => false,
                'grants' => [
                    ...self::grant('people', ['view', 'export'], Scope::Branch),
                    ...self::grant('requests', ['view', 'approve'], Scope::Branch),
                    ...self::grant('leave', ['view', 'approve'], Scope::Branch),
                    ...self::grant('attendance', ['view', 'update', 'approve', 'export'], Scope::Branch),
                    ...self::grant('scheduling', ['view', 'create', 'update', 'delete', 'approve', 'export'], Scope::Branch),
                    ...self::grant('calendar', ['view', 'create', 'update'], Scope::Branch),
                    ...self::grant('reports', ['view', 'export'], Scope::Branch),
                    'organization.view' => Scope::All,
                    'training.view' => Scope::Branch,
                ],
            ],
            'department_manager' => [
                'label' => ['en' => 'Department Manager', 'ar' => 'مدير القسم'],
                'description' => ['en' => 'Leads a department: approvals and team oversight.', 'ar' => 'يقود القسم: الموافقات ومتابعة الفريق.'],
                'rank' => 65,
                'home' => HomeLayout::Manager,
                'requires_2fa' => false,
                'grants' => [
                    ...self::grant('people', ['view'], Scope::Department),
                    ...self::grant('requests', ['view', 'approve'], Scope::Department),
                    ...self::grant('leave', ['view', 'approve'], Scope::Department),
                    ...self::grant('attendance', ['view', 'approve'], Scope::Department),
                    ...self::grant('scheduling', ['view', 'create', 'update', 'approve'], Scope::Department),
                    ...self::grant('calendar', ['view', 'create', 'update'], Scope::Department),
                    ...self::grant('reports', ['view'], Scope::Department),
                    'organization.view' => Scope::All,
                    'training.view' => Scope::Department,
                ],
            ],
            'team_lead' => [
                'label' => ['en' => 'Team Lead', 'ar' => 'قائد الفريق'],
                'description' => ['en' => 'Approves and follows up for their reporting line.', 'ar' => 'يوافق ويتابع فريقه المباشر وغير المباشر.'],
                'rank' => 55,
                'home' => HomeLayout::Manager,
                'requires_2fa' => false,
                'grants' => [
                    ...self::grant('people', ['view'], Scope::TeamTree),
                    ...self::grant('requests', ['view', 'approve'], Scope::TeamTree),
                    ...self::grant('leave', ['view', 'approve'], Scope::TeamTree),
                    ...self::grant('attendance', ['view', 'approve'], Scope::TeamTree),
                    ...self::grant('scheduling', ['view'], Scope::TeamTree),
                    ...self::grant('calendar', ['view', 'create'], Scope::TeamTree),
                ],
            ],
            'employee' => [
                'label' => ['en' => 'Employee', 'ar' => 'موظف'],
                'description' => ['en' => 'Self-service: own profile, requests, leave, schedule and learning.', 'ar' => 'الخدمة الذاتية: الملف الشخصي والطلبات والإجازات والجدول والتعلّم.'],
                'rank' => 10,
                'home' => HomeLayout::Employee,
                'requires_2fa' => false,
                'grants' => [
                    'people.view' => Scope::Own,
                    ...self::grant('requests', ['view', 'create', 'update'], Scope::Own),
                    ...self::grant('leave', ['view', 'create'], Scope::Own),
                    ...self::grant('attendance', ['view', 'create'], Scope::Own),
                    'scheduling.view' => Scope::Own,
                    ...self::grant('calendar', ['view', 'create'], Scope::Own),
                    ...self::grant('training', ['view', 'create'], Scope::Own),
                    'notifications.view' => Scope::All,
                ],
            ],
            'interviewer' => [
                'label' => ['en' => 'Interviewer', 'ar' => 'مُجري المقابلات'],
                'description' => ['en' => 'Sees only the candidates assigned to them.', 'ar' => 'يرى المرشحين المسندين إليه فقط.'],
                'rank' => 20,
                'home' => HomeLayout::Employee,
                'requires_2fa' => false,
                'grants' => [
                    ...self::grant('hiring', ['view', 'update'], Scope::Own), // own = assigned candidates
                    'calendar.view' => Scope::Own,
                ],
            ],
            'auditor' => [
                'label' => ['en' => 'Auditor (read-only)', 'ar' => 'مدقّق (قراءة فقط)'],
                'description' => ['en' => 'Reads everything, changes nothing. No sensitive fields unless granted.', 'ar' => 'يطّلع على كل شيء دون تعديل. لا يرى الحقول الحساسة إلا بمنح صريح.'],
                'rank' => 40,
                'home' => HomeLayout::HrAdmin,
                'requires_2fa' => true,
                'grants' => [
                    ...array_fill_keys(array_map(
                        fn (string $module): string => "{$module}.view",
                        array_keys(PermissionCatalog::MODULES),
                    ), Scope::All),
                    'audit.export' => Scope::All,
                    'reports.export' => Scope::All,
                ],
            ],
            self::INTEGRATION_BOT => [
                'label' => ['en' => 'Integration Bot', 'ar' => 'حساب التكامل'],
                'description' => ['en' => 'API-only account for integrations. No web sign-in.', 'ar' => 'حساب للواجهة البرمجية فقط، دون تسجيل دخول من الويب.'],
                'rank' => 0,
                'home' => HomeLayout::Employee,
                'requires_2fa' => false,
                'api_only' => true,
                'grants' => [],
            ],
        ];
    }

    /**
     * @param  list<string>  $actions
     * @return array<string, Scope>
     */
    private static function grant(string $module, array $actions, Scope $scope): array
    {
        $grants = [];

        foreach ($actions as $action) {
            $grants["{$module}.{$action}"] = $scope;
        }

        return $grants;
    }

    /**
     * @param  list<string>  $modules
     * @return array<string, Scope>
     */
    private static function grantAll(array $modules, Scope $scope): array
    {
        $grants = [];

        foreach ($modules as $module) {
            $grants = [...$grants, ...self::grant($module, PermissionCatalog::MODULES[$module]['actions'], $scope)];
        }

        return $grants;
    }
}
