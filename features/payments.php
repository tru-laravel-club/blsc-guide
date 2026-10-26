<?php

use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;

return Feature::make('payments')
    ->title('Online payments')
    ->section(Section::Foundation)
    ->level(3)
    ->needs(['accounts'])
    ->summary('Take card payments through the provider\'s hosted checkout, so card numbers never touch our server. Leads: agree the provider (Stripe or Square) with BLSC before you write the steps.');
