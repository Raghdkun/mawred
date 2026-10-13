<?php

declare(strict_types=1);

use App\Http\Controllers\Web\Audit\AuditLogController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('audit', [AuditLogController::class, 'index'])->name('audit.index');
});
