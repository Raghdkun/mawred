<?php

declare(strict_types=1);

namespace App\Domain\Leave\Actions;

use App\Domain\Forms\Models\FormSubmission;
use App\Domain\Leave\Data\LeaveQuoteData;
use App\Domain\Leave\Enums\LeaveRequestKind;
use App\Domain\Leave\Enums\LeaveRequestStatus;
use App\Domain\Leave\Models\LeaveRequest;
use App\Domain\Leave\Support\LeaveQuotes;
use App\Domain\People\Models\Employee;
use App\Domain\Requests\Models\EmployeeRequest;
use App\Domain\Requests\Models\RequestType;
use App\Support\Errors\DomainRuleViolation;

/**
 * Holds the days a time-away request asks for while it is decided (D64), or moves the hold when
 * a returned request is sent again. Anything the quote objects to blocks the request (D65).
 * Runs inside the submitting transaction, so a refusal sends nothing.
 */
final readonly class PlaceLeaveHold
{
    public function __construct(private LeaveQuotes $quotes) {}

    public function handle(EmployeeRequest $request, RequestType $type, Employee $employee, FormSubmission $submission): ?LeaveRequest
    {
        $existing = LeaveRequest::query()->where('request_id', $request->id)->first();
        $quote = $this->quotes->quote($employee, $type, $submission->data, $existing?->id);

        if (! $quote instanceof LeaveQuoteData) {
            return null;
        }

        $kind = LeaveRequestKind::from($quote->kind);
        $field = $kind === LeaveRequestKind::Leave ? 'dates' : 'date';

        if ($quote->from === null || $quote->to === null) {
            throw new DomainRuleViolation('leave.no_dates', __('Choose the dates you need.'), [$field => [__('Choose the dates you need.')]]);
        }

        if (! $quote->ok()) {
            $problem = $quote->problems[0];
            $target = in_array($problem['code'], ['leave.bad_times', 'leave.too_long', 'leave.quota'], true) ? 'to_time' : $field;

            throw new DomainRuleViolation($problem['code'], $problem['message'], [$target => array_map(fn (array $p): string => $p['message'], $quote->problems)]);
        }

        if ($quote->attachmentRequired && ! $submission->uploads()->exists()) {
            $message = __('Attach a medical note or another supporting document for this leave.');

            throw new DomainRuleViolation('leave.attachment_required', $message, ['medical_note' => [$message]]);
        }

        $answers = $submission->data;
        $leaveRequest = $existing ?? new LeaveRequest(['request_id' => $request->id]);
        $leaveRequest->fill([
            'employee_id' => $employee->id,
            'kind' => $kind,
            'leave_type_id' => $kind === LeaveRequestKind::Leave ? $type->leave_type_id : null,
            'starts_on' => $quote->from,
            'ends_on' => $quote->to,
            'half_day' => $kind === LeaveRequestKind::Leave && $quote->amount === 0.5 && is_string($answers['half_day'] ?? null) ? $answers['half_day'] : null,
            'from_time' => $kind === LeaveRequestKind::Hourly ? $answers['from_time'] ?? null : null,
            'to_time' => $kind === LeaveRequestKind::Hourly ? $answers['to_time'] ?? null : null,
            'minutes' => $kind === LeaveRequestKind::Hourly ? (int) $quote->amount : null,
            'amount' => $kind === LeaveRequestKind::Hourly ? 0 : $quote->amount,
            'status' => LeaveRequestStatus::Pending,
        ])->save();

        return $leaveRequest;
    }
}
