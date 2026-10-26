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
    ->steps([
        Step::make('Create the Program model')
            ->run('php artisan make:model Program -mfs')
            ->explain('-m makes a migration, -f a factory and -s a seeder.')
            ->check(Check::all(Check::classExists('App\Models\Program'), Check::table('programs', ['type', 'slug']))),
        Step::make('Create the ProgramSession model')
            ->run('php artisan make:model ProgramSession -mf')
            ->explain('It is not called Session because Laravel already uses that name for login sessions.')
            ->check(Check::all(Check::classExists('App\Models\ProgramSession'), Check::table('program_sessions', ['program_id', 'price_cents']))),
    ]);
