<?php

declare(strict_types=1);

use App\Domain\Access\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', fn (User $user, int|string $id): bool => (int) $user->id === (int) $id);
