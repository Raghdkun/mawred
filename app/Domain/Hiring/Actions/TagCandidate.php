<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Actions;

use App\Domain\Hiring\Models\Candidate;
use App\Domain\Hiring\Models\HiringTag;
use App\Support\Actors\Actor;
use Illuminate\Database\ConnectionInterface;

/** Replaces a candidate's tags; new names become tags, matched ignoring case. */
final readonly class TagCandidate
{
    public function __construct(private ConnectionInterface $db) {}

    /**
     * @param  list<string>  $names
     */
    public function handle(Actor $actor, Candidate $candidate, array $names): void
    {
        $this->db->transaction(function () use ($actor, $candidate, $names): void {
            $ids = [];

            foreach (array_unique(array_filter(array_map(fn (string $n): string => mb_substr(trim($n), 0, 40), $names))) as $name) {
                $tag = HiringTag::query()->whereRaw('lower(name) = ?', [mb_strtolower($name)])->first()
                    ?? HiringTag::query()->create(['name' => $name]);
                $ids[] = $tag->id;
            }

            $candidate->tags()->sync($ids);

            activity('hiring')->causedBy($actor->user)->performedOn($candidate)->event('tagged')
                ->withProperties(['tags' => count($ids)])->log('Candidate tags changed');
        });
    }
}
