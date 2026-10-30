<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('pages-posts')
    ->title('Pages and posts')
    ->section(Section::Content)
    ->level(2)
    ->needs(['safe-html', 'staff-roles'])
    ->summary('Staff write pages and posts with the April UI editor. Staff use posts for news and science articles.')
    ->teaches(['Enum casts', 'Date casts', 'Local scopes', 'Pagination', 'Route model binding', 'Time in tests'])
    ->test('PagesPostsTest.php')
    ->intro(<<<'TEXT'
        The centre has news to share: a new club, a science story, photos from a camp. It also has pages that change only a few times a year, such as "Volunteer with us". Today a developer must write each one in a Blade view. In this feature, staff write them in the admin area.

        Pages and posts have the same parts: a title, a web address, a short summary, a body and a publish date. So one `Page` model holds both. A `type` column says which one it is, with the `PageType` enum: `Page` or `Post`. The code needs the type, because posts are listed on `/news` and pages are not. So the type is an enum, and not data that staff can change.

        Publishing is a date, not a checkbox. A page is public when `published_at` is in the past. With no date, it is a draft. With a date in the future, it is scheduled. Staff can write a post on Friday and make it appear on Monday morning, without logging in on Monday.

        The path of a post:

        1. Staff write the post at `/admin/pages/create`. The body uses `<x-editor>` from Safe HTML, and the `SafeHtml` cast cleans it.
        2. When the publish date has passed, the `published()` scope lets the post through.
        3. `/news` lists the published posts, newest first, ten on each page. `/news/{slug}` shows one post. `/pages/{slug}` shows one page.
        TEXT)
    ->docs('https://laravel.com/docs/eloquent#local-scopes', 'Laravel docs: Local scopes')
    ->docs('https://laravel.com/docs/pagination#basic-usage', 'Laravel docs: Pagination')
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/8', 'Laravel From Scratch, episode 8: Databases, Migrations, and Eloquent')
    ->reflect([
        'Why is publishing a date and not a checkbox? What can staff do with a date that they cannot do with a checkbox?',
        'Pages and posts share one table, and the type is an enum. When is a second table better than a type column?',
        'The app stores times in UTC, and Kamloops is in Pacific time. A staff member schedules a post for 9:00 AM. When does it appear? How would you fix it?',
        'Which step was the hardest? What would help the next contributor? Open an issue or a pull request in blsc-guide to improve that step.',
    ])
    ->steps([
        Step::make('Claim the feature and copy in its test')
            ->run('php artisan guide:start pages-posts')
            ->explain(<<<'TEXT'
                Run the command below. It copies `tests/Feature/PagesPostsTest.php` into the app.

                Read the test first. It names the `Page` model, the `PageType` enum, the factory states `post()`, `draft()` and `scheduled()`, and the routes `news.index`, `news.show`, `pages.show` and `admin.pages.*`. Later features use these names, so keep them.

                Look at "a scheduled post is hidden until its date". The test calls `$this->travel(4)->days()`. Laravel moves the clock of the test forward, so the test does not wait four days. Each time your code asks for `now()`, it gets the new time.
                TEXT)
            ->think('A post at `/news/{slug}` and a page at `/pages/{slug}` are in the same table. What must `/news/volunteer` show when `volunteer` is a page?')
            ->tryIt('Run `php artisan test tests/Feature/PagesPostsTest.php`. All the rules fail, because the `Page` model does not exist yet.')
            ->commit('Add the pages and posts acceptance test')
            ->docs('https://laravel.com/docs/mocking#interacting-with-time', 'Laravel docs: Interacting with time')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/22', 'Laravel From Scratch, episode 22: How to Get Started Testing Your Code')
            ->check(Check::fileExists('tests/Feature/PagesPostsTest.php')),

        Step::make('Add the PageType enum')
            ->explain(<<<'TEXT'
                Create `app/Enums/PageType.php`, a string-backed enum with two cases: `Page = 'page'` and `Post = 'post'`. The database stores the string value.

                Add a `label()` method for staff: "Page" and "News post". Add a `routeName()` method that returns the name of the public route for the type: `pages.show` or `news.show`. Then a view can link to any page without an `if`.

                Program categories moved the program types from an enum into a table. Why is an enum right here? Because the code branches on the type: only posts go on `/news`. Staff cannot add a type that the code does not know.
                TEXT)
            ->think('Staff ask for a third type, "Science article". Does it need a new type, or is it a post with a different title? What would change in the code for a new type?')
            ->hint('A `match ($this)` returns one value for each case. PHPStan checks that the `match` covers every case.')
            ->code(<<<'PHP'
                enum PageType: string
                {
                    case Page = 'page';
                    case Post = 'post';

                    public function label(): string
                    {
                        return match ($this) {
                            self::Page => 'Page',
                            self::Post => 'News post',
                        };
                    }

                    public function routeName(): string
                    {
                        return match ($this) {
                            self::Page => 'pages.show',
                            self::Post => 'news.show',
                        };
                    }
                }
                PHP)
            ->tryIt('Run `php artisan tinker`, then `App\Enums\PageType::from(\'post\')->label()`. You see `"News post"`.')
            ->commit('Add the PageType enum')
            ->docs('https://laravel.com/docs/eloquent-mutators#enum-casting', 'Laravel docs: Enum casting')
            ->check(Check::fileExists('app/Enums/PageType.php')),

        Step::make('Create the Page model and its table')
            ->run('php artisan make:model Page -mf')
            ->explain(<<<'TEXT'
                Run the command below. It makes the model, a migration and a factory.

                In the migration, add `type`, `title`, a unique `slug`, a nullable `summary`, a nullable `body` and a nullable `published_at` timestamp. The `body` is `text`, because a post can be long.

                Why is `body` nullable? The `SafeHtml` cast turns an empty editor into `null`. A `NOT NULL` column then makes the database refuse the save, and staff see an error page.

                The news list asks for posts with a publish date in the past, sorted by that date. Add one index on `['type', 'published_at']`, so the database finds them fast when there are hundreds of posts.
                TEXT)
            ->think('`published_at` is `null` for a draft. In SQL, is `null <= now` true or false? What does that mean for drafts in the news list?')
            ->hint('Look at the `programs` migration. It has the same `name`, `slug` and `summary` columns.')
            ->hint('`$table->index([\'type\', \'published_at\'])` makes one index on two columns.')
            ->code(<<<'PHP'
                Schema::create('pages', function (Blueprint $table) {
                    $table->id();
                    $table->string('type');
                    $table->string('title');
                    $table->string('slug')->unique();
                    $table->string('summary')->nullable();
                    $table->text('body')->nullable();
                    $table->timestamp('published_at')->nullable();
                    $table->timestamps();

                    $table->index(['type', 'published_at']);
                });
                PHP)
            ->tryIt('Run `php artisan migrate`. Then, in `php artisan tinker`, run `Schema::getColumnListing(\'pages\')`. You see your columns.')
            ->commit('Add the Page model and table')
            ->docs('https://laravel.com/docs/migrations#creating-indexes', 'Laravel docs: Creating indexes')
            ->check(Check::table('pages', ['type', 'title', 'slug', 'summary', 'body', 'published_at'])),

        Step::make('Teach the model when a page is public')
            ->explain(<<<'TEXT'
                Open `app/Models/Page.php`. Use `Program` as your example for `#[Fillable]`, the docblock and the scope.

                In `casts()`, cast `type` to `PageType::class`, `body` to `SafeHtml::class` and `published_at` to `datetime`. Now `$page->type` is an enum, `$page->published_at` is a date, and the body is always clean.

                Then add the rules about publishing, in one place:

                - A `published` scope: `published_at` is less than or equal to `now()`. A draft has `null`, and SQL never matches `null`, so drafts stay out.
                - A `posts` scope: the type is `Post`.
                - `isPublished(): bool`, the same rule for one model that you already have.
                - `status(): string` for staff: "Draft", "Scheduled" or "Published".
                - `url(): string`: `route($this->type->routeName(), $this)`.
                TEXT)
            ->think('The `published` scope and `isPublished()` say the same rule twice, once in SQL and once in PHP. Why does the app need both? Where does each one run?')
            ->hint('`$this->published_at->isPast()` and `isFuture()` compare a date with now.')
            ->hint('`match (true)` checks each condition in order. Put the `null` check first, so the next lines can use the date.')
            ->code(<<<'PHP'
                #[Scope]
                protected function published(Builder $query): void
                {
                    $query->where('published_at', '<=', now());
                }

                public function isPublished(): bool
                {
                    return $this->published_at !== null && $this->published_at->isPast();
                }

                public function status(): string
                {
                    return match (true) {
                        $this->published_at === null => 'Draft',
                        $this->published_at->isFuture() => 'Scheduled',
                        default => 'Published',
                    };
                }
                PHP)
            ->tryIt('Run `php artisan tinker`, then `(new App\Models\Page([\'published_at\' => now()->addDay()]))->status()`. You see `"Scheduled"`.')
            ->commit('Add publishing rules to the Page model')
            ->docs('https://laravel.com/docs/eloquent#local-scopes', 'Laravel docs: Local scopes')
            ->docs('https://laravel.com/docs/eloquent-mutators#date-casting', 'Laravel docs: Date casting')
            ->check(Check::all(
                Check::fileContains('app/Models/Page.php', 'function published'),
                Check::fileContains('app/Models/Page.php', 'SafeHtml::class'),
            )),

        Step::make('Fill in the factory and a seeder')
            ->run('php artisan make:seeder PageSeeder')
            ->explain(<<<'TEXT'
                Open `database/factories/PageFactory.php`. The default state is a published page: type `PageType::Page`, a title, a slug from the title, a summary, a body in `<p>` tags, and `published_at` one day ago.

                Add the three states that the test uses: `post()` sets the type, `draft()` sets `published_at` to `null`, and `scheduled()` sets it one week in the future. The states combine, so `Page::factory()->post()->scheduled()` is a scheduled post.

                Then fill in `PageSeeder` with a published post, a scheduled post and a page. Call it from `DatabaseSeeder` after `ProgramSeeder`. Other contributors then see real examples after `migrate:fresh --seed`.
                TEXT)
            ->think('Why is the default state "published", and not "draft"? Which tests would be longer with the other default?')
            ->hint('`fake()->unique()->sentence(3, false)` gives a short title. `rtrim($title, \'.\')` removes the full stop at the end.')
            ->hint('`$this->call([ProgramSeeder::class, PageSeeder::class])` runs both seeders in order.')
            ->code(<<<'PHP'
                public function post(): static
                {
                    return $this->state(fn () => ['type' => PageType::Post]);
                }

                public function draft(): static
                {
                    return $this->state(fn () => ['published_at' => null]);
                }

                public function scheduled(): static
                {
                    return $this->state(fn () => ['published_at' => now()->addWeek()]);
                }
                PHP)
            ->tryIt('Run `php artisan migrate:fresh --seed`. Then, in tinker, run `App\Models\Page::all()->map->status()`. You see "Published" and "Scheduled".')
            ->commit('Add the Page factory and seeder')
            ->docs('https://laravel.com/docs/eloquent-factories#factory-states', 'Laravel docs: Factory states')
            ->docs('https://laravel.com/docs/seeding#calling-additional-seeders', 'Laravel docs: Calling additional seeders')
            ->check(Check::all(
                Check::fileContains('database/factories/PageFactory.php', 'function scheduled'),
                Check::fileContains('database/seeders/DatabaseSeeder.php', 'PageSeeder'),
            )),

        Step::make('Add the staff routes, request and controller')
            ->run('php artisan make:controller Admin/PageController --resource --model=Page')
            ->explain(<<<'TEXT'
                Copy the pattern of the category admin. Add `Route::resource('pages', Admin\PageController::class)->except('show')` to the staff group in `routes/web.php`.

                Create `PageRequest` with `php artisan make:request PageRequest`. Copy `authorize()` and `prepareForValidation()` from `CategoryRequest`, and build the slug from the `title`. The type must be a case of the enum: use `Rule::enum(PageType::class)`. `published_at` is `nullable` and a `date`.

                In the controller, `index()` lists every page and post, the newest change first. `create()` passes a new `Page` with the type `Post`, because staff write news more often than pages. `store()` and `update()` save `$request->validated()` and go back to the list.

                Watch for one trap. `validated()` returns only the fields that the request sent. The form always sends `published_at`, and an empty field becomes `null`. So staff make a page a draft again when they clear the date.
                TEXT)
            ->think('Staff write `banner` in the type field with the browser tools. Without `Rule::enum`, what happens when the model reads the type back?')
            ->hint('`Rule::unique(\'pages\', \'slug\')->ignore($page)` lets a page keep its own slug when staff save it.')
            ->hint('`Page::latest(\'updated_at\')->get()` puts the page that staff changed last at the top.')
            ->code(<<<'PHP'
                return [
                    'title' => ['required', 'string', 'max:255'],
                    'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('pages', 'slug')->ignore($page)],
                    'type' => ['required', Rule::enum(PageType::class)],
                    'summary' => ['nullable', 'string', 'max:255'],
                    'body' => ['nullable', 'string'],
                    'published_at' => ['nullable', 'date'],
                ];
                PHP)
            ->tryIt('Run `php artisan route:list --name=admin.pages`. You see six routes: index, create, store, edit, update and destroy.')
            ->commit('Add the page admin controller')
            ->docs('https://laravel.com/docs/controllers#resource-controllers', 'Laravel docs: Resource controllers')
            ->docs('https://laravel.com/docs/validation#rule-enum', 'Laravel docs: The enum rule')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/12', 'Laravel From Scratch, episode 12: Form Request Classes')
            ->check(Check::route('admin.pages.index', 'admin.pages.store', 'admin.pages.update', 'admin.pages.destroy')),

        Step::make('Build the staff pages with the editor')
            ->explain(<<<'TEXT'
                Create four views in `resources/views/admin/pages`: `index`, `create`, `edit` and `form`. Copy the category views and change what is different.

                The list shows the title, the type label, the status in a badge and the publish date. Staff then see at a glance which posts are drafts and which are scheduled.

                In the form, the type is a select over `PageType::cases()`. The publish date is an input with `type="datetime-local"`. The browser sends and expects the format `2026-11-02T09:00`, so format the value with `$page->published_at?->format('Y-m-d\TH:i')`. The body uses `<x-editor name="body" label="Text" />` from Safe HTML.

                On the edit page, show a "View on the site" link when the page is published. Use `$page->url()`.

                Last, add a "Pages and posts" link to the staff menu in `resources/views/layouts/app.blade.php`. That layout already loads the editor script.
                TEXT)
            ->think('The type is an enum, and `old(\'type\')` is a string. How do you compare them to choose the selected option?')
            ->hint('`$page->type?->value` gives the string of the enum, or `null` for a new page without a type.')
            ->hint('`@selected(old(\'type\', $page->type?->value) === $type->value)` marks the right option.')
            ->code(<<<'PHP'
                <x-form-field
                    name="published_at"
                    label="Publish date (leave empty to keep a draft)"
                    type="datetime-local"
                    :value="old('published_at', $page->published_at?->format('Y-m-d\TH:i'))"
                />

                <x-editor name="body" label="Text" :value="old('body', $page->body)" />
                PHP)
            ->tryIt(<<<'TEXT'
                Log in as staff and click "Pages and posts". Write a post with a bold word and a list. Set the publish date to tomorrow, and save. The list shows "Scheduled".

                Edit the post, set the date to one hour ago, and save. The list shows "Published", and the edit page has a "View on the site" link.
                TEXT)
            ->commit('Add the page admin views')
            ->docs('https://laravel.com/docs/blade#anonymous-components', 'Laravel docs: Anonymous components')
            ->check(Check::all(
                Check::view('admin.pages.index'),
                Check::fileContains('resources/views/admin/pages/form.blade.php', '<x-editor'),
            )),

        Step::make('Add the public routes')
            ->explain(<<<'TEXT'
                Add three routes under the Explore heading in `routes/web.php`:

                - `/news` to `NewsController@index`, named `news.index`.
                - `/news/{page:slug}` to `NewsController@show`, named `news.show`.
                - `/pages/{page:slug}` to `PageController@show`, named `pages.show`.

                `{page:slug}` tells Laravel to find the `Page` by its slug. If no page has that slug, Laravel shows a 404 page.

                Route model binding does not know about types or dates. So each `show()` checks the rest itself, with `abort_unless()`. `news.show` needs a published post. `pages.show` needs a published page. A draft or a scheduled post is "Not found" for visitors, not "Forbidden". A 403 tells a visitor that the post exists.

                In `NewsController@index`, use the scopes: `Page::posts()->published()->latest('published_at')->paginate(10)`.
                TEXT)
            ->think('Why is the scheduled post a 404 and not a 403? What does a 403 tell a curious visitor?')
            ->hint('`php artisan make:controller NewsController` and `php artisan make:controller PageController` make the two controllers.')
            ->hint('`abort_unless($page->type === PageType::Post && $page->isPublished(), 404)` stops the request unless both are true.')
            ->code(<<<'PHP'
                public function index(): View
                {
                    return view('news.index', [
                        'posts' => Page::posts()->published()->latest('published_at')->paginate(10),
                    ]);
                }

                public function show(Page $page): View
                {
                    abort_unless($page->type === PageType::Post && $page->isPublished(), 404);

                    return view('pages.show', [
                        'page' => $page,
                    ]);
                }
                PHP)
            ->tryIt('Run `php artisan route:list --path=news`. You see `news.index` and `news.show`.')
            ->commit('Add the news and page routes')
            ->docs('https://laravel.com/docs/routing#customizing-the-default-key-name', 'Laravel docs: Customizing the key')
            ->docs('https://laravel.com/docs/helpers#method-abort-unless', 'Laravel docs: abort_unless')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/3', 'Laravel From Scratch, episode 3: Routing 101')
            ->check(Check::route('news.index', 'news.show', 'pages.show')),

        Step::make('Show the news and the pages to visitors')
            ->explain(<<<'TEXT'
                Create `resources/views/news/index.blade.php`. It shows each post with its date, its title as a link and its summary. When there are no posts, say so. Under the list, `{{ $posts->links() }}` shows the page links.

                The page links use Tailwind classes from a view inside Laravel. Tailwind only makes the classes that it finds in your files. So add this line to `resources/css/app.css`, under the other `@source` lines: `@source '../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php';`. Then run `npm run build`.

                Create `resources/views/pages/show.blade.php` for both pages and posts. A post also shows its date and a link back to News. Show the body with `{!! $page->body !!}` in a `<div class="rich-text">`. This is allowed only because `body` has the `SafeHtml` cast.

                Last, add a "News" link to the Explore menu in `resources/views/layouts/site.blade.php`. Pages get no menu link. Staff link to them from other pages and posts.
                TEXT)
            ->think('The news view uses `$posts->links()`. How does the link to page 2 know the current URL?')
            ->hint('`$post->published_at?->format(\'F j, Y\')` shows a date such as "November 2, 2026".')
            ->hint('Add `[\'label\' => \'News\', \'route\' => \'news.index\', \'description\' => \'Stories and science from the centre.\']` to the Explore list.')
            ->code(<<<'PHP'
                @if ($page->body)
                    {{-- The SafeHtml cast cleaned this HTML when it was saved. --}}
                    <div class="rich-text leading-7">{!! $page->body !!}</div>
                @endif
                PHP)
            ->tryIt(<<<'TEXT'
                Run `php artisan serve` and open http://localhost:8000/news. You see the published posts from the seeder, but not the scheduled one. Click a post. You see its date and its formatted text.

                Open http://localhost:8000/pages/volunteer. You see the page. Open http://localhost:8000/news/volunteer. You see "Not found", because a page is not a post.
                TEXT)
            ->commit('Show news and pages to visitors')
            ->docs('https://laravel.com/docs/pagination#displaying-pagination-results', 'Laravel docs: Displaying pagination results')
            ->docs('https://laravel.com/docs/pagination#tailwind', 'Laravel docs: Pagination with Tailwind')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/5', 'Laravel From Scratch, episode 5: Pass Data to Views')
            ->check(Check::all(
                Check::view('news.index'),
                Check::view('pages.show'),
                Check::fileContains('resources/views/layouts/site.blade.php', "'news.index'"),
            )),

        Step::make('Run the checks and open your pull request')
            ->run('php artisan guide:verify pages-posts')
            ->explain(<<<'TEXT'
                Run the acceptance test with `guide:verify`. Then run `composer ci:check`. It runs Pint, PHPStan and every test, as GitHub does on your pull request.

                PHPStan reads the docblock of `Page`. Check that it lists every column with the right type: `PageType $type` and `Carbon|null $published_at`.

                In your pull request, tell reviewers to run `php artisan migrate:fresh --seed` and `npm run build`. The seeder adds example pages and posts.
                TEXT)
            ->think('The test travels four days into the future. Why is that better than a test that sets the date to yesterday?')
            ->tryIt('Run `composer ci:check`. Pint, PHPStan and all the tests pass.')
            ->commit('Fix the checks for pages and posts')
            ->check(Check::testPassed('PagesPostsTest')),
    ]);
