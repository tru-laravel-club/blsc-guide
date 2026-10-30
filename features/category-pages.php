<?php

use Illuminate\Support\Facades\Route;
use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('category-pages')
    ->title('Category pages')
    ->section(Section::Catalog)
    ->level(2)
    ->needs(['program-categories'])
    ->summary('One page for each category lists its programs and upcoming sessions. A new category gets its page with no new code.')
    ->teaches(['Route model binding', 'Custom binding keys', 'Scoped bindings', 'Invokable controllers', 'Refactoring routes'])
    ->test('CategoryPagesTest.php')
    ->intro(<<<'TEXT'
        Open `routes/web.php`. After the Program categories feature, staff can add a category, such as Homeschool. But visitors cannot find it. Only two categories have a page: `/camps` and `/clubs`. Each one is a route in the code, and the "Discover" menu in `resources/views/layouts/site.blade.php` names them by hand. So a new category still needs a developer.

        In this feature, every category gets a page from one route: `/programs/{category:slug}`. Laravel finds the category by its slug. A new category gets a page the moment staff save it. The Discover menu and the home page read the categories from the database, in the order that staff chose. `/programs` lists all categories.

        The program pages move under their category, such as `/programs/clubs/robotics-club`. The address tells the visitor where they are. Laravel finds the program inside the category, so `/programs/camps/robotics-club` is not found. Then the `/camps` and `/clubs` routes have no job, and you remove them.

        This feature changes addresses, so old code and old tests break. That is expected. Each break shows you a place that named a category in the code. When you fix them all, no code names a category.

        Keep this path in mind while you work. A visitor opens `/programs/clubs/robotics-club`. Laravel finds the category with the slug `clubs`. Then it finds the program with the slug `robotics-club` among the programs of that category. If either one is missing, the visitor sees 404. If both exist, the controller shows the program.
        TEXT)
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/3', 'Laravel From Scratch, episode 3: Routing 101')
    ->docs('https://laravel.com/docs/routing#implicit-binding', 'Laravel docs: Implicit binding')
    ->reflect([
        'Staff add a category called "Homeschool". List every file that changes.',
        'The program address has the category in it. What happens to old links when staff move a program to another category? Is that a good trade?',
        'Which step was the hardest? What would help the next contributor? Open an issue or a pull request in blsc-guide to improve that step.',
    ])
    ->steps([
        Step::make('Claim the feature and copy in its test')
            ->run('php artisan guide:start category-pages')
            ->explain(<<<'TEXT'
                You start with the test. Run the command below. It copies `tests/Feature/CategoryPagesTest.php` into the app.

                Read the whole test before you write code. It tells you the route names that the code must use: `programs.index`, `categories.show` and `programs.show`. Look at how the test calls `programs.show`. It now gives two values: the category and the program.

                Look at the rule "a new category gets a page and a menu link with no code change". The test makes a category in the database, and then expects a page and a link. Nothing in the code may name "Homeschool".
                TEXT)
            ->think('The last rule checks that `clubs.index` does not exist. Why does the test care about a route that is gone?')
            ->tryIt('Run `php artisan test tests/Feature/CategoryPagesTest.php`. The rules fail, because the routes do not exist yet.')
            ->commit('Add the category pages acceptance test')
            ->docs('https://laravel.com/docs/http-tests', 'Laravel docs: HTTP tests')
            ->check(Check::fileExists('tests/Feature/CategoryPagesTest.php')),

        Step::make('Add a page for each category')
            ->run('php artisan make:controller CategoryController')
            ->explain(<<<'TEXT'
                Make a public `CategoryController` with the command below. It is not the one in `Admin`. It has two methods. `index()` lists all categories with `ordered()`. `show()` lists the published programs of one category.

                Move the code of `ProgramController::index()` into `CategoryController::show()`. Then delete `index()` from `ProgramController`. The old method took a slug and called `firstOrFail()`. Now route model binding does that work. Type the parameter as `Category $category`, and Laravel finds it.

                Add two routes in the Discover part of `routes/web.php`: `/programs` named `programs.index`, and `/programs/{category:slug}` named `categories.show`. The `:slug` part tells Laravel to find the category by its slug, not by its ID.

                Leave the `/camps` and `/clubs` routes for now. The menu still uses them. You remove them in a later step.
                TEXT)
            ->think('The old code called `firstOrFail()`. What does Laravel do now when the slug in the address matches no category?')
            ->hint('Look at the old `ProgramController::index()`. Its body moves almost unchanged.')
            ->hint('`Route::get(\'/programs/{category:slug}\', [CategoryController::class, \'show\'])->name(\'categories.show\');`')
            ->code(<<<'PHP'
                Route::get('/programs', [CategoryController::class, 'index'])->name('programs.index');
                Route::get('/programs/{category:slug}', [CategoryController::class, 'show'])->name('categories.show');
                PHP)
            ->tryIt('Run `php artisan route:list --path=programs`. Among the routes, you see `programs.index` and `categories.show`. Each one names `CategoryController`.')
            ->commit('Add a page for each category')
            ->docs('https://laravel.com/docs/routing#implicit-binding', 'Laravel docs: Implicit binding')
            ->docs('https://laravel.com/docs/routing#customizing-the-default-key-name', 'Laravel docs: Customizing the key')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/10', 'Laravel From Scratch, episode 10: Controllers')
            ->check(Check::route('programs.index', 'categories.show')),

        Step::make('Make the category views')
            ->run('mkdir -p resources/views/categories && git mv resources/views/programs/index.blade.php resources/views/categories/show.blade.php')
            ->explain(<<<'TEXT'
                The page that lists the programs of one category is now a category page. So move its view to `categories/show.blade.php` with the command below. `git mv` needs the new folder first, and it keeps the history of the file.

                The category page lists each program with its upcoming sessions. Show all upcoming sessions of a program, not only the next one. Keep the text "There are no ... listed right now." for an empty category.

                Then make `categories/index.blade.php`. It shows one card for each category, with its name, its summary and a link to its page. Copy the cards on the home page for the layout.
                TEXT)
            ->think('A category has no programs yet. Should `/programs` still list it? What does a visitor see on its page?')
            ->hint('Look at `resources/views/home.blade.php`. Each card there is a link with an `<april:card>` inside.')
            ->hint('Use `@forelse ($program->upcomingSessions as $session)` with an `@empty` line for a program without dates.')
            ->tryIt('Run `php artisan serve`. Open http://localhost:8000/programs. You see a card for each category. Click one. You see its programs. Open http://localhost:8000/programs/no-such-category. You see 404.')
            ->commit('Add the category views')
            ->docs('https://laravel.com/docs/blade#loops', 'Laravel docs: Loops')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/6', 'Laravel From Scratch, episode 6: Blade Directives')
            ->check(Check::all(
                Check::view('categories.index'),
                Check::view('categories.show'),
            )),

        Step::make('Move each program page under its category')
            ->explain(<<<'TEXT'
                Change the program route to `/programs/{category:slug}/{program:slug}`. Add `->scopeBindings()`. Then Laravel looks for the program only among the programs of that category. It uses the `programs()` relationship on `Category`. A program in another category is not found.

                `ProgramController::show()` now takes `Category $category` and `Program $program`, in the same order as the address. The view uses `$program->category`. You already have the category, so give it to the program with `setRelation()`. Then the view does not query it again.

                Now `route('programs.show', ...)` needs two values: `[$category, $program]`. Find each place that calls it, and fix it. There are links on the category page and the "View on site" button in `admin/programs/edit.blade.php`. On the program page, make the category badge a link to the category page.
                TEXT)
            ->think('Without `scopeBindings()`, what does `/programs/camps/robotics-club` show when Robotics Club is in Clubs?')
            ->hint('Run `grep -rn "programs.show" app resources routes` to find each call.')
            ->hint('In the admin view, there is no `$category`. Use `[$program->category, $program]`.')
            ->code(<<<'PHP'
                Route::get('/programs/{category:slug}/{program:slug}', [ProgramController::class, 'show'])
                    ->scopeBindings()
                    ->name('programs.show');
                PHP)
            ->tryIt('Open http://localhost:8000/programs/clubs and click a club. The address is `/programs/clubs/` and the slug of the club. Change `clubs` to `camps` in the address. You see 404.')
            ->commit('Move program pages under their category')
            ->docs('https://laravel.com/docs/routing#implicit-model-binding-scoping', 'Laravel docs: Implicit model binding scoping')
            ->docs('https://laravel.com/docs/urls#urls-for-named-routes', 'Laravel docs: URLs for named routes')
            ->check(Check::fileContains('routes/web.php', '{category:slug}/{program:slug}')),

        Step::make('Build the Discover menu from the database')
            ->explain(<<<'TEXT'
                Open `resources/views/layouts/site.blade.php`. The `$menus` array names Camps and Clubs by hand. Replace the Discover list with the categories from the database: `Category::ordered()->get()`. Map each category to a link with its name, its page and its summary. Add a last link, "All programs", to `/programs`.

                The menu links used a route name, such as `camps.index`. A category page needs a route name and a category. So change each link to hold a full `url` instead of a `route`. Then one loop shows both menus. A link is active when its URL is the URL of the page.

                The layout runs a query on every public page. That is one small query, and it is fine here. If the menu grows, a view composer can move the query out of the view.
                TEXT)
            ->think('Staff rename "Clubs" to "After-school Clubs" and move it to the top. What does the menu show? Which file did you change?')
            ->hint('`Category::ordered()->get()->map(fn (Category $category) => [...])` gives one array for each category. Call `->all()` at the end to get a plain array.')
            ->hint('Some categories have no summary. Show the description line only `@if ($link[\'description\'])`.')
            ->code(<<<'PHP'
                'Discover' => App\Models\Category::ordered()->get()
                    ->map(fn (App\Models\Category $category) => [
                        'label' => $category->name,
                        'url' => route('categories.show', $category),
                        'description' => $category->summary,
                    ])
                    ->push(['label' => 'All programs', 'url' => route('programs.index'), 'description' => null])
                    ->all(),
                PHP)
            ->tryIt('Log in as staff, open Categories, and add "Homeschool". Open the home page and click Discover. "Homeschool" is in the menu. Click it. You see its page, with no programs yet.')
            ->commit('Build the Discover menu from categories')
            ->docs('https://laravel.com/docs/collections#method-map', 'Laravel docs: The map method')
            ->docs('https://laravel.com/docs/views#view-composers', 'Laravel docs: View composers')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/4', 'Laravel From Scratch, episode 4: Layout Files')
            ->check(Check::fileContains('resources/views/layouts/site.blade.php', "route('categories.show'")),

        Step::make('List the categories on the home page')
            ->run('php artisan make:controller HomeController --invokable')
            ->explain(<<<'TEXT'
                The home page has cards for Clubs and Camps, written by hand. It also needs the categories from the database.

                The home route is `Route::view()`. It shows a view, but it cannot load data. So make an invokable controller with the command below. It has one method, `__invoke()`, which returns the `home` view with the ordered categories. In `routes/web.php`, change the home route to `Route::get('/', HomeController::class)`.

                In `home.blade.php`, keep the Visit Us card and add one card for each category. Change the "See clubs and camps" button to "See all programs", with a link to `/programs`.
                TEXT)
            ->think('Why is a controller with one method better here than a query inside `home.blade.php`?')
            ->hint('A route to an invokable controller takes the class name only: `HomeController::class`.')
            ->hint('Loop over `$categories` after the Visit Us card. Use `route(\'categories.show\', $category)` for each link.')
            ->code(<<<'PHP'
                public function __invoke(): View
                {
                    return view('home', [
                        'categories' => Category::ordered()->get(),
                    ]);
                }
                PHP)
            ->tryIt('Open http://localhost:8000. You see Visit Us and one card for each category, in the order that staff chose.')
            ->commit('List the categories on the home page')
            ->docs('https://laravel.com/docs/controllers#single-action-controllers', 'Laravel docs: Invokable controllers')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/5', 'Laravel From Scratch, episode 5: Pass Data to Views')
            ->check(Check::fileContains('routes/web.php', 'HomeController')),

        Step::make('Remove the old routes and fix the old tests')
            ->explain(<<<'TEXT'
                Now nothing needs `/camps` and `/clubs`. Delete both routes from `routes/web.php`. Then search for the old names: `grep -rn "clubs.index\|camps.index" app resources routes tests`.

                Run all the tests. Some old tests fail. Read each failure before you change it. These tests break on purpose, because the addresses changed:

                - `tests/Feature/ProgramCatalogTest.php` opens `clubs.index` and `camps.index`. Change them to `route('categories.show', ...)` with the category slug or model.
                - `tests/Feature/ProgramCategoriesTest.php` opens `clubs.index` in its last rule. Use `route('categories.show', $clubs)`.
                - Each call to `route('programs.show', $program)` needs the category too: `route('programs.show', [$program->category, $program])`.

                Some tests already name both values, such as `['category' => $program->category, 'program' => $program]`. They work with the old and the new route, so leave them.

                Only change an old test when the behaviour changed on purpose. Here it did: the addresses moved.
                TEXT)
            ->think('A test fails with "Missing required parameter for [Route: programs.show]". Is that a bug in the app or in the test? How do you know?')
            ->hint('`php artisan test --stop-on-failure` stops at the first failure. Fix one at a time.')
            ->tryIt('Run `grep -rn "clubs.index\|camps.index" app resources routes tests`. You see only the last rule of `CategoryPagesTest.php`, which checks that the routes are gone. Then run `php artisan test`. All the tests pass.')
            ->commit('Remove the camps and clubs routes')
            ->check(fn (): bool => ! Route::has('clubs.index') && ! Route::has('camps.index')),

        Step::make('Run the checks and open your pull request')
            ->run('php artisan guide:verify category-pages')
            ->explain(<<<'TEXT'
                Run the acceptance test with `guide:verify`. Then run `composer ci:check`. It runs Pint, PHPStan and every test, as GitHub does on your pull request.

                In your pull request, list the old tests that you changed, and say why. The reviewer then knows that each change was on purpose.
                TEXT)
            ->think('Search the code for "camps" and "clubs". Where do they still appear? Is each one data, such as the seeder, or code that decides something?')
            ->tryIt('Run `composer ci:check`. Pint, PHPStan and all the tests pass.')
            ->commit('Fix the checks for category pages')
            ->check(Check::testPassed('CategoryPagesTest')),
    ]);
