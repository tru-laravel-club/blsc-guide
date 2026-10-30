<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('articles')
    ->title('Science articles')
    ->section(Section::Content)
    ->level(1)
    ->needs(['layout'])
    ->summary('Short articles that staff write.')
    ->steps([
        Step::make('Create the Article model')
            ->run('php artisan make:model Article -mf')
            ->code("\$table->string('title');\n\$table->string('slug')->unique();")
            ->check(Check::classExists('App\Models\Article')),
    ]);
