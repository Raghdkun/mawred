<?php

declare(strict_types=1);

namespace App\Domain\Training\Support;

/**
 * Scores a quiz attempt (D102). Pure: no models or clock, so it runs in the Unit suite. A
 * question earns its points only when the chosen options are exactly the correct ones; the score
 * is the share of points earned, rounded down to a whole percent.
 */
final class QuizScorer
{
    /**
     * @param  list<array{id: string, points: int, correct: list<string>}>  $questions
     * @param  array<string, list<string>>  $answers  question id => chosen option ids
     */
    public static function score(array $questions, array $answers): int
    {
        $total = 0;
        $earned = 0;

        foreach ($questions as $question) {
            $points = max(1, $question['points']);
            $total += $points;
            $chosen = array_values(array_unique($answers[$question['id']] ?? []));
            $correct = array_values(array_unique($question['correct']));
            sort($chosen);
            sort($correct);

            if ($chosen !== [] && $chosen === $correct) {
                $earned += $points;
            }
        }

        return $total === 0 ? 0 : intdiv($earned * 100, $total);
    }
}
