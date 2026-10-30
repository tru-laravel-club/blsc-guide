<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('quarks-info')
    ->title('QUARKS page')
    ->section(Section::Pages)
    ->level(1)
    ->needs(['program-catalog', 'big-night-out-page'])
    ->summary('The QUARKS program for Grade 8 and 9 students, run with TRU. Uses the quarks program type.')
    ->teaches(['Route defaults', 'Reusing a controller', 'Blade @includeIf'])
    ->test('QuarksInfoTest.php')
    ->intro(<<<'TEXT'
        QUARKS is a science program for students in Grade 8 and 9. BLSC runs it with Thompson Rivers University (TRU). In this feature, you add the QUARKS page. When you finish, a visitor opens `/quarks`, reads who the program is for, and sees the QUARKS programs.

        This feature uses the pattern from the BIG Night Out page. `ProgramController@index` lists the programs of one type. A route gives it the `quarks` type. The shared view `programs/index` already has `@includeIf`, so a type can add its own introduction with one new file. You add a route, an introduction and a menu link. You write no PHP class.

        Keep this path in mind while you work. The browser asks for `/quarks`. The route gives the type `quarks` to `ProgramController@index`. The controller gets the published QUARKS programs. The view shows them and includes `programs/intros/quarks` under the heading.
        TEXT)
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/3', 'Laravel From Scratch, episode 3: Routing 101')
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/6', 'Laravel From Scratch, episode 6: Blade Directives')
    ->docs('https://laravel.com/docs/blade#including-subviews', 'Laravel docs: Including subviews')
    ->reflect([
        'You wrote no PHP class for this page. Which parts of the app did the work?',
        'The QUARKS introduction is in its own file. What would change if every introduction were in `programs/index.blade.php`?',
        'Which step was the hardest? What would help the next contributor? Open an issue or a pull request in blsc-guide to improve that step.',
    ])
    ->steps([
        Step::make('Claim the feature and copy in its test')
            ->run('php artisan guide:start quarks-info')
            ->explain(<<<'TEXT'
                You start with the test, not with the code. The test tells you what "done" means, so you know where you are going.

                Run the command below. It copies `tests/Feature/QuarksInfoTest.php` into the app. Open the file. Each `test()` is one rule that the page must follow. Read all three rules before you continue.
                TEXT)
            ->think('Compare this test with `tests/Feature/BigNightOutPageTest.php`. What is the same? What is different?')
            ->tryIt('Run `php artisan test tests/Feature/QuarksInfoTest.php`. All three rules fail. That is correct, because the page does not exist yet.')
            ->commit('Add the QUARKS acceptance test')
            ->docs('https://laravel.com/docs/http-tests#assert-see', 'Laravel docs: assertSee')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/22', 'Laravel From Scratch, episode 22: How to Get Started Testing Your Code')
            ->check(Check::fileExists('tests/Feature/QuarksInfoTest.php')),

        Step::make('Add the route')
            ->code("Route::get('/quarks', [ProgramController::class, 'index'])\n    ->defaults('type', 'quarks')\n    ->name('quarks.index');")
            ->explain(<<<'TEXT'
                This is the same pattern as the BIG Night Out page. The route sends the request to `ProgramController@index`, and `->defaults()` gives the controller the type. You write no new controller.

                Put it under the Discover heading in `routes/web.php`. Name it `quarks.index`, because the test uses this name.
                TEXT)
            ->think('Open `app/Enums/ProgramType.php`. Which value does the route need for QUARKS?')
            ->hint('Look at the `big-night-out.index` route in `routes/web.php`.')
            ->hint('The enum value is `quarks`, in lowercase.')
            ->tryIt('Run `php artisan route:list --name=quarks.index`. You see your route. Then open http://localhost:8000/quarks. You see the QUARKS program from the seed data. The page does not say who QUARKS is for yet. You add that next.')
            ->commit('Add the quarks.index route')
            ->docs('https://laravel.com/docs/routing#route-parameters', 'Laravel docs: Route parameters')
            ->docs('https://laravel.com/docs/routing#named-routes', 'Laravel docs: Named routes')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/3', 'Laravel From Scratch, episode 3: Routing 101')
            ->check(Check::route('quarks.index')),

        Step::make('Write the QUARKS introduction')
            ->code('<p>QUARKS is for students in Grade 8 and 9. BLSC runs it with Thompson Rivers University (TRU).</p>')
            ->explain(<<<'TEXT'
                Now the page needs to say who QUARKS is for. You do not change the shared view. The BIG Night Out feature added `@includeIf('programs.intros.'.$type->value)` to it. So the view looks for `programs.intros.quarks`, and shows it when the file exists.

                Create `resources/views/programs/intros/quarks.blade.php`. Say that QUARKS is for students in Grade 8 and 9 and that BLSC runs it with Thompson Rivers University (TRU). Get the details from a maintainer.

                Write the full name of the university first, then the short name in brackets. Not every visitor knows what "TRU" means.
                TEXT)
            ->think('You do not change `programs/index.blade.php` in this feature. How does the page find your new file?')
            ->hint('The file name is the enum value: `quarks`.')
            ->hint('Look at `resources/views/programs/intros/big_night_out.blade.php`. Write only the text. The main view has the layout.')
            ->tryIt('Open http://localhost:8000/quarks. You see your introduction under the heading. Then open http://localhost:8000/camps. You do not see it there.')
            ->commit('Add the QUARKS introduction')
            ->docs('https://laravel.com/docs/blade#including-subviews', 'Laravel docs: Including subviews')
            ->check(Check::view('programs.intros.quarks')),

        Step::make('Add the page to the Discover menu')
            ->code("['label' => 'QUARKS', 'route' => 'quarks.index', 'description' => 'Grade 8 and 9 with TRU.'],")
            ->explain(<<<'TEXT'
                The page works, but visitors cannot find it yet. Add one entry to the Discover list in the `$menus` array at the top of `resources/views/layouts/site.blade.php`. The layout makes the link from the data, so you write no HTML.
                TEXT)
            ->think('What short description helps a parent decide if the page is for their child?')
            ->hint('Copy the BIG Night Out entry. Change the label, the route name and the description.')
            ->tryIt('Open the home page. Open the Discover menu, and click "QUARKS".')
            ->commit('Add QUARKS to the Discover menu')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/4', 'Laravel From Scratch, episode 4: Layout Files')
            ->check(Check::fileContains('resources/views/layouts/site.blade.php', "'quarks.index'")),

        Step::make('Make the acceptance test pass')
            ->run('php artisan guide:verify quarks-info')
            ->explain(<<<'TEXT'
                You are close. Run the acceptance test with the command below. The guide saves the result and ticks off this step.

                If the test fails, read the first failure only. It names the rule and the line. Fix that one problem, then run the test again. Later failures often come from the first one.

                When the test passes, run `composer ci:check`. It runs the code style check, the type check and every other test in the app. Then go to "Finish" below.
                TEXT)
            ->docs('https://laravel.com/docs/testing', 'Laravel docs: Testing')
            ->check(Check::testPassed('QuarksInfoTest')),
    ]);
