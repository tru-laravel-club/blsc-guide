<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('accounts')
    ->title('Accounts and login')
    ->section(Section::Foundation)
    ->level(1)
    ->summary('Parents create an account, log in and edit their profile. The April UI starter kit provides this with Laravel Fortify.')
    ->teaches(['Authentication', 'Middleware', 'Fortify'])
    ->intro(<<<'TEXT'
        This feature is already in `main`. It came with the April UI starter kit. You do not build it, but many features need it, so learn how it works.

        Laravel Fortify handles login, registration and password reset. It has no pages of its own. The starter kit gives the pages, and Fortify gives the routes and the logic behind them.

        Pages that need a logged-in user sit inside a route group with the `auth` middleware. Middleware runs before the controller. If the visitor is a guest, the `auth` middleware sends them to the login page, and the controller never runs.
        TEXT)
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/14', 'Laravel From Scratch, episode 14: Authentication Explained')
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/15', 'Laravel From Scratch, episode 15: Require Authentication With Middleware')
    ->docs('https://laravel.com/docs/fortify#introduction', 'Laravel docs: Fortify')
    ->steps([
        Step::make('Read how login works')
            ->run('laravel new blsc-website --using=yungifez/april-ui-starter-kit')
            ->explain(<<<'TEXT'
                The command above made this app. You do not run it again.

                Open `app/Actions/Fortify/CreateNewUser.php`. Fortify calls it when someone registers. It validates the form and creates the user.

                Then open `routes/web.php`. Find the group with `middleware(['auth', 'verified'])`. Every route inside it needs a logged-in user with a verified email.
                TEXT)
            ->think('A guest opens `/dashboard`. Which code runs first, and what does the guest see?')
            ->tryIt('Open http://localhost:8000/dashboard in a private window. You go to the login page. Log in as `test@example.com` with the password `password`. Now the dashboard opens.')
            ->docs('https://laravel.com/docs/authentication#protecting-routes', 'Laravel docs: Protecting routes')
            ->check(Check::route('login', 'register', 'profile.edit')),
    ]);
