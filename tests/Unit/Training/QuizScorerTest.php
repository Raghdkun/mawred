<?php

declare(strict_types=1);

use App\Domain\Training\Support\QuizScorer;

it('scores exact answers by points and rounds down', function (): void {
    $questions = [
        ['id' => 'q1', 'points' => 1, 'correct' => ['a']],
        ['id' => 'q2', 'points' => 2, 'correct' => ['b', 'c']],
    ];

    expect(QuizScorer::score($questions, ['q1' => ['a'], 'q2' => ['c', 'b']]))->toBe(100)
        ->and(QuizScorer::score($questions, ['q1' => ['a'], 'q2' => ['b']]))->toBe(33)
        ->and(QuizScorer::score($questions, ['q2' => ['b', 'c']]))->toBe(66)
        ->and(QuizScorer::score($questions, ['q1' => ['a', 'x']]))->toBe(0)
        ->and(QuizScorer::score([], []))->toBe(0);
});
