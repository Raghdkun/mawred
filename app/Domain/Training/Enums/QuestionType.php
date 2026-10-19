<?php

declare(strict_types=1);

namespace App\Domain\Training\Enums;

/** Quiz question types (D102). True/false is a single choice with two fixed options. */
enum QuestionType: string
{
    case Single = 'single';
    case Multiple = 'multiple';
    case TrueFalse = 'true_false';
}
