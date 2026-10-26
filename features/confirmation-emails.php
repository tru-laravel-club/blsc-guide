<?php

use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;

return Feature::make('confirmation-emails')
    ->title('Confirmation emails')
    ->section(Section::Commerce)
    ->level(2)
    ->needs(['club-camp-registration'])
    ->summary('Email parents a receipt and the details after they register.');
