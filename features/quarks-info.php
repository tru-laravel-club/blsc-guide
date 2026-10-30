<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('quarks-info')
    ->title('QUARKS page')
    ->section(Section::Pages)
    ->level(1)
    ->needs(['program-catalog', 'big-night-out-page'])
    ->summary('The QUARKS program for Grade 8 and 9 students, run with TRU. Uses the quarks program type.')
    ->teaches(['Route defaults', 'Reusing a controller', 'Blade @includeIf'])
    ->test('QuarksInfoTest.php')
    ->steps([
        Step::make('Claim the feature and copy in its test')
            ->run('php artisan guide:start quarks-info')
            ->check(Check::fileExists('tests/Feature/QuarksInfoTest.php')),

        Step::make('Add the route')
            ->code("Route::get('/quarks', [ProgramController::class, 'index'])\n    ->defaults('type', 'quarks')\n    ->name('quarks.index');")
            ->explain('This is the same pattern as the BIG Night Out page. Put it under the Discover heading.')
            ->check(Check::route('quarks.index')),

        Step::make('Write the QUARKS introduction')
            ->explain('Create resources/views/programs/intros/quarks.blade.php. Say that QUARKS is for students in Grade 8 and 9 and that BLSC runs it with Thompson Rivers University (TRU). Get the details from a lead.')
            ->check(Check::view('programs.intros.quarks')),

        Step::make('Add the page to the Discover menu')
            ->check(Check::fileContains('resources/views/layouts/site.blade.php', "'quarks.index'")),

        Step::make('Make the acceptance test pass')
            ->run('php artisan guide:verify quarks-info')
            ->check(Check::testPassed('QuarksInfoTest')),
    ]);
