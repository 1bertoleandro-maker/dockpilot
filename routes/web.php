<?php

declare(strict_types=1);

use App\Http\Controllers\DocumentPreviewController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::middleware(['web', 'auth'])->group(function (): void {
    Route::get('/media/documents/{document}', DocumentPreviewController::class)
        ->name('documents.preview');
});
