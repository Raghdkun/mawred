<?php

declare(strict_types=1);

use App\Http\Controllers\Web\Access\ImpersonationController;
use App\Http\Controllers\Web\Access\RolesController;
use App\Http\Controllers\Web\Access\UsersController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('access')->name('access.')->group(function (): void {
    Route::get('roles', [RolesController::class, 'index'])->name('roles.index');
    Route::get('roles/{role}', [RolesController::class, 'show'])->name('roles.show');
    Route::patch('roles/{role}', [RolesController::class, 'update'])->name('roles.update');
    Route::put('roles/{role}/grants', [RolesController::class, 'updateGrants'])->name('roles.grants.update');
    Route::put('roles/{role}/fields', [RolesController::class, 'updateFields'])->name('roles.fields.update');
    Route::post('roles/{role}/clone', [RolesController::class, 'clone'])->name('roles.clone');
    Route::delete('roles/{role}', [RolesController::class, 'destroy'])->name('roles.destroy');

    Route::get('users', [UsersController::class, 'index'])->name('users.index');
    Route::put('users/{user}/roles', [UsersController::class, 'updateRoles'])->name('users.roles.update');
    Route::get('users/{user}/preview', [UsersController::class, 'preview'])->name('users.preview');

    Route::post('users/{user}/impersonate', [ImpersonationController::class, 'store'])->name('impersonate.start');
    Route::delete('impersonate', [ImpersonationController::class, 'destroy'])->name('impersonate.stop');
});
