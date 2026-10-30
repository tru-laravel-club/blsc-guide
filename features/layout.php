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
    ->intro(<<<'TEXT'
        This feature is already in `main`. You do not build it. You read it, because almost every page that you build uses it.

        Every public page wraps its content in `<x-layouts::site>`. The layout adds the header, the Explore and Discover menus, and the footer. Your page fills only the middle. So all pages look the same, and a change to the header changes every page at once.

        The menus are not written as HTML. They come from a PHP array at the top of the layout. When you build a new page, you add one entry to that array, and the layout makes the link.
        TEXT)
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/4', 'Laravel From Scratch, episode 4: Layout Files')
    ->docs('https://laravel.com/docs/blade#components', 'Laravel docs: Components')
    ->docs('https://laravel.com/docs/blade#slots', 'Laravel docs: Slots')
    ->steps([
        Step::make('Read the public layout')
            ->explain(<<<'TEXT'
                Open `resources/views/layouts/site.blade.php`. Find the `$menus` array at the top. Each entry has a label, a route name and a short description.

                Then find the `@foreach` that turns the array into links. Look at `request()->routeIs()`. It marks the link of the page that the visitor is on.

                Last, find `{{ $slot }}`. Your page content goes there.
                TEXT)
            ->think('You build a new page. What is the smallest change that puts it in the Discover menu?')
            ->tryIt('Open http://localhost:8000. Open the Explore menu and the Discover menu. Compare the links with the `$menus` array.')
            ->check(Check::view('layouts.site')),

        Step::make('Read the home page route')
            ->explain(<<<'TEXT'
                Open `routes/web.php`. The first route is `Route::view('/', 'home')->name('home')`. It shows a view with no controller, because the home page needs no data from the database.
                TEXT)
            ->think('When would the home page need a controller?')
            ->tryIt('Run `php artisan route:list --name=home`. You see the route and the view that it shows.')
            ->docs('https://laravel.com/docs/routing#view-routes', 'Laravel docs: View routes')
            ->check(Check::route('home')),
    ]);
