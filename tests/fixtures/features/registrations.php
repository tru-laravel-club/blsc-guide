<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('registrations')
    ->title('Club and camp registration')
    ->section(Section::Signups)
    ->level(3)
    ->needs(['payments'])
    ->summary('Blocked until payments is done.')
    ->steps([
        Step::make('Create the Registration model')
            ->run('php artisan make:model Registration -mf')
            ->check(Check::classExists('App\Models\Registration')),
    ]);
