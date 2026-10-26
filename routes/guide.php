<?php

use Illuminate\Support\Facades\Route;
use TruLaravelClub\BlscGuide\Http\GuideController;

Route::middleware('web')
    ->prefix(config('blsc-guide.path', 'guide'))
    ->name('blsc-guide.')
    ->group(function () {
        Route::get('/', [GuideController::class, 'index'])->name('index');
        Route::get('/{feature}', [GuideController::class, 'show'])->name('show');
    });
