<?php

use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;

return Feature::make('parties-info')
    ->title('Parties page')
    ->section(Section::Pages)
    ->level(1)
    ->needs(['layout'])
    ->summary('What a birthday party includes, the themes, prices and times.');
