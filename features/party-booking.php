<?php

use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;

return Feature::make('party-booking')
    ->title('Party booking')
    ->section(Section::Commerce)
    ->level(3)
    ->needs(['parties-info', 'child-profiles', 'payments'])
    ->summary('Pick an open party slot and pay the $25 deposit online.');
