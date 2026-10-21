<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Support;

use App\Domain\Attendance\Models\Kiosk;
use App\Domain\Forms\Models\FormSubmission;
use App\Domain\Hiring\Models\Candidate;
use App\Domain\Hiring\Models\CandidateMessage;
use App\Domain\Hiring\Models\Offer;
use App\Domain\Integrations\Models\CognitoReceipt;
use App\Domain\Integrations\Models\CognitoSource;
use App\Domain\Integrations\Models\IntegrationConnection;
use App\Domain\Notifications\Models\PushSubscription;
use App\Domain\People\Models\Employee;
use App\Domain\People\Models\EmployeeAllowance;
use App\Domain\People\Models\EmployeeCustomValue;
use App\Domain\People\Models\EmployeeDocument;
use App\Domain\People\Models\EmploymentHistoryEntry;
use App\Domain\Training\Models\TrainingSession;
use App\Domain\Workflows\Models\WebhookEndpoint;
use Illuminate\Database\Eloquent\Model;

/**
 * Every column encrypted with APP_KEY (Eloquent `encrypted*` casts, and the `<name>_minor`
 * column behind an EncryptedMoneyCast), for key rotation (`mawred:reencrypt`, ADR-0015). A test
 * keeps this list in step with the models.
 */
final class EncryptedColumns
{
    /**
     * @return array<class-string<Model>, list<string>>
     */
    public static function all(): array
    {
        return [
            Employee::class => ['national_id', 'passport_number', 'residency_number', 'bank_details', 'personal_contact'],
            EmployeeDocument::class => ['number'],
            EmployeeCustomValue::class => ['value_encrypted'],
            FormSubmission::class => ['data'],
            WebhookEndpoint::class => ['secret'],
            IntegrationConnection::class => ['credentials'],
            CognitoSource::class => ['sample_payload'],
            CognitoReceipt::class => ['payload'],
            Kiosk::class => ['secret'],
            Candidate::class => ['email', 'phone'],
            CandidateMessage::class => ['body'],
            Offer::class => ['letter', 'salary_minor'],
            EmploymentHistoryEntry::class => ['salary_minor'],
            EmployeeAllowance::class => ['amount_minor'],
            TrainingSession::class => ['checkin_secret'],
            PushSubscription::class => ['public_key', 'auth_token'],
        ];
    }
}
