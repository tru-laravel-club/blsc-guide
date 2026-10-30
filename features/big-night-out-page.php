<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('big-night-out-page')
    ->title('BIG Night Out page')
    ->section(Section::Pages)
    ->level(1)
    ->needs(['program-catalog'])
    ->summary('Upcoming adults-only evening events, such as the murder mystery. Each event is a Program with the big_night_out type.')
    ->teaches(['Route defaults', 'Reusing a controller', 'Blade @includeIf'])
    ->test('BigNightOutPageTest.php')
    ->steps([
        Step::make('Claim the feature and copy in its test')
            ->run('php artisan guide:start big-night-out-page')
            ->check(Check::fileExists('tests/Feature/BigNightOutPageTest.php')),

        Step::make('Add the route')
            ->code("Route::get('/big-night-out', [ProgramController::class, 'index'])\n    ->defaults('type', 'big_night_out')\n    ->name('big-night-out.index');")
            ->explain('The camps and clubs routes already use ProgramController this way. You write no new controller. Put the route under the Discover heading.')
            ->docs('https://laravel.com/docs/routing#parameters-default-values')
            ->check(Check::route('big-night-out.index')),

        Step::make('Let each program type add its own introduction')
            ->code("@includeIf('programs.intros.'.\$type->value)")
            ->explain('Put this under the <h1> in resources/views/programs/index.blade.php. @includeIf does nothing when the view does not exist, so camps and clubs do not change.')
            ->docs('https://laravel.com/docs/blade#including-subviews')
            ->check(Check::fileContains('resources/views/programs/index.blade.php', '@includeIf')),

        Step::make('Write the BIG Night Out introduction')
            ->explain('Create resources/views/programs/intros/big_night_out.blade.php. Say that the events are for adults only, ages 19 and up.')
            ->check(Check::view('programs.intros.big_night_out')),

        Step::make('Add the page to the Discover menu')
            ->check(Check::fileContains('resources/views/layouts/site.blade.php', "'big-night-out.index'")),

        Step::make('Make the acceptance test pass')
            ->run('php artisan guide:verify big-night-out-page')
            ->check(Check::testPassed('BigNightOutPageTest')),
    ]);
