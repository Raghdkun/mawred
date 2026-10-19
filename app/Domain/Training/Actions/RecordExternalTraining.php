<?php

declare(strict_types=1);

namespace App\Domain\Training\Actions;

use App\Domain\Requests\Models\EmployeeRequest;
use App\Domain\Training\Models\ExternalTraining;
use App\Support\Actors\Actor;
use App\Support\Money\Money;

/**
 * An approved training request becomes an entry on the person's training record (D105): course,
 * provider, dates and cost from the request's answers. Once per request.
 */
final readonly class RecordExternalTraining
{
    /**
     * @param  array<string, mixed>  $answers
     */
    public function handle(Actor $actor, EmployeeRequest $request, array $answers): ExternalTraining
    {
        $existing = ExternalTraining::query()->where('request_id', $request->id)->first();

        if ($existing instanceof ExternalTraining) {
            return $existing;
        }

        $dates = is_array($answers['dates'] ?? null) ? $answers['dates'] : [];
        $cost = is_array($answers['cost'] ?? null) && is_int($answers['cost']['amount_minor'] ?? null) && is_string($answers['cost']['currency'] ?? null)
            ? new Money($answers['cost']['amount_minor'], $answers['cost']['currency'])
            : null;
        $text = fn (mixed $value, int $max): ?string => is_string($value) && trim($value) !== '' ? mb_substr(trim($value), 0, $max) : null;
        $date = fn (mixed $value): ?string => is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;

        $record = ExternalTraining::query()->create([
            'employee_id' => $request->requester_employee_id,
            'request_id' => $request->id,
            'title' => $text($answers['course'] ?? null, 200) ?? $request->number,
            'provider' => $text($answers['provider'] ?? null, 200),
            'starts_on' => $date($dates['from'] ?? null),
            'ends_on' => $date($dates['to'] ?? null) ?? $date($dates['from'] ?? null),
            'cost' => $cost,
            'created_by' => $actor->user?->id,
        ]);

        activity('training')->causedBy($actor->user)->performedOn($record)->event('recorded')->withProperties(['request' => $request->number])->log('External training recorded');

        return $record;
    }
}
