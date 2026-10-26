<?php

use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;

return Feature::make('club-camp-registration')
    ->title('Club and camp registration')
    ->section(Section::Commerce)
    ->level(3)
    ->needs(['programs', 'child-profiles', 'payments'])
    ->summary('A parent picks a session, picks which children attend, and pays. Each registration is linked to one child.');
