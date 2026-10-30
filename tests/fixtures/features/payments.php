<?php

use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;

return Feature::make('payments')
    ->title('Online payments')
    ->section(Section::Signups)
    ->level(3)
    ->summary('A feature without steps is not planned yet.');
