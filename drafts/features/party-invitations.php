<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('party-invitations')
    ->title('Party invitation downloads')
    ->section(Section::Content)
    ->level(1)
    ->needs(['info-pages'])
    ->summary('Free printable birthday invitations that parents download from the Parties page.')
    ->teaches(['Filesystem disks', 'File downloads', 'Route constraints', 'Storage::fake in tests'])
    ->test('PartyInvitationsTest.php')
    ->intro(<<<'TEXT'
        Parents who book a birthday party at BLSC need invitations for their guests. In this feature, you add free printable invitations to the Parties page. When you finish, a parent opens `/parties`, clicks a design, such as "dinosaurs", and the browser downloads a PDF file.

        The invitations are not in the database. They are PDF files in a folder. Laravel reads and writes files through disks, so you first add an `invitations` disk for the folder. Then a controller sends one file as a download. A route with a constraint accepts only safe design names. Last, the Parties page reads the folder and makes one link for each file.

        Keep this path in mind while you work. The parent clicks a link to `/parties/invitations/dinosaurs`. The route checks the name `dinosaurs` against its constraint. The controller asks the disk for `dinosaurs.pdf`. If the file exists, the controller sends it as a download. If not, the parent sees a 404 page.
        TEXT)
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/36', 'Laravel From Scratch, episode 36: Upload Featured Images To Storage')
    ->docs('https://laravel.com/docs/filesystem#introduction', 'Laravel docs: File storage')
    ->reflect([
        'The test uses `Storage::fake(\'invitations\')`. Why does the test not use the real PDF files?',
        'The route accepts only lowercase letters, digits and dashes. What could a visitor try to download if the route accepted every character?',
        'Which step was the hardest? What would help the next contributor? Open an issue or a pull request in blsc-guide to improve that step.',
    ])
    ->steps([
        Step::make('Claim the feature and copy in its test')
            ->run('php artisan guide:start party-invitations')
            ->explain(<<<'TEXT'
                You start with the test, not with the code. The test tells you what "done" means, so you know where you are going.

                Run the command below. It copies `tests/Feature/PartyInvitationsTest.php` into the app. Open the file. Each `test()` is one rule that the feature must follow. Read all four rules before you continue.

                Look at `beforeEach()` at the top. It runs before each rule. It replaces the `invitations` disk with a fake disk and puts two test PDF files on it. So the test does not need the real PDF files.
                TEXT)
            ->think('The last rule asks for `/parties/invitations/..%2F..%2F.env`. What file is the visitor trying to get? Why is this rule in the test?')
            ->tryIt('Run `php artisan test tests/Feature/PartyInvitationsTest.php`. Three rules fail, because the route does not exist yet. The last rule already passes, because no route matches its URL. It must still pass when your route exists.')
            ->commit('Add the party invitations acceptance test')
            ->docs('https://laravel.com/docs/http-tests#assert-download', 'Laravel docs: assertDownload')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/22', 'Laravel From Scratch, episode 22: How to Get Started Testing Your Code')
            ->check(Check::fileExists('tests/Feature/PartyInvitationsTest.php')),

        Step::make('Add an invitations disk')
            ->code("'invitations' => [\n    'driver' => 'local',\n    'root' => resource_path('invitations'),\n    'throw' => false,\n],")
            ->explain(<<<'TEXT'
                First, you give the PDF files a home. In Laravel, a disk is a named folder that Laravel can read and write. Your code asks for a disk by its name, such as `Storage::disk('invitations')`. It does not need to know where the folder is.

                Add an `invitations` disk to the disks in `config/filesystems.php`. Use the `local` driver, because the files are on the same server as the app.

                The PDF files go in `resources/invitations`, so git keeps them. Git ignores the files in `storage/app`, so files there do not get to the other contributors or to the live site.

                Ask a maintainer for the PDF files. Use lowercase names with dashes, such as `dinosaurs.pdf`. The file name becomes part of the URL, so it must match the route constraint that you add later.
                TEXT)
            ->think('Why do the PDF files go in `resources` and not in `storage`?')
            ->hint('Copy the `local` disk in `config/filesystems.php`. Change its name and its root.')
            ->hint('`resource_path(\'invitations\')` gives the full path of the `resources/invitations` folder.')
            ->tryIt('In `php artisan tinker`, run `Illuminate\Support\Facades\Storage::disk(\'invitations\')->files()`. You see the names of the PDF files, such as `"dinosaurs.pdf"`. If you see `[]`, the folder is empty. Add the PDF files from the maintainer. Type `exit` to leave tinker.')
            ->commit('Add the invitations disk')
            ->docs('https://laravel.com/docs/filesystem#configuration', 'Laravel docs: Filesystem configuration')
            ->docs('https://laravel.com/docs/filesystem#the-local-driver', 'Laravel docs: The local driver')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/36', 'Laravel From Scratch, episode 36: Upload Featured Images To Storage')
            ->check(Check::fileContains('config/filesystems.php', "'invitations'")),

        Step::make('Create the download controller')
            ->run('php artisan make:controller PartyInvitationController --invokable')
            ->code(<<<'PHP'
                public function __invoke(string $design): StreamedResponse
                {
                    $disk = Storage::disk('invitations');

                    abort_unless($disk->exists("{$design}.pdf"), 404);

                    return $disk->download("{$design}.pdf", "blsc-invitation-{$design}.pdf");
                }
                PHP)
            ->explain(<<<'TEXT'
                The files are on the disk. Now you make the code that sends one file to the parent. This controller does only one thing, so make it invokable.

                The route gives the controller the file name without ".pdf", such as `dinosaurs`. Name the argument `$design`, like the route parameter in the next step. Add `.pdf` to find the file.

                Return a 404 if the file does not exist. Without this check, a design that does not exist gives a server error, and the test expects "Not found". Otherwise, return `Storage::disk('invitations')->download()` with a clear name, such as `blsc-invitation-dinosaurs.pdf`. Then the parent finds the file in the Downloads folder and knows that it is from BLSC.
                TEXT)
            ->think('A parent asks for `unicorns`, but there is no `unicorns.pdf`. What must the parent see?')
            ->hint('The method gets the design name from the route, such as `dinosaurs`. Add `.pdf` to get the file name.')
            ->hint('`Storage::disk(\'invitations\')->exists()` tells you if the file is there. `abort_unless()` stops with a 404 when it is not.')
            ->hint('The second argument of `download()` is the file name that the parent sees.')
            ->tryIt('In `php artisan tinker`, run `app(App\Http\Controllers\PartyInvitationController::class)(\'unicorns\')`. You see a `NotFoundHttpException`. That is correct, because there is no `unicorns.pdf`. Laravel turns this exception into a 404 page.')
            ->commit('Add PartyInvitationController')
            ->docs('https://laravel.com/docs/filesystem#downloading-files', 'Laravel docs: Downloading files')
            ->docs('https://laravel.com/docs/controllers#single-action-controllers', 'Laravel docs: Single action controllers')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/10', 'Laravel From Scratch, episode 10: Controllers')
            ->check(Check::classExists('App\Http\Controllers\PartyInvitationController')),

        Step::make('Add the route with a constraint')
            ->code("Route::get('/parties/invitations/{design}', PartyInvitationController::class)\n    ->where('design', '[a-z0-9-]+')\n    ->name('parties.invitations');")
            ->explain(<<<'TEXT'
                Now you connect a URL to the controller. Add the route under the Discover heading in `routes/web.php`, near the `parties` route. Name it `parties.invitations`, because the test uses this name.

                This step is about safety. The controller makes a file path from text that the visitor types in the URL. In a path, `..` means "the folder above". So a visitor can try `../../.env`. That path climbs out of `resources/invitations` to the `.env` file, which holds the passwords and keys of the app. This attack is called path traversal.

                A constraint stops it. `->where()` gives a route parameter a regular expression. The constraint allows only lowercase letters, digits and dashes. Then a visitor cannot ask for a path such as `../../.env`, because a dot and a slash are not in the list. Laravel returns 404, and your controller never runs.

                Laravel parameters already stop at a `/`. But they accept dots and backslashes, and some systems read a backslash as a folder separator. So do not trust other code to catch bad names. Name the characters that are safe, and refuse all the others. This is an allow list. It is safer than a list of bad characters, because you cannot forget a bad character.

                The test sends `%2F`. That is a `/` in URL code. Laravel decodes it before it compares the URL with the route.
                TEXT)
            ->think('Which characters does a visitor need to climb out of a folder? Does your pattern allow them?')
            ->hint('`->where(\'design\', \'...\')` gives the `design` parameter a regular expression.')
            ->hint('`[a-z0-9-]+` means "one or more lowercase letters, digits or dashes".')
            ->tryIt(<<<'TEXT'
                Run `php artisan route:list --name=parties.invitations`. You see your route.

                Then open http://localhost:8000/parties/invitations/dinosaurs, or use the name of a PDF that you have. The browser downloads `blsc-invitation-dinosaurs.pdf`.

                Now open http://localhost:8000/parties/invitations/unicorns and http://localhost:8000/parties/invitations/..%2F..%2F.env. You see a 404 page both times.
                TEXT)
            ->commit('Add the parties.invitations route')
            ->docs('https://laravel.com/docs/routing#parameters-regular-expression-constraints', 'Laravel docs: Regular expression constraints')
            ->docs('https://laravel.com/docs/routing#named-routes', 'Laravel docs: Named routes')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/3', 'Laravel From Scratch, episode 3: Routing 101')
            ->check(Check::route('parties.invitations')),

        Step::make('List the invitations on the Parties page')
            ->code("'invitations' => collect(Storage::disk('invitations')->files())\n    ->filter(fn (string \$file) => str_ends_with(\$file, '.pdf'))\n    ->map(fn (string \$file) => basename(\$file, '.pdf'))\n    ->values(),")
            ->explain(<<<'TEXT'
                The downloads work, but parents cannot find them yet. Now you show a link for each design on the Parties page.

                In `PartyController`, read the PDF names from the invitations disk and pass them to the view. The disk gives names such as `dinosaurs.pdf`. Remove `.pdf`, so each name fits the route. Keep only the files that end in `.pdf`, because a folder can hold other files.

                In `resources/views/parties.blade.php`, show a download link for each one. Do not write the names in the view, so staff can add a design by adding a file. Then nobody needs to change the code for a new design.
                TEXT)
            ->think('Staff want to add a new design next year. With your code, what must they change?')
            ->hint('`Storage::disk(\'invitations\')->files()` gives the file names, such as `dinosaurs.pdf`.')
            ->hint('`basename($file, \'.pdf\')` removes `.pdf` from the name.')
            ->hint('In the view, use `@foreach` and link each design with `route(\'parties.invitations\', $design)`.')
            ->tryIt('Open http://localhost:8000/parties. You see one link for each PDF file. Click one. The browser downloads the invitation.')
            ->commit('List the invitations on the Parties page')
            ->docs('https://laravel.com/docs/filesystem#get-all-files-within-a-directory', 'Laravel docs: Get all files in a directory')
            ->docs('https://laravel.com/docs/blade#loops', 'Laravel docs: Loops')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/5', 'Laravel From Scratch, episode 5: Pass Data to Views')
            ->check(Check::fileContains('resources/views/parties.blade.php', "route('parties.invitations'")),

        Step::make('Make the acceptance test pass')
            ->run('php artisan guide:verify party-invitations')
            ->explain(<<<'TEXT'
                You are close. Run the acceptance test with the command below. The guide saves the result and ticks off this step.

                The test uses `Storage::fake('invitations')`. This gives the test an empty disk in a temporary folder. The test puts its own PDF files there, so it gives the same result on every computer.

                If the test fails, read the first failure only. It names the rule and the line. Fix that one problem, then run the test again. Later failures often come from the first one.

                When the test passes, run `composer ci:check`. It runs the code style check, the type check and every other test in the app. Then go to "Finish" below.
                TEXT)
            ->docs('https://laravel.com/docs/filesystem#testing', 'Laravel docs: Testing file storage')
            ->check(Check::testPassed('PartyInvitationsTest')),
    ]);
