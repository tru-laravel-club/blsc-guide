<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('party-invitations')
    ->title('Party invitation downloads')
    ->section(Section::Pages)
    ->level(1)
    ->needs(['parties-info'])
    ->summary('Free printable birthday invitations that parents download from the Parties page.')
    ->teaches(['Filesystem disks', 'File downloads', 'Route constraints', 'Storage::fake in tests'])
    ->test('PartyInvitationsTest.php')
    ->steps([
        Step::make('Claim the feature and copy in its test')
            ->run('php artisan guide:start party-invitations')
            ->check(Check::fileExists('tests/Feature/PartyInvitationsTest.php')),

        Step::make('Add an invitations disk')
            ->code("'invitations' => [\n    'driver' => 'local',\n    'root' => resource_path('invitations'),\n    'throw' => false,\n],")
            ->explain('Add it to the disks in config/filesystems.php. The PDF files go in resources/invitations, so git keeps them. Ask a lead for the PDF files. Use lowercase names with dashes, such as dinosaurs.pdf.')
            ->docs('https://laravel.com/docs/filesystem#configuration')
            ->check(Check::fileContains('config/filesystems.php', "'invitations'")),

        Step::make('Create the download controller')
            ->run('php artisan make:controller PartyInvitationController --invokable')
            ->explain('Take the file name without ".pdf". Return a 404 if the file does not exist. Otherwise return Storage::disk(\'invitations\')->download() with a clear name, such as blsc-invitation-dinosaurs.pdf.')
            ->docs('https://laravel.com/docs/filesystem#downloading-files')
            ->check(Check::classExists('App\Http\Controllers\PartyInvitationController')),

        Step::make('Add the route with a constraint')
            ->code("Route::get('/parties/invitations/{design}', PartyInvitationController::class)\n    ->where('design', '[a-z0-9-]+')\n    ->name('parties.invitations');")
            ->explain('The constraint allows only lowercase letters, digits and dashes. Then a visitor cannot ask for a path such as ../../.env.')
            ->docs('https://laravel.com/docs/routing#parameters-regular-expression-constraints')
            ->check(Check::route('parties.invitations')),

        Step::make('List the invitations on the Parties page')
            ->explain('In PartyController, read the PDF names from the invitations disk and pass them to the view. Show a download link for each one. Do not write the names in the view, so staff can add a design by adding a file.')
            ->check(Check::fileContains('resources/views/parties.blade.php', "route('parties.invitations'")),

        Step::make('Make the acceptance test pass')
            ->run('php artisan guide:verify party-invitations')
            ->check(Check::testPassed('PartyInvitationsTest')),
    ]);
