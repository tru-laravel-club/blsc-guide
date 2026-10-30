<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('programs')
    ->title('Programs and sessions')
    ->section(Section::Foundation)
    ->level(2)
    ->summary('A Program is something BLSC offers, such as a club or camp. A ProgramSession is one run of it, with dates, price and spots.')
    ->teaches(['Models', 'Migrations', 'Factories', 'Relationships', 'Enums', 'Seeders'])
    ->intro(<<<'TEXT'
        This feature is already in `main`. Camps, clubs, parties, QUARKS and BIG Night Out all use it, so read it carefully.

        A program is something BLSC offers, such as the Junior Robotics & Coding Club. A session is one run of that program, such as "Fall 2026, Tuesdays". One program has many sessions. Each session has its own dates, price and number of spots.

        This split matters. The name and the description of a club stay the same every term. Only the dates and the price change. So staff add a new session each term, and they do not copy the whole program.
        TEXT)
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/8', 'Laravel From Scratch, episode 8: Databases, Migrations, and Eloquent')
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/16', 'Laravel From Scratch, episode 16: Eloquent Relationships')
    ->docs('https://laravel.com/docs/eloquent-relationships#one-to-many', 'Laravel docs: One-to-many relationships')
    ->steps([
        Step::make('Read the Program model')
            ->run('php artisan make:model Program -mfs')
            ->explain(<<<'TEXT'
                The command above made the model, the migration, the factory and the seeder. You do not run it again.

                Open `app/Models/Program.php`. Look at three things. The `type` column is cast to the `ProgramType` enum, so it can only be a camp, a club, QUARKS, a party or BIG Night Out. The `sessions()` method connects a program to its sessions. The `published` scope hides the programs that staff have not published yet.

                Then open `app/Enums/ProgramType.php` to see the five types.
                TEXT)
            ->think('Why is `type` an enum and not free text? What could go wrong with free text?')
            ->tryIt('Run `echo "App\\Models\\Program::published()->pluck(\'name\');" | php artisan tinker`. You see the sample programs from the seeder.')
            ->docs('https://laravel.com/docs/eloquent-mutators#enum-casting', 'Laravel docs: Enum casting')
            ->check(Check::all(Check::classExists('App\Models\Program'), Check::table('programs', ['type', 'slug']))),

        Step::make('Read the ProgramSession model')
            ->run('php artisan make:model ProgramSession -mf')
            ->explain(<<<'TEXT'
                The model is not called `Session`, because Laravel already uses that name for login sessions.

                Open `app/Models/ProgramSession.php`. The prices are in cents, such as `15000` for $150.00. Computers store decimal numbers such as 0.1 with small errors, and money must be exact. So the app stores whole cents and formats them only for display, in `price()`.

                Then read the `upcoming` scope. A session is upcoming when it has not ended yet. A session with no end date is upcoming when it has not started yet.
                TEXT)
            ->think('Why does the app store `15000` and not `150.00`?')
            ->tryIt('Run `echo "App\\Models\\ProgramSession::first()->price();" | php artisan tinker`. You see a price in dollars, such as `$150.00`.')
            ->docs('https://laravel.com/docs/eloquent#local-scopes', 'Laravel docs: Local scopes')
            ->check(Check::all(Check::classExists('App\Models\ProgramSession'), Check::table('program_sessions', ['program_id', 'price_cents']))),
    ]);
