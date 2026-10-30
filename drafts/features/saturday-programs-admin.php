<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('saturday-programs-admin')
    ->title('Staff: manage Saturday events')
    ->section(Section::StaffTools)
    ->level(2)
    ->needs(['saturday-programs', 'staff-roles', 'programs-admin'])
    ->summary('Staff add, edit and delete Saturday events.')
    ->teaches(['Resource controllers', 'Form requests', 'Date validation'])
    ->test('Admin/SaturdayProgramsAdminTest.php')
    ->steps([
        Step::make('Claim the feature and copy in its test')
            ->run('php artisan guide:start saturday-programs-admin')
            ->check(Check::fileExists('tests/Feature/Admin/SaturdayProgramsAdminTest.php')),

        Step::make('Create the admin controller')
            ->run('php artisan make:controller Admin/SaturdayEventController --resource --model=SaturdayEvent')
            ->explain('Copy the pattern of app/Http/Controllers/Admin/ProgramController.php. Delete the show() method. The admin list shows past events too, newest first.')
            ->check(Check::classExists('App\Http\Controllers\Admin\SaturdayEventController')),

        Step::make('Create the form request')
            ->run('php artisan make:request SaturdayEventRequest')
            ->explain('date and title are required. description and schedule are optional.')
            ->check(Check::classExists('App\Http\Requests\SaturdayEventRequest')),

        Step::make('Add the routes to the staff group')
            ->code("Route::resource('saturday-events', Admin\\SaturdayEventController::class)->except('show');")
            ->explain('The route parameter is {saturday_event}. Laravel still binds it to a $saturdayEvent argument.')
            ->check(Check::route('admin.saturday-events.index', 'admin.saturday-events.create', 'admin.saturday-events.edit')),

        Step::make('Create the views')
            ->explain('Copy resources/views/admin/programs into resources/views/admin/saturday-events. Use a date input for the date.')
            ->check(Check::all(Check::view('admin.saturday-events.index'), Check::view('admin.saturday-events.create'), Check::view('admin.saturday-events.edit'))),

        Step::make('Add a sidebar link')
            ->explain('Add "Saturday events" to the Staff group in resources/views/layouts/app.blade.php.')
            ->check(Check::fileContains('resources/views/layouts/app.blade.php', "route('admin.saturday-events.index')")),

        Step::make('Make the acceptance test pass')
            ->run('php artisan guide:verify saturday-programs-admin')
            ->check(Check::testPassed('SaturdayProgramsAdminTest')),
    ]);
