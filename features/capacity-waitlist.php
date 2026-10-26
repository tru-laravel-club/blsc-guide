<?php

use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;

return Feature::make('capacity-waitlist')
    ->title('Capacity and waitlists')
    ->section(Section::Commerce)
    ->level(3)
    ->needs(['club-camp-registration'])
    ->summary('Stop registrations when a session is full, and put extra children on a waitlist.');
