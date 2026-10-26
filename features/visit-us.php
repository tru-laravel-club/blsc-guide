<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('visit-us')
    ->title('Visit Us page')
    ->section(Section::Pages)
    ->level(1)
    ->needs(['layout'])
    ->summary('Hours, location and admission rates. A static page made with Route::view().')
    ->teaches(['Route::view', 'Blade loops'])
    ->steps([
        Step::make('Add the Visit Us page')
            ->code("Route::view('/visit-us', 'visit-us')->name('visit-us');")
            ->check(Check::all(Check::route('visit-us'), Check::view('visit-us'))),
    ]);
