<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('layout')
    ->title('Site layout and menus')
    ->section(Section::Foundation)
    ->level(1)
    ->summary('The public page layout: the header with the Explore and Discover menus, and the footer.')
    ->teaches(['Blade components', 'Layouts', 'Slots'])
    ->steps([
        Step::make('Create the public layout')
            ->explain('Every public page wraps its content in <x-layouts::site>. Open resources/views/layouts/site.blade.php to see the menus.')
            ->check(Check::view('layouts.site')),
        Step::make('Add the home page')
            ->check(Check::route('home')),
    ]);
