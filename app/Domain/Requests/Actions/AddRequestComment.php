<?php

declare(strict_types=1);

namespace App\Domain\Requests\Actions;

use App\Domain\Requests\Models\EmployeeRequest;
use App\Domain\Requests\Models\RequestComment;
use App\Support\Actors\Actor;

/** Adds a message to a request's thread (handoff 12). */
final readonly class AddRequestComment
{
    public function handle(Actor $actor, EmployeeRequest $request, string $body): RequestComment
    {
        $comment = new RequestComment(['request_id' => $request->id, 'user_id' => $actor->user?->id, 'body' => trim($body)]);
        $comment->save();

        return $comment;
    }
}
