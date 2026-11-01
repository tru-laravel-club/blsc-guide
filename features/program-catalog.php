<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('program-catalog')
    ->title('Camps and clubs pages')
    ->section(Section::Foundation)
    ->level(2)
    ->needs(['layout', 'programs'])
    ->summary('Public lists of camps and clubs, and a page for each program with its upcoming dates.')
    ->teaches(['Controllers', 'Route model binding', 'Route defaults', 'Eager loading', 'Query scopes', '404 pages'])
    ->test('ProgramCatalogTest.php')
    ->intro(<<<'TEXT'
        The programs and sessions are in the database, but visitors cannot see them yet. In this feature, you build three public pages: a list of camps at `/camps`, a list of clubs at `/clubs`, and a page for each program, such as `/programs/junior-robotics-coding-club`.

        These pages need data from the database, so `Route::view()` is not enough. A controller loads the data and gives it to a view. One `ProgramController` serves all three pages.

        The camps page and the clubs page are the same page with a different program type. So one controller method serves both. Each route gives the method its type. When BLSC wants a parties page, it is one more route, not a new method.

        Each program card on a list shows the next session. To get the sessions of every program, the controller loads them up front, in one extra query. This is called eager loading. Without it, the page makes one query for each program.

        A visitor must never see a draft. The list uses the `published()` scope from the Programs feature. The program page checks it too, because a visitor can type any address.
        TEXT)
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/5', 'Laravel From Scratch, episode 5: Pass Data to Views')
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/10', 'Laravel From Scratch, episode 10: Controllers')
    ->docs('https://laravel.com/docs/controllers', 'Laravel docs: Controllers')
    ->docs('https://laravel.com/docs/eloquent-relationships#eager-loading', 'Laravel docs: Eager loading')
    ->reflect([
        'BLSC wants a page for parties. What do you add? What stays the same?',
        'The list hides drafts, and the program page checks again. Why both?',
        'Which step was the hardest? What would help the next contributor? Open an issue or a pull request in blsc-guide to improve that step.',
    ])
    ->steps([
        Step::make('Claim the feature and copy in its test')
            ->run('php artisan guide:start program-catalog')
            ->explain(<<<'TEXT'
                You start with the test. Run the command below. It copies `tests/Feature/ProgramCatalogTest.php` into the app.

                Read the whole test before you write code. It names the routes: `camps.index`, `clubs.index` and `programs.show`. It uses the factory states from the Programs feature, such as `club()`, `unpublished()` and `past()`.

                Look at the rule "a one-day session stays listed until that day ends". The `upcoming()` scope in `ProgramSession` already handles it. Your controller only has to use the scope.
                TEXT)
            ->think('The test opens `route(\'programs.show\', $program)` for a draft and expects "Not found". Why "Not found" and not "Forbidden"?')
            ->tryIt('Run `php artisan test tests/Feature/ProgramCatalogTest.php`. All the rules fail, because the routes do not exist yet.')
            ->commit('Add the camps and clubs acceptance test')
            ->docs('https://laravel.com/docs/http-tests', 'Laravel docs: HTTP tests')
            ->check(Check::fileExists('tests/Feature/ProgramCatalogTest.php')),

        Step::make('Make the controller and the program page route')
            ->run('php artisan make:controller ProgramController')
            ->explain(<<<'TEXT'
                Start with the page for one program. Make the controller with the command below.

                Add a `show(Program $program)` method. Laravel sees the `Program` type, reads the value from the URL, and finds the program in the database. This is route model binding. When no program matches, Laravel shows a 404 page, and your method never runs.

                By default, Laravel looks for the id. A slug makes a better address: `/programs/junior-robotics-coding-club`, not `/programs/2`. Write `{program:slug}` in the route, and Laravel looks in the `slug` column.

                In `show()`:

                1. Stop with 404 when the program is a draft. `abort_unless($program->is_published, 404)` does that. A visitor who guesses the slug of a draft sees "Not found", as for a program that does not exist.
                2. Load the upcoming sessions: `$program->load('upcomingSessions')`.
                3. Return the view `programs.show` with the program.

                Then add the route in `routes/web.php`: `GET /programs/{program:slug}` to `[ProgramController::class, 'show']`, with the name `programs.show`.
                TEXT)
            ->think('A draft returns 404, not 403 ("Forbidden"). What does a 403 tell a visitor that a 404 does not?')
            ->hint('Import the controller at the top of `routes/web.php`: `use App\Http\Controllers\ProgramController;`.')
            ->hint('Return the view with `view(\'programs.show\', [\'program\' => $program])`. Give the method the return type `View` (`Illuminate\View\View`).')
            ->code(<<<'PHP'
                public function show(Program $program): View
                {
                    abort_unless($program->is_published, 404);

                    $program->load('upcomingSessions');

                    return view('programs.show', [
                        'program' => $program,
                    ]);
                }
                PHP)
            ->tryIt('Run `php artisan route:list --name=programs`. You see `programs/{program:slug}` and `ProgramController@show`. Open http://localhost:8000/programs/no-such-program. You see a 404 page.')
            ->commit('Add the program page route')
            ->docs('https://laravel.com/docs/routing#implicit-binding', 'Laravel docs: Implicit binding')
            ->docs('https://laravel.com/docs/routing#customizing-the-key', 'Laravel docs: Customizing the key')
            ->docs('https://laravel.com/docs/errors#http-exceptions', 'Laravel docs: HTTP exceptions')
            ->check(Check::route('programs.show')),

        Step::make('Show one program with its upcoming dates')
            ->run('php artisan make:view programs.show')
            ->explain(<<<'TEXT'
                Make the view with the command below, and delete the `<div>` that it writes. Use the public layout, with the name of the program as the title: `<x-layouts::site :title="$program->name">`. The colon before `title` means "this is PHP, not text".

                The page has three parts:

                1. A header: a badge with the type, such as "Club" (`$program->type->label()`), the name as the `<h1>`, and the age range when there is one.
                2. The summary, and the description when there is one. Staff type the description with line breaks. Show it with `{!! nl2br(e($program->description)) !!}`. `e()` escapes the HTML that staff type, and `nl2br()` turns each line break into a `<br>`.
                3. An "Upcoming dates" section. Show one card for each upcoming session: the label, the dates and the schedule, the price and the member price, and the number of spots. When the registration is not open, say "Registration is not open." When there are no upcoming sessions, say "No upcoming dates yet. Check back soon."

                Use the methods from the Programs feature: `ageRange()`, `dates()`, `price()`, `memberPrice()` and `isRegistrationOpen()`. The view does no maths and no formatting of its own.
                TEXT)
            ->think('`{{ }}` escapes HTML, but `{!! !!}` does not. Why is `{!! nl2br(e(...)) !!}` still safe? What happens without `e()`?')
            ->hint('`@forelse ($program->upcomingSessions as $session) ... @empty ... @endforelse` is a loop with a part for an empty list.')
            ->hint('`@unless ($session->isRegistrationOpen())` is the same as `@if (! ...)`.')
            ->code(<<<'BLADE'
                @forelse ($program->upcomingSessions as $session)
                    <april:card>
                        <slot:title class="text-lg">{{ $session->label }}</slot:title>
                        <slot:description>{{ $session->dates() }}@if ($session->schedule) · {{ $session->schedule }}@endif</slot:description>
                        <slot:content class="space-y-1 text-sm">
                            <p><span class="font-medium">{{ $session->price() }}</span>@if ($session->memberPrice()) · Members {{ $session->memberPrice() }}@endif</p>
                            {{-- The spots and the registration notice go here. --}}
                        </slot:content>
                    </april:card>
                @empty
                    <p class="text-muted-foreground">No upcoming dates yet. Check back soon.</p>
                @endforelse
                BLADE)
            ->tryIt('Run `php artisan migrate:fresh --seed`. Open http://localhost:8000/programs/junior-robotics-coding-club. You see the club, "Ages 6–9", and its Fall 2026 session with "$150.00 · Members $135.00".')
            ->commit('Show the program page')
            ->docs('https://laravel.com/docs/blade#displaying-unescaped-data', 'Laravel docs: Displaying unescaped data')
            ->docs('https://laravel.com/docs/blade#loops', 'Laravel docs: Loops')
            ->check(Check::view('programs.show')),

        Step::make('List the camps and the clubs with one method')
            ->explain(<<<'TEXT'
                Now the lists. The camps page and the clubs page differ only in the type. So write one `index()` method that takes the type, and two routes that each give it a different type.

                A route can give a value to the method without a value in the URL: `->defaults('type', 'camp')`. Laravel passes it to the parameter with the same name, `string $type`. Add two routes: `/camps` with the type `camp` and the name `camps.index`, and `/clubs` with the type `club` and the name `clubs.index`.

                In `index()`, turn the string into the enum with `ProgramType::from($type)`. Then get the published programs of that type, sorted by name. Add `with('upcomingSessions')`, so Laravel loads the sessions of all the programs in one extra query. Return the view `programs.index` with the type and the programs.
                TEXT)
            ->think('There are 20 clubs. Without `with()`, the view asks each club for its sessions. How many database queries does the page make? And with `with()`?')
            ->hint('`Route::get(\'/camps\', [ProgramController::class, \'index\'])->defaults(\'type\', \'camp\')->name(\'camps.index\');`')
            ->hint('Chain the query: `Program::published()->where(\'type\', $type)->with(\'upcomingSessions\')->orderBy(\'name\')->get()`.')
            ->code(<<<'PHP'
                public function index(string $type): View
                {
                    $type = ProgramType::from($type);

                    $programs = Program::published()
                        ->where('type', $type)
                        ->with('upcomingSessions')
                        ->orderBy('name')
                        ->get();

                    return view('programs.index', [
                        'type' => $type,
                        'programs' => $programs,
                    ]);
                }
                PHP)
            ->tryIt('Run `php artisan route:list --except-vendor`. You see `camps` and `clubs`, both to `ProgramController@index`.')
            ->commit('Add the camps and clubs routes')
            ->docs('https://laravel.com/docs/eloquent-relationships#eager-loading', 'Laravel docs: Eager loading')
            ->docs('https://laravel.com/docs/routing#parameters-and-dependency-injection', 'Laravel docs: Route parameters')
            ->check(Check::route('camps.index', 'clubs.index')),

        Step::make('Show the list of programs')
            ->run('php artisan make:view programs.index')
            ->explain(<<<'TEXT'
                Make the view with the command below, and delete its `<div>`. Use the plural label of the type as the title and the `<h1>`, such as "Camps".

                When the list is empty, say so: "There are no camps listed right now. Check back soon." Use the plural label in lowercase, so the same sentence works for clubs: `Str::lower($type->pluralLabel())`.

                Otherwise, show a grid of cards, one for each program. Each card has the name as a link to the program page, the age range, the summary, and the next session: its label, dates and price. A program without upcoming sessions says "No upcoming dates yet." Add a "Details" button at the bottom of each card.

                The next session is `$program->upcomingSessions->first()`. Note that there are no brackets after `upcomingSessions`. Without brackets, you read the sessions that the controller already loaded. With brackets, you start a new query.
                TEXT)
            ->think('Why does `$program->upcomingSessions` (no brackets) not run a query here, but `$program->upcomingSessions()->first()` does?')
            ->hint('`@if ($session = $program->upcomingSessions->first())` stores the session and checks it in one line.')
            ->hint('Link to a program with `route(\'programs.show\', $program)`. Laravel puts the slug in the URL, because the route says `{program:slug}`.')
            ->code(<<<'BLADE'
                <april:card>
                    <slot:title class="text-xl">
                        <a href="{{ route('programs.show', $program) }}" class="hover:underline">{{ $program->name }}</a>
                    </slot:title>
                    <slot:description>{{ $program->ageRange() }}</slot:description>
                    <slot:content class="space-y-3 text-sm">
                        <p>{{ $program->summary }}</p>
                        @if ($session = $program->upcomingSessions->first())
                            <p class="text-muted-foreground">Next: {{ $session->label }} · {{ $session->dates() }} · {{ $session->price() }}</p>
                        @else
                            <p class="text-muted-foreground">No upcoming dates yet.</p>
                        @endif
                    </slot:content>
                    <slot:footer>
                        <april:button-link href="{{ route('programs.show', $program) }}" variant="outline" size="sm">Details</april:button-link>
                    </slot:footer>
                </april:card>
                BLADE)
            ->tryIt('Open http://localhost:8000/clubs. You see the four clubs in name order, each with its next session. Open http://localhost:8000/camps. You see the Pro-D Day camp, or the "no camps" text when its day is over.')
            ->commit('Show the camps and clubs lists')
            ->docs('https://laravel.com/docs/eloquent-relationships#relationship-methods-vs-dynamic-properties', 'Laravel docs: Relationship methods and dynamic properties')
            ->check(Check::view('programs.index')),

        Step::make('Add the pages to the menu and the home page')
            ->explain(<<<'TEXT'
                Link to the new pages, as you did for Visit Us.

                Open `resources/views/layouts/site.blade.php`. Add a "Discover" menu after "Explore", with two links: "Camps" (`camps.index`, "Pro-D day and summer camps.") and "Clubs" (`clubs.index`, "Ten-week after-school clubs.").

                Open `resources/views/home.blade.php`. Add a "See clubs and camps" button next to "Plan your visit", to `route('clubs.index')`, with `variant="outline"`. Then add two items to the array of cards: "Clubs" ("Robotics, 3D design, film and more, one afternoon a week.") and "Camps" ("Full-day science camps on Pro-D days and in the summer.").

                If the Instant navigation feature is done, give the new links `wire:navigate`, here and on the program cards: every new link to a page of the site uses it.
                TEXT)
            ->think('The menu marks the current page with `request()->routeIs()`. On a program page, no menu link is marked. Is that right? What would you change?')
            ->hint('The two menus are two keys in the same array: `\'Explore\' => [...], \'Discover\' => [...]`.')
            ->tryIt('Open http://localhost:8000. Click "Discover", then "Clubs". Click a club. Use the Back button, and click "Camps" on the home page.')
            ->commit('Link to the camps and clubs pages')
            ->check(Check::all(
                Check::fileContains('resources/views/layouts/site.blade.php', "'clubs.index'"),
                Check::fileContains('resources/views/home.blade.php', "route('camps.index')"),
            )),

        Step::make('Run the checks and open your pull request')
            ->run('php artisan guide:verify program-catalog')
            ->explain(<<<'TEXT'
                Run the acceptance test with `guide:verify`. Then run `composer ci:check`. It runs Pint, PHPStan and every test, as GitHub does on your pull request.

                A good test fails when the rule is broken. Prove it for one rule. Remove the `abort_unless()` line from `show()`, and run the test again. The draft rule fails. Put the line back.

                Extra: count the queries of eager loading. In `php artisan tinker`, run `DB::enableQueryLog(); App\Models\Program::with('upcomingSessions')->get()->each(fn ($p) => $p->upcomingSessions->first()); count(DB::getQueryLog());`. You see `2`. Run `DB::flushQueryLog();`, then the same line without `with('upcomingSessions')`. You see one query more than the number of programs.
                TEXT)
            ->think('Which rule protects the centre the most? What would a visitor see without it?')
            ->tryIt('Run `composer ci:check`. Pint, PHPStan and all the tests pass.')
            ->commit('Fix the checks for the camps and clubs pages')
            ->check(Check::testPassed('ProgramCatalogTest')),
    ]);
