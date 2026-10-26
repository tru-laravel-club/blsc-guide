<?php

use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;

return Feature::make('party-invitations')
    ->title('Party invitation downloads')
    ->section(Section::Pages)
    ->level(1)
    ->needs(['parties-info'])
    ->summary('Free printable birthday invitations that parents download from the Parties page.');
