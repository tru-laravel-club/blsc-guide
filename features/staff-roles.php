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
    ->intro(<<<'TEXT'
        This feature is already in `main`. Read it before you build a staff page, because every staff page uses it.

        A user is staff when the `is_staff` column is true. Parents are not staff. A gate called `access-admin` asks one question: "Is this user staff?" Routes, controllers and views all ask the gate. They never check `is_staff` themselves.

        So the rule lives in one place. If BLSC adds roles later, you change the gate, and every staff page follows.
        TEXT)
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/17', 'Laravel From Scratch, episode 17: Authorization Using Gates')
    ->docs('https://laravel.com/docs/authorization#gates', 'Laravel docs: Gates')
    ->steps([
        Step::make('Read the migration that adds is_staff')
            ->run('php artisan make:migration add_is_staff_to_users_table')
            ->explain(<<<'TEXT'
                The command above made the migration. You do not run it again.

                Open the migration in `database/migrations` that ends in `add_is_staff_to_users_table.php`. It uses `Schema::table()`, not `Schema::create()`, because the `users` table already exists. The `down()` method removes the column again, so a maintainer can undo the migration.

                Then open `app/Models/User.php`. Find `$attributes`. It sets `is_staff` to false for every new user. Last, open `database/factories/UserFactory.php` and find the `staff()` state. Tests use it to make a staff user.
                TEXT)
            ->think('Why does the migration need a `down()` method?')
            ->tryIt('Run `echo "App\\Models\\User::where(\'is_staff\', true)->pluck(\'email\');" | php artisan tinker`. You see `staff@example.com`.')
            ->docs('https://laravel.com/docs/migrations#updating-tables', 'Laravel docs: Updating tables')
            ->docs('https://laravel.com/docs/eloquent#default-attribute-values', 'Laravel docs: Default attribute values')
            ->docs('https://laravel.com/docs/eloquent-factories#factory-states', 'Laravel docs: Factory states')
            ->check(Check::table('users', ['is_staff'])),

        Step::make('Read the access-admin gate')
            ->explain(<<<'TEXT'
                Open `app/Providers/AppServiceProvider.php`. In `boot()`, find `Gate::define('access-admin', ...)`. It returns true for staff.

                Now find the three places that ask the gate. The staff route group in `routes/web.php` uses the `can:access-admin` middleware. Each form request calls `can('access-admin')` in `authorize()`. The sidebar in `resources/views/layouts/app.blade.php` uses `@can('access-admin')` to hide the Staff links.
                TEXT)
            ->think('The sidebar hides the Staff links from parents. Why do the routes still need the middleware?')
            ->tryIt('Log in as `test@example.com` and open http://localhost:8000/admin/programs. You see "403 Forbidden". Log in as `staff@example.com`, and the page opens. Both passwords are `password`.')
            ->docs('https://laravel.com/docs/authorization#via-middleware', 'Laravel docs: Authorizing via middleware')
            ->docs('https://laravel.com/docs/authorization#via-blade-templates', 'Laravel docs: Authorizing in Blade')
            ->check(Check::gate('access-admin')),
    ]);
