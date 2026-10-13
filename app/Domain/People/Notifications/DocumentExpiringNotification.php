<?php

declare(strict_types=1);

namespace App\Domain\People\Notifications;

use App\Domain\Access\Models\User;
use App\Domain\People\Models\EmployeeDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * In-app reminder that a document expires soon (or has expired). Text is rendered in the
 * recipient's language (ADR-0016). Email and WhatsApp channels join in Phases 3 and 4.
 */
final class DocumentExpiringNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $documentUlid,
        public readonly string $employeeUlid,
        public readonly string $employeeName,
        public readonly string $documentName,
        public readonly string $expiresOn,
        public readonly int $daysLeft,
        public readonly bool $aboutRecipient,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $locale = $notifiable instanceof User ? $notifiable->locale : null;
        $replace = ['document' => $this->documentName, 'name' => $this->employeeName, 'date' => $this->expiresOn, 'days' => $this->daysLeft];

        $title = match (true) {
            $this->daysLeft < 0 => $this->aboutRecipient
                ? __('Your :document has expired', $replace, $locale)
                : __(':name’s :document has expired', $replace, $locale),
            $this->aboutRecipient => __('Your :document expires in :days days', $replace, $locale),
            default => __(':name’s :document expires in :days days', $replace, $locale),
        };

        return [
            'category' => 'documents',
            'title' => $title,
            'body' => __('Expiry date: :date', $replace, $locale),
            'url' => "/people/{$this->employeeUlid}?tab=documents",
            'document' => $this->documentUlid,
        ];
    }

    public static function for(EmployeeDocument $document, int $daysLeft, bool $aboutRecipient, ?string $locale): self
    {
        $employee = $document->employee;
        $documentName = $document->type?->getTranslation('name', $locale ?? 'en');

        return new self(
            documentUlid: $document->ulid,
            employeeUlid: $employee->ulid ?? '',
            employeeName: $employee?->displayName($locale) ?? '',
            documentName: is_string($documentName) ? $documentName : '',
            expiresOn: $document->expires_on?->toDateString() ?? '',
            daysLeft: $daysLeft,
            aboutRecipient: $aboutRecipient,
        );
    }
}
