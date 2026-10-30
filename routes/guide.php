<?php

use Illuminate\Support\Facades\Route;
use TruLaravelClub\BlscGuide\Http\GuideController;

Route::middleware('web')
    ->prefix(config('blsc-guide.path', 'guide'))
    ->name('blsc-guide.')
    ->group(function () {
        Route::get('/', [GuideController::class, 'index'])->name('index');
        Route::get('/start', [GuideController::class, 'start'])->name('start');
        Route::view('/git', 'blsc-guide::git')->name('git');
        Route::view('/reactive', 'blsc-guide::reactive')->name('reactive');
        Route::get('/{feature}', [GuideController::class, 'show'])->name('show');
        Route::post('/{feature}/done', [GuideController::class, 'markDone'])->name('done');
        Route::delete('/{feature}/done', [GuideController::class, 'unmarkDone'])->name('undone');
    });
