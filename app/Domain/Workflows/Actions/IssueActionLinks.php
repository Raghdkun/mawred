<?php

declare(strict_types=1);

namespace App\Domain\Workflows\Actions;

use App\Domain\Workflows\Enums\Decision;
use App\Domain\Workflows\Enums\DecisionChannel;
use App\Domain\Workflows\Models\ActionToken;
use App\Domain\Workflows\Models\WorkflowTask;

/**
 * Approve / reject links for an email or message (ADR-0009): 32 random bytes each, only their
 * sha256 is stored, single-use, expiring (config `workflows.action_token_ttl_hours`), and void
 * once the task is decided through any channel.
 */
final readonly class IssueActionLinks
{
    /**
     * @return array<string, string> decision => URL
     */
    public function handle(WorkflowTask $task, DecisionChannel $channel = DecisionChannel::Email): array
    {
        $ttl = config('workflows.action_token_ttl_hours', 72);
        $links = [];

        foreach ([Decision::Approved, Decision::Rejected] as $decision) {
            $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
            ActionToken::query()->create([
                'workflow_task_id' => $task->id,
                'token_hash' => hash('sha256', $token),
                'decision' => $decision,
                'channel' => $channel,
                'expires_at' => now()->addHours(is_numeric($ttl) ? (int) $ttl : 72),
            ]);
            $links[$decision->value] = route('action-links.show', $token);
        }

        return $links;
    }
}
