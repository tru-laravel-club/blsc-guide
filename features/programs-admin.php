<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('programs-admin')
    ->title('Staff: manage programs')
    ->section(Section::Foundation)
    ->level(2)
    ->needs(['programs', 'staff-roles'])
    ->summary('Staff create, edit and delete programs and their sessions. Copy this pattern when you build other admin pages.')
    ->teaches(['Resource controllers', 'Form requests', 'Nested routes', 'Route model binding'])
    ->intro(<<<'TEXT'
        This feature is already in `main`. It is the pattern for every staff page. When you build one, you copy these files, so read them closely.

        A resource controller has one method for each job: list, show the form to create, save, show the form to edit, update and delete. One line in `routes/web.php` makes all of the routes.

        A form request holds the rules for a form. It checks that the user is staff, cleans the input, and validates it. The controller then receives only valid data, so it stays short.
        TEXT)
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/10', 'Laravel From Scratch, episode 10: Controllers')
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/12', 'Laravel From Scratch, episode 12: Form Request Classes')
    ->docs('https://laravel.com/docs/controllers#resource-controllers', 'Laravel docs: Resource controllers')
    ->steps([
        Step::make('Read the programs admin pages')
            ->explain(<<<'TEXT'
                Open `app/Http/Controllers/Admin/ProgramController.php`. Each method is short. It loads data, shows a view, or saves and redirects with a status message.

                Then open `app/Http/Requests/ProgramRequest.php`. `authorize()` asks the `access-admin` gate. `prepareForValidation()` makes the slug from the name when staff leave it empty. `rules()` makes the slug unique, but ignores the program that staff edit.

                Last, look at `resources/views/admin/programs`. `create` and `edit` share one `form.blade.php`.
                TEXT)
            ->think('The route group already checks the gate. Why does `ProgramRequest` check it again?')
            ->tryIt('Log in as `staff@example.com` with the password `password`. Open http://localhost:8000/admin/programs, and edit a program.')
            ->docs('https://laravel.com/docs/validation#form-request-validation', 'Laravel docs: Form request validation')
            ->check(Check::route('admin.programs.index', 'admin.programs.create', 'admin.programs.edit')),

        Step::make('Read the sessions admin pages')
            ->explain(<<<'TEXT'
                Sessions belong to a program, so their routes are nested: `/admin/programs/{program}/sessions/create`. The routes are also shallow. To edit a session, you need only its id, so the URL is `/admin/sessions/{session}/edit`.

                Open `app/Http/Requests/ProgramSessionRequest.php`. Staff type prices in dollars, such as `150.00`. `sessionData()` turns them into cents before the app saves them.
                TEXT)
            ->think('Why does the form take dollars, but the database stores cents?')
            ->tryIt('Run `php artisan route:list --name=sessions`. Compare the URLs of the create route and the edit route.')
            ->docs('https://laravel.com/docs/controllers#shallow-nesting', 'Laravel docs: Shallow nesting')
            ->check(Check::route('admin.programs.sessions.create', 'admin.sessions.edit')),
    ]);
