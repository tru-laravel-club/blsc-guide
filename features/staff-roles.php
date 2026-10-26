<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('staff-roles')
    ->title('Staff accounts')
    ->section(Section::Foundation)
    ->level(2)
    ->needs(['accounts'])
    ->summary('Mark some users as science centre staff. Only staff can open the admin pages.')
    ->teaches(['Migrations that change a table', 'Gates', 'Model default values'])
    ->steps([
        Step::make('Add an is_staff column to users')
            ->run('php artisan make:migration add_is_staff_to_users_table')
            ->check(Check::table('users', ['is_staff'])),
        Step::make('Define the access-admin gate')
            ->explain('See AppServiceProvider::boot(). Routes use it with the can:access-admin middleware.')
            ->check(Check::gate('access-admin')),
    ]);
