<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Support;

use App\Domain\Forms\Models\FormSubmission;
use App\Domain\Integrations\Models\CognitoReceipt;
use App\Domain\Integrations\Models\CognitoSource;
use App\Domain\Integrations\Models\IntegrationConnection;
use App\Domain\People\Models\Employee;
use App\Domain\People\Models\EmployeeCustomValue;
use App\Domain\People\Models\EmployeeDocument;
use App\Domain\Workflows\Models\WebhookEndpoint;
use Illuminate\Database\Eloquent\Model;

/**
 * Every column encrypted with APP_KEY (Eloquent `encrypted*` casts), for key rotation
 * (`mawred:reencrypt`, ADR-0015). An arch test keeps this list in step with the models.
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
        ];
    }
}
