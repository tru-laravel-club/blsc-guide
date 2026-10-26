<?php

use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;

return Feature::make('member-lookup')
    ->title('Member lookup at the front desk')
    ->section(Section::StaffTools)
    ->level(2)
    ->needs(['memberships', 'staff-roles'])
    ->summary('Staff search for a member and see if their membership is valid.');
