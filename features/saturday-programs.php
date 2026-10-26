<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('saturday-programs')
    ->title('Saturday programming page')
    ->section(Section::Pages)
    ->level(1)
    ->needs(['layout'])
    ->summary('Special Saturday events, such as Spooktacular Science. They are drop-in and free with admission. The page lists upcoming events only.')
    ->teaches(['Models', 'Migrations', 'Factories', 'Query scopes', 'Dates with Carbon'])
    ->test('SaturdayProgramsTest.php')
    ->steps([
        Step::make('Claim the feature and copy in its test')
            ->run('php artisan guide:start saturday-programs')
            ->check(Check::fileExists('tests/Feature/SaturdayProgramsTest.php')),

        Step::make('Create the SaturdayEvent model, migration and factory')
            ->run('php artisan make:model SaturdayEvent -mf')
            ->check(Check::classExists('App\Models\SaturdayEvent')),

        Step::make('Add the columns, then migrate')
            ->run('php artisan migrate')
            ->code("\$table->date('date');\n\$table->string('title');\n\$table->text('description')->nullable();\n\$table->string('schedule')->nullable();")
            ->explain('schedule holds text such as "Shows at 11:00 AM and 1:30 PM". Update #[Fillable] and the casts on the model.')
            ->check(Check::table('saturday_events', ['date', 'title', 'description', 'schedule'])),

        Step::make('Fill in the factory')
            ->check(Check::fileContains('database/factories/SaturdayEventFactory.php', "'title'")),

        Step::make('Add an upcoming scope')
            ->explain('Add a scope that keeps events from today onward. See the upcoming scope in app/Models/ProgramSession.php for the pattern.')
            ->docs('https://laravel.com/docs/eloquent#local-scopes')
            ->check(Check::fileContains('app/Models/SaturdayEvent.php', 'function upcoming')),

        Step::make('Create a controller')
            ->run('php artisan make:controller SaturdayProgramController --invokable')
            ->check(Check::classExists('App\Http\Controllers\SaturdayProgramController')),

        Step::make('Add the route')
            ->code("Route::get('/saturdays', SaturdayProgramController::class)->name('saturday-programs');")
            ->explain('Put it under the Explore heading in routes/web.php.')
            ->check(Check::route('saturday-programs')),

        Step::make('Create the view')
            ->explain('Create resources/views/saturday-programs.blade.php. Say that events are "Free with admission". Show each event as a card with its date, title, schedule and description.')
            ->check(Check::view('saturday-programs')),

        Step::make('Add the page to the Explore menu')
            ->check(Check::fileContains('resources/views/layouts/site.blade.php', "'saturday-programs'")),

        Step::make('Make the acceptance test pass')
            ->run('php artisan guide:verify saturday-programs')
            ->check(Check::testPassed('SaturdayProgramsTest')),
    ]);
