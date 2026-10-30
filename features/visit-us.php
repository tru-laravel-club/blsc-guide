<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('visit-us')
    ->title('Visit Us page')
    ->section(Section::Foundation)
    ->level(1)
    ->needs(['layout'])
    ->summary('Hours, location and admission rates. A static page made with Route::view().')
    ->teaches(['Route::view', 'Blade loops'])
    ->intro(<<<'TEXT'
        This feature is already in `main`. It is the simplest page in the app, so it is a good first file to read.

        The page shows fixed text: hours, the address and the admission rates. It needs no database, so it needs no controller and no model. One route shows one view.
        TEXT)
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/3', 'Laravel From Scratch, episode 3: Routing 101')
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/6', 'Laravel From Scratch, episode 6: Blade Directives')
    ->steps([
        Step::make('Read the Visit Us page')
            ->code("Route::view('/visit-us', 'visit-us')->name('visit-us');")
            ->explain(<<<'TEXT'
                Open `routes/web.php` and find the route above. Then open `resources/views/visit-us.blade.php`. The admission rates are a `@php` array at the top, and a `@foreach` shows them as a table. To change a price, you change one line in the array.
                TEXT)
            ->think('BLSC wants staff to change the hours from an admin page. What must change in this feature?')
            ->tryIt('Open http://localhost:8000/visit-us.')
            ->docs('https://laravel.com/docs/routing#view-routes', 'Laravel docs: View routes')
            ->check(Check::all(Check::route('visit-us'), Check::view('visit-us'))),
    ]);
