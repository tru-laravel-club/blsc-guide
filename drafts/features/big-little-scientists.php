<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('big-little-scientists')
    ->title('BIG Little Scientists page')
    ->section(Section::Pages)
    ->level(1)
    ->needs(['layout'])
    ->summary('A drop-in program for ages 3 to 5 on Thursday mornings. The page explains the program and lists the theme of each week. No sign-up and no payment.')
    ->teaches(['Models', 'Migrations', 'Factories', 'Seeders', 'Invokable controllers', 'Blade @foreach'])
    ->test('BigLittleScientistsTest.php')
    ->steps([
        Step::make('Claim the feature and copy in its test')
            ->run('php artisan guide:start big-little-scientists')
            ->explain('This copies tests/Feature/BigLittleScientistsTest.php into the app. It fails now. You are done when it passes.')
            ->check(Check::fileExists('tests/Feature/BigLittleScientistsTest.php')),

        Step::make('Create the WeeklyTheme model, migration and factory')
            ->run('php artisan make:model WeeklyTheme -mf')
            ->explain('One WeeklyTheme is one Thursday, such as Oct 8: Magical Music. Open the three new files and read them.')
            ->docs('https://laravel.com/docs/eloquent#generating-model-classes')
            ->check(Check::classExists('App\Models\WeeklyTheme')),

        Step::make('Add the date and title columns, then migrate')
            ->run('php artisan migrate')
            ->code("\$table->date('date');\n\$table->string('title');")
            ->explain('Add the columns in the new migration file before you run the command. Also add both names to #[Fillable] on the model, and cast date to a date.')
            ->check(Check::table('weekly_themes', ['date', 'title'])),

        Step::make('Fill in the factory')
            ->code("'date' => fake()->dateTimeBetween('now', '+3 months'),\n'title' => fake()->words(2, true),")
            ->explain('The tests use the factory to make themes. Open database/factories/WeeklyThemeFactory.php.')
            ->check(Check::fileContains('database/factories/WeeklyThemeFactory.php', "'title'")),

        Step::make('Seed the Fall 2026 themes')
            ->run('php artisan make:seeder WeeklyThemeSeeder')
            ->explain('Oct 8 Magical Music, Oct 15 Fun in Fall, Oct 22 Incredible Earth, Oct 29 Spooky Science, Nov 5 Up in the Air, Nov 12 Sticky Things, Nov 19 Everything Colourful, Nov 26 Fabulous Fossils, Dec 3 Things That Go, Dec 10 Can I Code it? Call the seeder from DatabaseSeeder.')
            ->check(Check::fileContains('database/seeders/DatabaseSeeder.php', 'WeeklyThemeSeeder')),

        Step::make('Create an invokable controller')
            ->run('php artisan make:controller BigLittleScientistsController --invokable')
            ->explain('An invokable controller does one job, in its __invoke() method. Load the themes ordered by date and pass them to the view.')
            ->check(Check::classExists('App\Http\Controllers\BigLittleScientistsController')),

        Step::make('Add the route')
            ->code("Route::get('/big-little-scientists', BigLittleScientistsController::class)\n    ->name('big-little-scientists');")
            ->explain('Put it in routes/web.php under the Discover heading.')
            ->check(Check::route('big-little-scientists')),

        Step::make('Create the view')
            ->explain('Create resources/views/big-little-scientists.blade.php. Wrap it in <x-layouts::site>. Say that it runs Thursdays 9:00 to 10:00 AM for ages 3 to 5, that siblings are welcome, and that there is no need to sign up. Copy the card style from resources/views/programs/index.blade.php.')
            ->check(Check::view('big-little-scientists')),

        Step::make('Add the page to the Discover menu')
            ->explain('Add an entry to the Discover list at the top of resources/views/layouts/site.blade.php.')
            ->check(Check::fileContains('resources/views/layouts/site.blade.php', "'big-little-scientists'")),

        Step::make('Make the acceptance test pass')
            ->run('php artisan guide:verify big-little-scientists')
            ->check(Check::testPassed('BigLittleScientistsTest')),
    ]);
