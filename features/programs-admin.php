<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('programs-admin')
    ->title('Staff: manage programs')
    ->section(Section::Foundation)
    ->level(2)
    ->needs(['programs', 'staff-roles'])
    ->summary('Staff create, edit and delete programs and their sessions. Copy this pattern when you build other admin pages.')
    ->teaches(['Resource controllers', 'Form requests', 'Nested routes', 'Route model binding'])
    ->steps([
        Step::make('Programs admin pages')
            ->explain('See app/Http/Controllers/Admin/ProgramController.php and resources/views/admin/programs.')
            ->check(Check::route('admin.programs.index', 'admin.programs.create', 'admin.programs.edit')),
        Step::make('Sessions admin pages')
            ->check(Check::route('admin.programs.sessions.create', 'admin.sessions.edit')),
    ]);
