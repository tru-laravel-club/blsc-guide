<?php

use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;

return Feature::make('quarks-application')
    ->title('QUARKS application')
    ->section(Section::Commerce)
    ->level(2)
    ->needs(['quarks-info', 'accounts'])
    ->summary('Students apply online. Staff review the applications.');
