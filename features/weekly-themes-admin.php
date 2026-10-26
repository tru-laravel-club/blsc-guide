<?php

use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;

return Feature::make('weekly-themes-admin')
    ->title('Staff: manage weekly themes')
    ->section(Section::StaffTools)
    ->level(2)
    ->needs(['big-little-scientists', 'staff-roles'])
    ->summary('Staff set the BIG Little Scientists theme for each Thursday.');
