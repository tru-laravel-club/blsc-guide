<?php

use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;

return Feature::make('big-night-out-tickets')
    ->title('BIG Night Out tickets')
    ->section(Section::Commerce)
    ->level(2)
    ->needs(['big-night-out-page', 'payments'])
    ->summary('Buy tickets for adults-only evening events.');
