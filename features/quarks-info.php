<?php

use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;

return Feature::make('quarks-info')
    ->title('QUARKS page')
    ->section(Section::Pages)
    ->level(1)
    ->needs(['program-catalog'])
    ->summary('The QUARKS program for Grade 8 and 9 students, run with TRU. Uses the quarks program type.');
