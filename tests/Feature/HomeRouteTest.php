<?php

declare(strict_types=1);

use App\Domain\Access\Models\User;

it('sends guests to the login page', function (): void {
    $this->get(route('home'))->assertRedirect(route('login'));
});

it('sends signed-in users to their home', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertRedirect(route('dashboard'));
});
