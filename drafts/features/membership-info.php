<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('membership-info')
    ->title('Memberships page')
    ->section(Section::Pages)
    ->level(1)
    ->needs(['layout'])
    ->summary('Membership types, prices, benefits and the FAQ, such as reciprocal admission at other science centres.')
    ->teaches(['Route::view', 'Blade @foreach', 'April UI accordion'])
    ->test('MembershipInfoTest.php')
    ->steps([
        Step::make('Claim the feature and copy in its test')
            ->run('php artisan guide:start membership-info')
            ->check(Check::fileExists('tests/Feature/MembershipInfoTest.php')),

        Step::make('Add the route')
            ->code("Route::view('/memberships', 'memberships')->name('memberships');")
            ->explain('This page shows fixed text and needs no database, so Route::view() is enough. Put it under the Explore heading in routes/web.php.')
            ->docs('https://laravel.com/docs/routing#view-routes')
            ->check(Check::route('memberships')),

        Step::make('Create the view with the membership types')
            ->explain('Create resources/views/memberships.blade.php and wrap it in <x-layouts::site>. Put the types in a @php array at the top, as the menus are in layouts/site.blade.php. Show each type as a card with its price and benefits. Get the current types and prices from a maintainer. Do not guess them.')
            ->check(Check::view('memberships')),

        Step::make('Add the FAQ')
            ->code("<april:accordion type=\"single\" collapsible>\n    <april:accordion-item>\n        <slot:trigger>Can I visit other science centres?</slot:trigger>\n        <slot:content>...</slot:content>\n    </april:accordion-item>\n</april:accordion>")
            ->explain('One question must explain reciprocal admission at other science centres.')
            ->check(Check::fileContains('resources/views/memberships.blade.php', '<april:accordion')),

        Step::make('Add the page to the Explore menu')
            ->check(Check::fileContains('resources/views/layouts/site.blade.php', "'memberships'")),

        Step::make('Make the acceptance test pass')
            ->run('php artisan guide:verify membership-info')
            ->check(Check::testPassed('MembershipInfoTest')),
    ]);
