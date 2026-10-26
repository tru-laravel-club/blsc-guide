<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('child-profiles')
    ->title('Child profiles')
    ->section(Section::Foundation)
    ->level(2)
    ->needs(['accounts'])
    ->summary('Parents add their children once: name, birthdate, medical notes and photo consent. Registrations and party bookings use these profiles later. Collect only what BLSC needs.')
    ->teaches(['Resource controllers', 'Policies', 'hasMany relationships', 'Validation', 'Feature tests'])
    ->steps([
        Step::make('Create the Child model, migration and factory')
            ->run('php artisan make:model Child -mf')
            ->explain('Laravel names the table "children", not "childs".')
            ->check(Check::classExists('App\Models\Child')),

        Step::make('Add the columns, then migrate')
            ->run('php artisan migrate')
            ->code("\$table->foreignId('user_id')->constrained()->cascadeOnDelete();\n\$table->string('name');\n\$table->date('birthdate');\n\$table->text('medical_notes')->nullable();\n\$table->boolean('photo_consent')->default(false);")
            ->check(Check::table('children', ['user_id', 'name', 'birthdate', 'medical_notes', 'photo_consent'])),

        Step::make('Connect users and children')
            ->explain('Add children(): HasMany on User and user(): BelongsTo on Child.')
            ->check(Check::fileContains('app/Models/User.php', 'function children')),

        Step::make('Create a resource controller')
            ->run('php artisan make:controller ChildController --resource --model=Child')
            ->check(Check::classExists('App\Http\Controllers\ChildController')),

        Step::make('Add the routes for logged-in parents')
            ->code("Route::resource('children', ChildController::class)->except('show');")
            ->explain('Put it inside the auth and verified group in routes/web.php.')
            ->check(Check::route('children.index', 'children.create', 'children.edit')),

        Step::make('Protect each child with a policy')
            ->run('php artisan make:policy ChildPolicy --model=Child')
            ->explain('A parent can only see and change their own children. Staff do not get access here.')
            ->docs('https://laravel.com/docs/authorization#creating-policies')
            ->check(Check::classExists('App\Policies\ChildPolicy')),

        Step::make('Create the views and add a sidebar link')
            ->explain('Use the admin program views as a pattern. Add "My children" to the Account group in resources/views/layouts/app.blade.php.')
            ->check(Check::fileContains('resources/views/layouts/app.blade.php', "route('children.index')")),

        Step::make('Write the tests')
            ->run('php artisan make:test ChildProfilesTest')
            ->explain('Test that a parent can add, edit and delete a child, and that a parent cannot see another parent\'s child.')
            ->check(Check::fileExists('tests/Feature/ChildProfilesTest.php')),
    ]);
