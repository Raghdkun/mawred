<?php

declare(strict_types=1);

namespace App\Domain\People\Notifications;

use App\Domain\Access\Models\User;
use App\Domain\People\Models\EmployeeDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * The wording of a document-expiry reminder in the recipient's language (ADR-0016). Delivery
 * goes through the notification router (in-app, then WhatsApp or email per the documents
 * category's chain, D44); `toArray` remains for the stored in-app shape.
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
     * @return array<string, string>
     */
    public function viaQueues(): array
    {
        return ['database' => 'notifications'];
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{subject: string, body: string}
     */
    public function text(?string $locale): array
    {
        $data = $this->wording($locale);

        return ['subject' => $data['title'], 'body' => $data['body']];
    }

    public function url(): string
    {
        return url("/people/{$this->employeeUlid}?tab=documents");
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->wording($notifiable instanceof User ? $notifiable->locale : null);
    }

    /**
     * @return array{category: string, title: string, body: string, url: string, document: string}
     */
    private function wording(?string $locale): array
    {
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
