<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('parties-info')
    ->title('Parties page')
    ->section(Section::Pages)
    ->level(1)
    ->needs(['layout', 'programs'])
    ->summary('What a birthday party includes, the themes, prices and times. Each party theme is a Program with the party type, so staff manage themes in the programs admin.')
    ->teaches(['Invokable controllers', 'Query scopes', 'Enums in queries'])
    ->test('PartiesInfoTest.php')
    ->steps([
        Step::make('Claim the feature and copy in its test')
            ->run('php artisan guide:start parties-info')
            ->check(Check::fileExists('tests/Feature/PartiesInfoTest.php')),

        Step::make('Create an invokable controller')
            ->run('php artisan make:controller PartyController --invokable')
            ->explain('Load the published programs with the party type, ordered by name. Use the published scope on Program and ProgramType::Party. Do not write the string "party" in the query.')
            ->check(Check::classExists('App\Http\Controllers\PartyController')),

        Step::make('Add the route')
            ->code("Route::get('/parties', PartyController::class)->name('parties');")
            ->explain('Put it under the Discover heading in routes/web.php.')
            ->check(Check::route('parties')),

        Step::make('Create the view')
            ->explain('Create resources/views/parties.blade.php. First say what every party includes, the times and the price. Say "A $25 deposit holds your date." Then show each theme as a card with its name, age range and summary. Get the current prices and times from a maintainer.')
            ->check(Check::view('parties')),

        Step::make('Add the page to the Discover menu')
            ->check(Check::fileContains('resources/views/layouts/site.blade.php', "'parties'")),

        Step::make('Make the acceptance test pass')
            ->run('php artisan guide:verify parties-info')
            ->check(Check::testPassed('PartiesInfoTest')),
    ]);
