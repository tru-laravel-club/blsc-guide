<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('weekly-themes-admin')
    ->title('Staff: manage weekly themes')
    ->section(Section::StaffTools)
    ->level(2)
    ->needs(['big-little-scientists', 'staff-roles', 'programs-admin'])
    ->summary('Staff set the BIG Little Scientists theme for each Thursday. Each Thursday has one theme only.')
    ->teaches(['Resource controllers', 'Form requests', 'Unique rules', 'Closure validation rules'])
    ->test('Admin/WeeklyThemesAdminTest.php')
    ->steps([
        Step::make('Claim the feature and copy in its test')
            ->run('php artisan guide:start weekly-themes-admin')
            ->check(Check::fileExists('tests/Feature/Admin/WeeklyThemesAdminTest.php')),

        Step::make('Create the admin controller')
            ->run('php artisan make:controller Admin/WeeklyThemeController --resource --model=WeeklyTheme')
            ->explain('Copy the pattern of app/Http/Controllers/Admin/ProgramController.php. Delete the show() method.')
            ->check(Check::classExists('App\Http\Controllers\Admin\WeeklyThemeController')),

        Step::make('Create the form request')
            ->run('php artisan make:request WeeklyThemeRequest')
            ->explain('The date must be unique, but ignore the theme that staff edit. See the slug rule in ProgramRequest. The date must also be a Thursday. Write a closure rule that calls $fail() when Carbon::parse($value)->isThursday() is false.')
            ->docs('https://laravel.com/docs/validation#using-closures')
            ->check(Check::classExists('App\Http\Requests\WeeklyThemeRequest')),

        Step::make('Add the routes to the staff group')
            ->code("Route::resource('weekly-themes', Admin\\WeeklyThemeController::class)->except('show');")
            ->check(Check::route('admin.weekly-themes.index', 'admin.weekly-themes.create', 'admin.weekly-themes.edit')),

        Step::make('Create the views')
            ->explain('Copy resources/views/admin/programs into resources/views/admin/weekly-themes. The list shows the date and the title, ordered by date.')
            ->check(Check::all(Check::view('admin.weekly-themes.index'), Check::view('admin.weekly-themes.create'), Check::view('admin.weekly-themes.edit'))),

        Step::make('Add a sidebar link')
            ->explain('Add "Weekly themes" to the Staff group in resources/views/layouts/app.blade.php.')
            ->check(Check::fileContains('resources/views/layouts/app.blade.php', "route('admin.weekly-themes.index')")),

        Step::make('Make the acceptance test pass')
            ->run('php artisan guide:verify weekly-themes-admin')
            ->check(Check::testPassed('WeeklyThemesAdminTest')),
    ]);
