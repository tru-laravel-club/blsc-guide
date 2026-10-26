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
    ->steps([
        Step::make('Install the April UI Livewire starter kit')
            ->run('laravel new blsc-website --using=yungifez/april-ui-starter-kit')
            ->check(Check::route('login', 'register', 'profile.edit')),
    ]);
