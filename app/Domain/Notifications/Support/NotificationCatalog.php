<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Support;

use App\Domain\Notifications\Enums\NotificationCategory;

/**
 * Notification events with their category, allowed variables and default templates (ADR-0016).
 * Admins can override any template per channel and locale; rendering is SafeTemplate only.
 */
final class NotificationCatalog
{
    public const array COMMON_VARIABLES = ['name', 'requester', 'request_type', 'request_number', 'link'];

    /**
     * @return array<string, array{category: NotificationCategory, variables: list<string>, templates: array<string, array<string, array{subject?: string, body: string}>>}>
     */
    public static function events(): array
    {
        $vars = [...self::COMMON_VARIABLES, 'step', 'due_at', 'comment'];

        return [
            'approvals.task_assigned' => [
                'category' => NotificationCategory::Approvals,
                'variables' => $vars,
                'templates' => [
                    'in_app' => [
                        'en' => ['subject' => 'Approval needed: {{ request_type }}', 'body' => '{{ requester }} · {{ request_number }}'],
                        'ar' => ['subject' => 'مطلوب موافقتك: {{ request_type }}', 'body' => '{{ requester }} · {{ request_number }}'],
                    ],
                    'email' => [
                        'en' => ['subject' => 'Approval needed: {{ request_type }} ({{ request_number }})', 'body' => "Hello {{ name }},\n\n{{ requester }} sent a {{ request_type }} request ({{ request_number }}) that needs your decision."],
                        'ar' => ['subject' => 'مطلوب موافقتك: {{ request_type }} ({{ request_number }})', 'body' => "مرحبًا {{ name }}،\n\nأرسل {{ requester }} طلب {{ request_type }} ({{ request_number }}) وهو بانتظار قرارك."],
                    ],
                ],
            ],
            'approvals.task_reminder' => [
                'category' => NotificationCategory::Approvals,
                'variables' => $vars,
                'templates' => [
                    'in_app' => [
                        'en' => ['subject' => 'Reminder: {{ request_type }} is waiting', 'body' => '{{ requester }} · {{ request_number }}'],
                        'ar' => ['subject' => 'تذكير: {{ request_type }} بانتظارك', 'body' => '{{ requester }} · {{ request_number }}'],
                    ],
                    'email' => [
                        'en' => ['subject' => 'Reminder: {{ request_type }} ({{ request_number }}) is waiting', 'body' => "Hello {{ name }},\n\n{{ requester }}'s {{ request_type }} request ({{ request_number }}) is still waiting for your decision."],
                        'ar' => ['subject' => 'تذكير: {{ request_type }} ({{ request_number }}) بانتظارك', 'body' => "مرحبًا {{ name }}،\n\nما زال طلب {{ request_type }} ({{ request_number }}) من {{ requester }} بانتظار قرارك."],
                    ],
                ],
            ],
            'approvals.task_escalated' => [
                'category' => NotificationCategory::Approvals,
                'variables' => $vars,
                'templates' => [
                    'in_app' => [
                        'en' => ['subject' => 'Escalated to you: {{ request_type }}', 'body' => '{{ requester }} · {{ request_number }} is overdue'],
                        'ar' => ['subject' => 'صُعّد إليك: {{ request_type }}', 'body' => '{{ requester }} · {{ request_number }} متأخر'],
                    ],
                    'email' => [
                        'en' => ['subject' => 'Escalated to you: {{ request_type }} ({{ request_number }})', 'body' => "Hello {{ name }},\n\n{{ requester }}'s {{ request_type }} request ({{ request_number }}) passed its deadline and now needs your decision."],
                        'ar' => ['subject' => 'صُعّد إليك: {{ request_type }} ({{ request_number }})', 'body' => "مرحبًا {{ name }}،\n\nتجاوز طلب {{ request_type }} ({{ request_number }}) من {{ requester }} موعده، وهو الآن بانتظار قرارك."],
                    ],
                ],
            ],
            'approvals.task_delegated' => [
                'category' => NotificationCategory::Approvals,
                'variables' => $vars,
                'templates' => [
                    'in_app' => [
                        'en' => ['subject' => 'Delegated to you: {{ request_type }}', 'body' => '{{ requester }} · {{ request_number }}'],
                        'ar' => ['subject' => 'فُوّض إليك: {{ request_type }}', 'body' => '{{ requester }} · {{ request_number }}'],
                    ],
                    'email' => [
                        'en' => ['subject' => 'Delegated to you: {{ request_type }} ({{ request_number }})', 'body' => "Hello {{ name }},\n\nA {{ request_type }} request ({{ request_number }}) from {{ requester }} was delegated to you."],
                        'ar' => ['subject' => 'فُوّض إليك: {{ request_type }} ({{ request_number }})', 'body' => "مرحبًا {{ name }}،\n\nفُوّض إليك طلب {{ request_type }} ({{ request_number }}) من {{ requester }}."],
                    ],
                ],
            ],
            'requests.approved' => [
                'category' => NotificationCategory::Requests,
                'variables' => $vars,
                'templates' => [
                    'in_app' => [
                        'en' => ['subject' => 'Approved: your {{ request_type }}', 'body' => '{{ request_number }}'],
                        'ar' => ['subject' => 'تمت الموافقة على طلب {{ request_type }}', 'body' => '{{ request_number }}'],
                    ],
                    'email' => [
                        'en' => ['subject' => 'Approved: your {{ request_type }} ({{ request_number }})', 'body' => "Hello {{ name }},\n\nYour {{ request_type }} request ({{ request_number }}) was approved.\n\n{{ comment }}"],
                        'ar' => ['subject' => 'تمت الموافقة على طلب {{ request_type }} ({{ request_number }})', 'body' => "مرحبًا {{ name }}،\n\nتمت الموافقة على طلبك {{ request_type }} ({{ request_number }}).\n\n{{ comment }}"],
                    ],
                ],
            ],
            'requests.rejected' => [
                'category' => NotificationCategory::Requests,
                'variables' => $vars,
                'templates' => [
                    'in_app' => [
                        'en' => ['subject' => 'Not approved: your {{ request_type }}', 'body' => '{{ request_number }}'],
                        'ar' => ['subject' => 'لم تتم الموافقة على طلب {{ request_type }}', 'body' => '{{ request_number }}'],
                    ],
                    'email' => [
                        'en' => ['subject' => 'Not approved: your {{ request_type }} ({{ request_number }})', 'body' => "Hello {{ name }},\n\nYour {{ request_type }} request ({{ request_number }}) was not approved.\n\n{{ comment }}"],
                        'ar' => ['subject' => 'لم تتم الموافقة على طلب {{ request_type }} ({{ request_number }})', 'body' => "مرحبًا {{ name }}،\n\nلم تتم الموافقة على طلبك {{ request_type }} ({{ request_number }}).\n\n{{ comment }}"],
                    ],
                ],
            ],
            'requests.completed' => [
                'category' => NotificationCategory::Requests,
                'variables' => $vars,
                'templates' => [
                    'in_app' => [
                        'en' => ['subject' => 'Done: your {{ request_type }}', 'body' => '{{ request_number }}'],
                        'ar' => ['subject' => 'اكتمل طلب {{ request_type }}', 'body' => '{{ request_number }}'],
                    ],
                    'email' => [
                        'en' => ['subject' => 'Done: your {{ request_type }} ({{ request_number }})', 'body' => "Hello {{ name }},\n\nYour {{ request_type }} request ({{ request_number }}) is complete.\n\n{{ comment }}"],
                        'ar' => ['subject' => 'اكتمل طلب {{ request_type }} ({{ request_number }})', 'body' => "مرحبًا {{ name }}،\n\nاكتمل طلبك {{ request_type }} ({{ request_number }}).\n\n{{ comment }}"],
                    ],
                ],
            ],
            'requests.returned' => [
                'category' => NotificationCategory::Requests,
                'variables' => $vars,
                'templates' => [
                    'in_app' => [
                        'en' => ['subject' => 'Changes needed: {{ request_type }}', 'body' => '{{ comment }}'],
                        'ar' => ['subject' => 'مطلوب تعديل: {{ request_type }}', 'body' => '{{ comment }}'],
                    ],
                    'email' => [
                        'en' => ['subject' => 'Changes needed: {{ request_type }} ({{ request_number }})', 'body' => "Hello {{ name }},\n\nYour {{ request_type }} request ({{ request_number }}) was sent back for changes:\n\n{{ comment }}"],
                        'ar' => ['subject' => 'مطلوب تعديل: {{ request_type }} ({{ request_number }})', 'body' => "مرحبًا {{ name }}،\n\nأُعيد طلبك {{ request_type }} ({{ request_number }}) لإجراء تعديلات:\n\n{{ comment }}"],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array{category: NotificationCategory, variables: list<string>, templates: array<string, array<string, array{subject?: string, body: string}>>}|null
     */
    public static function event(string $event): ?array
    {
        return self::events()[$event] ?? null;
    }
}
