<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('saturday-programs')
    ->title('Saturday programming page')
    ->section(Section::Catalog)
    ->level(1)
    ->needs(['layout'])
    ->summary('Special Saturday events, such as Spooktacular Science. They are drop-in and free with admission. The page lists upcoming events only.')
    ->teaches(['Models', 'Migrations', 'Factories', 'Query scopes', 'Dates with Carbon'])
    ->test('SaturdayProgramsTest.php')
    ->intro(<<<'TEXT'
        On some Saturdays, BLSC runs a special event, such as Spooktacular Science. In this feature, you build the page that lists the next events. When you finish, a visitor opens `/saturdays` and sees what is on this month.

        Staff will add events later from an admin page. So the events cannot be text in the HTML. They must come from the database. You build the feature in two halves. First, you store the events: a table, a model and a scope that finds the next events. Then, you show them: a route, a controller and a view.

        Keep this path in mind while you work. The browser asks for `/saturdays`. The route sends the request to the controller. The controller asks the model for the next events. The view turns the events into HTML. Almost every page in Laravel uses this path, so this feature teaches you the base of the framework.
        TEXT)
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/3', 'Laravel From Scratch, episode 3: Routing 101')
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/8', 'Laravel From Scratch, episode 8: Databases, Migrations, and Eloquent')
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/10', 'Laravel From Scratch, episode 10: Controllers')
    ->docs('https://laravel.com/docs/lifecycle', 'Laravel docs: Request lifecycle')
    ->reflect([
        'Why does the upcoming scope use `>=` and not `>`?',
        'The view uses `{{ $event->title }}`. What could go wrong if it used `{!! $event->title !!}`?',
        'Which step was the hardest? What would help the next contributor? Open an issue or a pull request in blsc-guide to improve that step.',
    ])
    ->steps([
        Step::make('Read the acceptance test')
            ->run('php artisan guide:start saturday-programs')
            ->explain(<<<'TEXT'
                You start with the test, not with the code. The test tells you what "done" means, so you know where you are going.

                Run the command below. It copies `tests/Feature/SaturdayProgramsTest.php` into the app. Open the file. Each `test()` is one rule that the page must follow. Read all four rules before you continue.

                Do not change what the test checks. If you think that a rule is wrong, ask on the issue.
                TEXT)
            ->think('Which rule do you think is the hardest to make pass? Why?')
            ->tryIt('Run `php artisan test tests/Feature/SaturdayProgramsTest.php`. All four rules fail. That is correct, because the page does not exist yet.')
            ->commit('Add the Saturday programs acceptance test')
            ->docs('https://laravel.com/docs/http-tests', 'Laravel docs: HTTP tests')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/22', 'Laravel From Scratch, episode 22: How to Get Started Testing Your Code')
            ->check(Check::fileExists('tests/Feature/SaturdayProgramsTest.php')),

        Step::make('Make a model for events')
            ->run('php artisan make:model SaturdayEvent -mf')
            ->explain(<<<'TEXT'
                Now you make a home for the events. In Laravel, a model is a PHP class for one database table. The `SaturdayEvent` model is for the `saturday_events` table. Laravel finds the table name from the class name. This system is called Eloquent.

                The model needs two more files. A migration makes the table. A factory makes fake events for the tests. One artisan command can make all three files.
                TEXT)
            ->think('Look at the test again. Which columns does an event need?')
            ->hint('Run `php artisan make:model --help`. Look for the options that also make a migration and a factory.')
            ->hint('The test makes events with a `date` and a `title`. You add the columns in the next step.')
            ->tryIt('Open the three new files: `app/Models/SaturdayEvent.php`, the new file in `database/migrations` and `database/factories/SaturdayEventFactory.php`. They are almost empty. You fill them in the next steps.')
            ->commit('Add the SaturdayEvent model')
            ->docs('https://laravel.com/docs/eloquent#generating-model-classes', 'Laravel docs: Generating model classes')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/8', 'Laravel From Scratch, episode 8: Databases, Migrations, and Eloquent')
            ->check(Check::classExists('App\Models\SaturdayEvent')),

        Step::make('Describe the table in the migration')
            ->run('php artisan migrate')
            ->code("\$table->date('date');\n\$table->string('title');\n\$table->text('description')->nullable();\n\$table->string('schedule')->nullable();")
            ->explain(<<<'TEXT'
                A migration is a PHP file that changes the database. Git keeps the migrations, so every contributor gets the same tables when they run `php artisan migrate`.

                Think about what an event needs. It must have a date and a title. It can also have a description and a schedule, such as "Shows at 11:00 AM and 1:30 PM". A column that can be empty must be `nullable()`. Add the four columns to the migration, then run `php artisan migrate`.

                Next, open the model. Add the column names to `#[Fillable]`. Then cast `date` to a date. A cast turns the text from the database into a Carbon object, so you can format the date and compare it with other dates.
                TEXT)
            ->think('Why is `title` required, but `description` is not? What would staff do if every field were required?')
            ->hint('`string` is for short text, up to 255 characters. `text` is for long text.')
            ->hint('Look at `app/Models/ProgramSession.php`. It casts `starts_on` to a date in its `casts()` method.')
            ->tryIt('Run `php artisan tinker`, then `Schema::getColumnListing(\'saturday_events\')`. You see your columns. Type `exit` to leave tinker.')
            ->commit('Add the saturday_events columns')
            ->docs('https://laravel.com/docs/migrations#available-column-types', 'Laravel docs: Column types')
            ->docs('https://laravel.com/docs/migrations#column-modifiers', 'Laravel docs: Column modifiers, such as nullable()')
            ->docs('https://laravel.com/docs/eloquent-mutators#date-casting', 'Laravel docs: Date casting')
            ->check(Check::table('saturday_events', ['date', 'title', 'description', 'schedule'])),

        Step::make('Fill in the factory')
            ->code("'date' => fake()->dateTimeBetween('now', '+3 months'),\n'title' => fake()->words(3, true),\n'description' => fake()->sentence(),\n'schedule' => 'Shows at 11:00 AM and 1:30 PM',")
            ->explain(<<<'TEXT'
                The tests need events, but they cannot use real BLSC events. Real data changes, and a test must give the same result every time. A factory solves this. It makes fake events on demand.

                The tests call `SaturdayEvent::factory()->create()`. Each test then sets only the fields that it cares about, such as the date. The factory fills in the rest.
                TEXT)
            ->think('Why do the tests use a factory, and not the real BLSC events?')
            ->hint('Open `database/factories/ProgramFactory.php`. Copy its pattern.')
            ->hint('`fake()` gives random data, such as `fake()->sentence()`.')
            ->tryIt('In `php artisan tinker`, run `App\Models\SaturdayEvent::factory()->make()`. You see one fake event. `make()` does not save it, so your database stays clean.')
            ->commit('Fill in the SaturdayEvent factory')
            ->docs('https://laravel.com/docs/eloquent-factories#defining-model-factories', 'Laravel docs: Defining model factories')
            ->check(Check::fileContains('database/factories/SaturdayEventFactory.php', "'title'")),

        Step::make('Find the next events with a scope')
            ->code("#[Scope]\nprotected function upcoming(Builder \$query): void\n{\n    \$query->whereDate('date', '>=', today());\n}")
            ->explain(<<<'TEXT'
                The page shows only the events from today onward. You could write that query in the controller. But staff pages and emails will need the same query later, and copies of a query drift apart.

                A query scope gives a query a name. Then any code can write `SaturdayEvent::upcoming()->get()`. If the rule changes, you change it in one place. Add an `upcoming` scope to the model.
                TEXT)
            ->think('An event is today. Is it upcoming? Find the rule in the test that answers this.')
            ->hint('Look at the `upcoming` scope in `app/Models/ProgramSession.php`.')
            ->hint('Compare the date with `today()`. Think about `>` and `>=`.')
            ->hint('`whereDate()` compares only the date part. It ignores the time.')
            ->tryIt(<<<'TEXT'
                In `php artisan tinker`, make one past event and one future event:

                `App\Models\SaturdayEvent::factory()->create(['date' => today()->subWeek()]);`

                `App\Models\SaturdayEvent::factory()->create(['date' => today()->addWeek()]);`

                Then run `App\Models\SaturdayEvent::upcoming()->count()`. You see `1`. Keep the events. You use them to look at the page later.
                TEXT)
            ->commit('Add an upcoming scope to SaturdayEvent')
            ->docs('https://laravel.com/docs/eloquent#local-scopes', 'Laravel docs: Local scopes')
            ->check(Check::fileContains('app/Models/SaturdayEvent.php', 'function upcoming')),

        Step::make('Make a controller')
            ->run('php artisan make:controller SaturdayProgramController --invokable')
            ->code("public function __invoke(): View\n{\n    return view('saturday-programs', [\n        'events' => SaturdayEvent::upcoming()->orderBy('date')->get(),\n    ]);\n}")
            ->explain(<<<'TEXT'
                The data half is ready. Now you start the page half, with the controller.

                A controller gets the data for a page and gives it to a view. This page does only one thing, so use an invokable controller. It has one method, `__invoke()`. In it, get the upcoming events and pass them to a view called `saturday-programs`.

                The controller does not make HTML. That is the job of the view. When each part has one job, you always know where to look for a bug.
                TEXT)
            ->think('In which order must the events show? What does a visitor expect?')
            ->hint('Use your `upcoming` scope, then `orderBy()`.')
            ->hint('Look at `app/Http/Controllers/ProgramController.php` to see how a controller passes data to a view.')
            ->commit('Add SaturdayProgramController')
            ->docs('https://laravel.com/docs/controllers#single-action-controllers', 'Laravel docs: Single action controllers')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/10', 'Laravel From Scratch, episode 10: Controllers')
            ->check(Check::classExists('App\Http\Controllers\SaturdayProgramController')),

        Step::make('Add a named route')
            ->code("Route::get('/saturdays', SaturdayProgramController::class)->name('saturday-programs');")
            ->explain(<<<'TEXT'
                The controller exists, but no URL leads to it yet. A route connects a URL to a controller. Add one under the Explore heading in `routes/web.php`.

                Give the route a name. Other code then links to it with `route('saturday-programs')`, not with the text `/saturdays`. If BLSC wants a different URL later, you change one line, and every link still works.
                TEXT)
            ->think('The test calls `route(\'saturday-programs\')`. What happens if you use a different name?')
            ->hint('Look at the `visit-us` route in `routes/web.php`.')
            ->tryIt('Run `php artisan route:list --name=saturday`. You see your route. Now open http://localhost:8000/saturdays. You see an error that the view does not exist. That is correct. You make the view next.')
            ->commit('Add the saturday-programs route')
            ->docs('https://laravel.com/docs/routing#named-routes', 'Laravel docs: Named routes')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/3', 'Laravel From Scratch, episode 3: Routing 101')
            ->check(Check::route('saturday-programs')),

        Step::make('Make the view')
            ->explain(<<<'TEXT'
                Now you make the page that visitors see. Create `resources/views/saturday-programs.blade.php`, and wrap it in `<x-layouts::site>`. The layout adds the header and the footer, so every page looks the same.

                Near the top, say "Free with admission". Then show each event as a card with its date, title, schedule and description. Copy the card style from `resources/views/programs/index.blade.php`.

                Use `{{ }}` to show data. Blade escapes everything inside it. If a title contains HTML, the browser shows it as text and does not run it.
                TEXT)
            ->think('What must the page show when there are no events? The test does not check this, but a visitor will see it.')
            ->hint('`@forelse` is like `@foreach`, with an `@empty` part for an empty list.')
            ->hint('`$event->date` is a Carbon object, so `$event->date->format(\'l, F j\')` shows "Saturday, October 31".')
            ->tryIt('Open http://localhost:8000/saturdays. You see the future event from step 5, and not the past one. Make the window narrow, too, and check that the page still reads well on a phone.')
            ->commit('Add the Saturday programs view')
            ->docs('https://laravel.com/docs/blade#displaying-data', 'Laravel docs: Displaying data')
            ->docs('https://laravel.com/docs/blade#loops', 'Laravel docs: Loops')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/5', 'Laravel From Scratch, episode 5: Pass Data to Views')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/6', 'Laravel From Scratch, episode 6: Blade Directives')
            ->check(Check::view('saturday-programs')),

        Step::make('Add the page to the Explore menu')
            ->explain(<<<'TEXT'
                The page works, but visitors cannot find it yet. The menus come from the `$menus` array at the top of `resources/views/layouts/site.blade.php`. Add one entry to the Explore list. The layout makes the link from the data, so you write no HTML.
                TEXT)
            ->think('Why does the layout keep the menu in an array and not in HTML?')
            ->tryIt('Open the home page. Open the Explore menu, and click your new link.')
            ->commit('Add Saturdays to the Explore menu')
            ->docs('https://laravel.com/docs/blade#components', 'Laravel docs: Components')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/4', 'Laravel From Scratch, episode 4: Layout Files')
            ->check(Check::fileContains('resources/views/layouts/site.blade.php', "'saturday-programs'")),

        Step::make('Make the acceptance test pass')
            ->run('php artisan guide:verify saturday-programs')
            ->explain(<<<'TEXT'
                You are close. Run the acceptance test with the command below. The guide saves the result and ticks off this step.

                If the test fails, read the first failure only. It names the rule and the line. Fix that one problem, then run the test again. Later failures often come from the first one.

                When the test passes, run `composer ci:check`. It runs the code style check, the type check and every other test in the app. Then go to "Finish" below.
                TEXT)
            ->docs('https://laravel.com/docs/testing', 'Laravel docs: Testing')
            ->check(Check::testPassed('SaturdayProgramsTest')),
    ]);
