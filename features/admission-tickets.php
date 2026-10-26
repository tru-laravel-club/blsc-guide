<?php

use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;

return Feature::make('admission-tickets')
    ->title('Admission tickets')
    ->section(Section::Commerce)
    ->level(2)
    ->needs(['payments'])
    ->summary('Buy general admission tickets online, with the name of each ticket holder.');
