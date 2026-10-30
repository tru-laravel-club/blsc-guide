<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('big-night-out-page')
    ->title('BIG Night Out page')
    ->section(Section::Pages)
    ->level(1)
    ->needs(['program-catalog'])
    ->summary('Upcoming adults-only evening events, such as the murder mystery. Each event is a Program with the big_night_out type.')
    ->teaches(['Route defaults', 'Reusing a controller', 'Blade @includeIf'])
    ->test('BigNightOutPageTest.php')
    ->intro(<<<'TEXT'
        BIG Night Out is a series of evening events for adults, such as a murder mystery. In this feature, you add the page that lists the next events. When you finish, a visitor opens `/big-night-out`. The visitor sees each event and a short note that the events are for adults only, ages 19 and up.

        You write almost no new PHP. The Camps and Clubs pages already use `ProgramController@index`. Each BIG Night Out event is a `Program` with the `big_night_out` type. So you add a route that gives the controller this type. Then you let each program type add its own introduction to the shared view. Camps and clubs do not change.

        Keep this path in mind while you work. The browser asks for `/big-night-out`. The route gives the type `big_night_out` to `ProgramController@index`. The controller gets the published programs of that type. The view `programs/index` shows them. It also includes `programs/intros/big_night_out` under the heading.
        TEXT)
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/10', 'Laravel From Scratch, episode 10: Controllers')
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/6', 'Laravel From Scratch, episode 6: Blade Directives')
    ->docs('https://laravel.com/docs/blade#including-subviews', 'Laravel docs: Including subviews')
    ->reflect([
        'Camps, clubs and BIG Night Out use one controller and one view. Why is this better than three controllers and three views?',
        'The Camps page does not show "19 and up". Which line makes sure of this?',
        'Which step was the hardest? What would help the next contributor? Open an issue or a pull request in blsc-guide to improve that step.',
    ])
    ->steps([
        Step::make('Claim the feature and copy in its test')
            ->run('php artisan guide:start big-night-out-page')
            ->explain(<<<'TEXT'
                You start with the test, not with the code. The test tells you what "done" means, so you know where you are going.

                Run the command below. It copies `tests/Feature/BigNightOutPageTest.php` into the app. Open the file. Each `test()` is one rule that the page must follow. Read all four rules before you continue.

                The test makes its programs with `Program::factory()`. One is a draft, and one is a club. The page must show neither of them.
                TEXT)
            ->think('The third rule opens the Camps page, not the BIG Night Out page. Why is this rule in the test?')
            ->tryIt('Run `php artisan test tests/Feature/BigNightOutPageTest.php`. Three rules fail, because the page does not exist yet. The Camps rule already passes. It must still pass when you finish.')
            ->commit('Add the BIG Night Out acceptance test')
            ->docs('https://laravel.com/docs/http-tests#assert-dont-see', 'Laravel docs: assertDontSee')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/22', 'Laravel From Scratch, episode 22: How to Get Started Testing Your Code')
            ->check(Check::fileExists('tests/Feature/BigNightOutPageTest.php')),

        Step::make('Add the route')
            ->code("Route::get('/big-night-out', [ProgramController::class, 'index'])\n    ->defaults('type', 'big_night_out')\n    ->name('big-night-out.index');")
            ->explain(<<<'TEXT'
                Open `app/Http/Controllers/ProgramController.php`. The `index()` method gets a `$type`, turns it into a `ProgramType` with `ProgramType::from()`, and lists the published programs of that type. It works for every type.

                The camps and clubs routes already use `ProgramController` this way. You write no new controller. You add a route that gives `index()` a different type.

                The URL `/big-night-out` has no `{type}` part. `->defaults('type', 'big_night_out')` gives the parameter a value anyway, and Laravel passes it to `$type`. The value must be the enum value, `big_night_out`, with underscores. The URL uses dashes.

                Put the route under the Discover heading in `routes/web.php`. Name it `big-night-out.index`, because the test uses this name.
                TEXT)
            ->think('Open `app/Enums/ProgramType.php`. Which value does `ProgramType::from()` need for BIG Night Out?')
            ->hint('Look at the `camps.index` route in `routes/web.php`. Copy it, then change the URL, the type and the name.')
            ->hint('The URL uses dashes: `/big-night-out`. The type uses underscores: `big_night_out`.')
            ->tryIt('Run `php artisan route:list --name=big-night-out`. You see your route. Then open http://localhost:8000/big-night-out. You see "BIG Night Out: Murder Mystery" from the seed data. The page does not say "ages 19 and up" yet. You add that next.')
            ->commit('Add the big-night-out.index route')
            ->docs('https://laravel.com/docs/routing#route-parameters', 'Laravel docs: Route parameters')
            ->docs('https://laravel.com/docs/routing#named-routes', 'Laravel docs: Named routes')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/3', 'Laravel From Scratch, episode 3: Routing 101')
            ->check(Check::route('big-night-out.index')),

        Step::make('Let each program type add its own introduction')
            ->code("@includeIf('programs.intros.'.\$type->value)")
            ->explain(<<<'TEXT'
                The BIG Night Out page needs a note for adults. But camps and clubs use the same view, and they must not show this note.

                You could add an `@if` for BIG Night Out to the view. Then each new type adds another `@if`, and the shared view grows. Instead, let the type choose a small view by its name. The view for BIG Night Out is `programs.intros.big_night_out`. A dot in a view name is a folder.

                Put an `@includeIf` line under the `<h1>` in `resources/views/programs/index.blade.php`. `@include` gives an error when the view does not exist. `@includeIf` does nothing when the view does not exist, so camps and clubs do not change.
                TEXT)
            ->think('BLSC adds five more program types next year. What must change in the shared view?')
            ->hint('`$type->value` is the text value of the enum, such as `camp` or `big_night_out`.')
            ->hint('`programs.intros.camp` is the file `resources/views/programs/intros/camp.blade.php`.')
            ->tryIt('Open http://localhost:8000/camps. The page looks the same as before. That is correct, because `programs/intros/camp.blade.php` does not exist. In `php artisan tinker`, run `view()->exists(\'programs.intros.camp\')`. You see `false`.')
            ->commit('Let each program type add an introduction')
            ->docs('https://laravel.com/docs/blade#including-subviews', 'Laravel docs: Including subviews')
            ->docs('https://laravel.com/docs/views#nested-view-directories', 'Laravel docs: Nested view directories')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/6', 'Laravel From Scratch, episode 6: Blade Directives')
            ->check(Check::fileContains('resources/views/programs/index.blade.php', '@includeIf')),

        Step::make('Write the BIG Night Out introduction')
            ->code('<p>Adults only, ages 19 and up.</p>')
            ->explain(<<<'TEXT'
                Now you write the note. Create `resources/views/programs/intros/big_night_out.blade.php`. Say that the events are for adults only, ages 19 and up.

                This file is only a part of a page. The main view already has the layout, the heading and the list. So write only the text, such as one paragraph.
                TEXT)
            ->think('The file is a part of a page. Does it need `<x-layouts::site>`? What happens if you add it?')
            ->hint('The file name must match `$type->value` exactly: `big_night_out`, with underscores.')
            ->hint('One `<p>` is enough.')
            ->tryIt('Open http://localhost:8000/big-night-out. You see "Adults only, ages 19 and up." under the heading. Then open http://localhost:8000/camps. You do not see the note there.')
            ->commit('Add the BIG Night Out introduction')
            ->docs('https://laravel.com/docs/views#creating-and-rendering-views', 'Laravel docs: Creating and rendering views')
            ->check(Check::view('programs.intros.big_night_out')),

        Step::make('Add the page to the Discover menu')
            ->code("['label' => 'BIG Night Out', 'route' => 'big-night-out.index', 'description' => 'Evenings for adults.'],")
            ->explain(<<<'TEXT'
                The page works, but visitors cannot find it yet. The menus come from the `$menus` array at the top of `resources/views/layouts/site.blade.php`. Add one entry to the Discover list. The layout makes the link from the data, so you write no HTML.
                TEXT)
            ->think('The test opens the home page, not the BIG Night Out page. Why does the home page show your link?')
            ->hint('Copy the Camps entry. Change the label, the route name and the description.')
            ->tryIt('Open the home page. Open the Discover menu, and click "BIG Night Out".')
            ->commit('Add BIG Night Out to the Discover menu')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/4', 'Laravel From Scratch, episode 4: Layout Files')
            ->check(Check::fileContains('resources/views/layouts/site.blade.php', "'big-night-out.index'")),

        Step::make('Make the acceptance test pass')
            ->run('php artisan guide:verify big-night-out-page')
            ->explain(<<<'TEXT'
                You are close. Run the acceptance test with the command below. The guide saves the result and ticks off this step.

                If the test fails, read the first failure only. It names the rule and the line. Fix that one problem, then run the test again. Later failures often come from the first one.

                When the test passes, run `composer ci:check`. It runs the code style check, the type check and every other test in the app. Then go to "Finish" below.
                TEXT)
            ->docs('https://laravel.com/docs/testing', 'Laravel docs: Testing')
            ->check(Check::testPassed('BigNightOutPageTest')),
    ]);
