<?php

use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;

return Feature::make('big-night-out-page')
    ->title('BIG Night Out page')
    ->section(Section::Pages)
    ->level(1)
    ->needs(['program-catalog'])
    ->summary('Upcoming adults-only evening events, such as the murder mystery.');
