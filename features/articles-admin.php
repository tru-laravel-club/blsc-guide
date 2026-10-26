<?php

use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;

return Feature::make('articles-admin')
    ->title('Staff: manage articles')
    ->section(Section::StaffTools)
    ->level(2)
    ->needs(['science-articles', 'staff-roles'])
    ->summary('Staff write, edit, publish and delete science articles.');
