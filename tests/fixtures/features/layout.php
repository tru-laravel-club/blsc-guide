<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('layout')
    ->title('Site layout and menus')
    ->section(Section::Foundation)
    ->level(1)
    ->summary('The shared layout of every page.')
    ->issue(12)
    ->steps([
        Step::make('Make the layout component')
            ->run('php artisan make:component Layout --view')
            ->check(Check::fileExists('resources/views/components/layout.blade.php')),
    ]);
