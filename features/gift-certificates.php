<?php

use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;

return Feature::make('gift-certificates')
    ->title('Gift certificates')
    ->section(Section::Commerce)
    ->level(2)
    ->needs(['payments'])
    ->summary('Buy admission and membership gift certificates with a code to redeem.');
