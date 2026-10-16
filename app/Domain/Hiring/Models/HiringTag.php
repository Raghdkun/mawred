<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;

/**
 * A free label recruiters put on candidates (talent pool search). Unique ignoring case.
 *
 * @property int $id
 * @property string $name
 */
#[Guarded(['id'])]
final class HiringTag extends Model {}
