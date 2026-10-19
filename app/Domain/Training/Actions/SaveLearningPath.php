<?php

declare(strict_types=1);

namespace App\Domain\Training\Actions;

use App\Domain\Training\Data\LearningPathInputData;
use App\Domain\Training\Models\Course;
use App\Domain\Training\Models\LearningPath;
use App\Support\Actors\Actor;
use Illuminate\Database\ConnectionInterface;

/** Creates or edits a learning path: its texts and its courses in order. */
final readonly class SaveLearningPath
{
    public function __construct(private ConnectionInterface $db) {}

    public function handle(Actor $actor, LearningPathInputData $input, ?LearningPath $path = null): LearningPath
    {
        return $this->db->transaction(function () use ($actor, $input, $path): LearningPath {
            $path ??= new LearningPath(['created_by' => $actor->user?->id]);
            $path->fill([
                'title' => ['en' => $input->title['en'], 'ar' => ($input->title['ar'] ?? null) ?: $input->title['en']],
                'description' => ($input->description['en'] ?? '') === '' ? null : ['en' => $input->description['en'], 'ar' => ($input->description['ar'] ?? null) ?: $input->description['en']],
            ])->save();

            $ids = [];

            foreach (Course::query()->whereIn('ulid', $input->courses)->get(['id', 'ulid']) as $course) {
                $ids[$course->ulid] = $course->id;
            }

            $sync = [];

            foreach ($input->courses as $position => $ulid) {
                if (isset($ids[$ulid])) {
                    $sync[$ids[$ulid]] = ['position' => $position];
                }
            }

            $path->courses()->sync($sync);

            activity('training')->causedBy($actor->user)->performedOn($path)->event($path->wasRecentlyCreated ? 'created' : 'updated')->log('Learning path saved');

            return $path;
        });
    }
}
