<?php

use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;

return Feature::make('member-pricing')
    ->title('Member pricing')
    ->section(Section::Commerce)
    ->level(3)
    ->needs(['memberships', 'club-camp-registration'])
    ->summary('Members pay the member price automatically. This replaces the shared MEMBER discount code.');
