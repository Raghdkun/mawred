<?php

declare(strict_types=1);

namespace App\Support\Rules;

use InvalidArgumentException;

/** A rule that doesn't follow `mawred.rule/v1` (unknown operator, wrong arity, too deep). */
final class RuleError extends InvalidArgumentException {}
