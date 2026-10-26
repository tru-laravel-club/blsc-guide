<?php

use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;

return Feature::make('memberships')
    ->title('Membership purchase')
    ->section(Section::Commerce)
    ->level(3)
    ->needs(['payments'])
    ->summary('Buy and renew annual memberships online. Each membership has an expiry date.');
