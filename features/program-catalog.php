<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('program-catalog')
    ->title('Camps and clubs pages')
    ->section(Section::Pages)
    ->level(1)
    ->needs(['layout', 'programs'])
    ->summary('Public lists of camps and clubs, and a detail page for each program with its upcoming dates.')
    ->teaches(['Controllers', 'Route defaults', 'Eager loading', 'Query scopes'])
    ->steps([
        Step::make('Camps and clubs lists')
            ->check(Check::route('camps.index', 'clubs.index')),
        Step::make('Program detail page')
            ->check(Check::route('programs.show')),
    ]);
