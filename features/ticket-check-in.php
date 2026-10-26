<?php

use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;

return Feature::make('ticket-check-in')
    ->title('Ticket check-in')
    ->section(Section::StaffTools)
    ->level(2)
    ->needs(['admission-tickets', 'staff-roles'])
    ->summary('Staff mark online tickets as used when visitors arrive.');
