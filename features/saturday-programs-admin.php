<?php

use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;

return Feature::make('saturday-programs-admin')
    ->title('Staff: manage Saturday events')
    ->section(Section::StaffTools)
    ->level(2)
    ->needs(['saturday-programs', 'staff-roles'])
    ->summary('Staff add and edit Saturday events.');
