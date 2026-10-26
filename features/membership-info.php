<?php

use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;

return Feature::make('membership-info')
    ->title('Memberships page')
    ->section(Section::Pages)
    ->level(1)
    ->needs(['layout'])
    ->summary('Membership types, prices, benefits and the FAQ, such as reciprocal admission at other science centres.');
