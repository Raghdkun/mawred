<?php

declare(strict_types=1);

namespace App\Domain\Requests\Enums;

use App\Domain\Workflows\Enums\InstanceStatus;
use App\Domain\Workflows\Enums\Outcome;
use App\Domain\Workflows\Models\WorkflowInstance;

/** A request's status, kept in step with its workflow instance. */
enum RequestStatus: string
{
    case InReview = 'in_review';
    case Returned = 'returned';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /** The status a finished (or returned) workflow gives its request. */
    public static function fromInstance(WorkflowInstance $instance): self
    {
        return match (true) {
            $instance->status === InstanceStatus::Returned => self::Returned,
            $instance->outcome === Outcome::Approved => self::Approved,
            $instance->outcome === Outcome::Rejected => self::Rejected,
            $instance->outcome === Outcome::Cancelled => self::Cancelled,
            default => self::Completed,
        };
    }

    public function isOpen(): bool
    {
        return $this === self::InReview || $this === self::Returned;
    }
}
