<?php

use Illuminate\Support\Facades\Schema;
use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('program-categories')
    ->title('Program categories')
    ->section(Section::Catalog)
    ->level(3)
    ->needs(['programs-admin'])
    ->summary('Staff manage categories, such as Camps, Clubs and Homeschool. Today the kinds of program are a fixed list in the ProgramType enum. After this feature, staff add a new kind without a developer.')
    ->teaches(['Data migrations', 'Foreign keys', 'belongsTo and hasMany', 'Factory states', 'Resource controllers', 'Refactoring'])
    ->test('ProgramCategoriesTest.php')
    ->intro(<<<'TEXT'
        Open `app/Enums/ProgramType.php`. Every program has one of five types: camp, club, QUARKS, party or BIG Night Out. The list is in the code. So when the centre starts homeschool classes, a developer must add a case, deploy the site, and update every `match` that uses the enum. Staff cannot do it.

        In this feature, you move the types out of the code and into the database. A new `Category` model holds the kinds of program. Each program belongs to one category. Staff add, rename, order and delete categories on an admin page. Then a new kind of program is data, not code.

        This is a refactor: you change how the code works, but the site must keep working. The programs on your machine already have a type, and they must not lose it. So you write a migration that moves each program from its type to a category. Then you remove the enum and fix every place that used it. The existing tests help you here: when they pass again, you did not break the catalog.

        Keep this rule in mind while you work. The code may branch on a value only if the code needs that value. Nothing in the code needs to know that a category is called "Camps". So the category is data that staff control.
        TEXT)
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/8', 'Laravel From Scratch, episode 8: Databases, Migrations, and Eloquent')
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/16', 'Laravel From Scratch, episode 16: Eloquent Relationships')
    ->docs('https://laravel.com/docs/migrations#foreign-key-constraints', 'Laravel docs: Foreign key constraints')
    ->docs('https://laravel.com/docs/eloquent-relationships#one-to-many', 'Laravel docs: One to many relationships')
    ->reflect([
        'The migration copies the five type names into its own array. Why does it not use the `ProgramType` enum?',
        'The code never checks the name of a category. Why is this important? Find one place where you could be tempted to check it.',
        'A staff member deletes a category by mistake. What protects the programs in it? Name both protections.',
        'Which step was the hardest? What would help the next contributor? Open an issue or a pull request in blsc-guide to improve that step.',
    ])
    ->steps([
        Step::make('Claim the feature and copy in its test')
            ->run('php artisan guide:start program-categories')
            ->explain(<<<'TEXT'
                You start with the test. Run the command below. It copies `tests/Feature/ProgramCategoriesTest.php` into the app.

                Read the whole test before you write code. It tells you the names that the code must use: the `Category` model, the `admin.categories.*` routes, the `category_id` field and the `programs()` relationship. It also tells you the rules, such as "a category with programs cannot be deleted".

                Look at the last rule, too. The `/clubs` page must still work after the refactor. That rule protects the visitors while you change the code.
                TEXT)
            ->think('The test never mentions `ProgramType`. Why not?')
            ->tryIt('Run `php artisan test tests/Feature/ProgramCategoriesTest.php`. All the rules fail, because the `Category` model does not exist yet.')
            ->commit('Add the program categories acceptance test')
            ->docs('https://laravel.com/docs/http-tests', 'Laravel docs: HTTP tests')
            ->check(Check::fileExists('tests/Feature/ProgramCategoriesTest.php')),

        Step::make('Create the Category model, then add its columns')
            ->run('php artisan make:model Category -mf')
            ->explain(<<<'TEXT'
                Run the command below. It makes the `Category` model, a migration and a factory.

                A category needs four columns. `name` is what visitors see, such as "Camps". `slug` is the web address, such as `camps`, and it must be unique. `summary` is one optional sentence for the category page. `position` sets the order on the site. Staff choose the order, so it is a number and not the alphabet.

                Add the columns to the new migration, then run `php artisan migrate`.
                TEXT)
            ->think('Why does `position` need a default value?')
            ->hint('Look at the `programs` migration in `database/migrations`. It has a `name`, a unique `slug` and a nullable `description`.')
            ->hint('`unsignedSmallInteger(\'position\')->default(0)` stores a small number that is never negative.')
            ->code(<<<'PHP'
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('summary')->nullable();
                $table->unsignedSmallInteger('position')->default(0);
                $table->timestamps();
                PHP)
            ->tryIt('Run `php artisan migrate`. Then, in `php artisan tinker`, run `Schema::getColumnListing(\'categories\')`. You see your columns.')
            ->commit('Add the Category model and table')
            ->docs('https://laravel.com/docs/migrations#generating-migrations', 'Laravel docs: Generating migrations')
            ->check(Check::table('categories', ['name', 'slug', 'summary', 'position'])),

        Step::make('Fill in the model and the factory')
            ->explain(<<<'TEXT'
                Open `app/Models/Category.php`. Use `app/Models/Program.php` as your example: it has the same parts.

                Add `name`, `slug`, `summary` and `position` to `#[Fillable]`. Add a `programs(): HasMany` relationship. Add an `ordered` scope that sorts by `position` and then by `name`. Every list of categories uses this order, so the rule lives in one place.

                Then open `database/factories/CategoryFactory.php`. Make a plural name, such as "Robots", and build the slug from it with `Str::slug()`. Set `position` to `0`. The tests make many categories, so the name must be unique.
                TEXT)
            ->think('Two categories have the same position. Which one comes first? Why is a second sort column useful?')
            ->hint('`Program` has a `published` scope with the `#[Scope]` attribute. Write `ordered` the same way.')
            ->hint('In the factory, `fake()->unique()->word()` gives a different word each time. `Str::plural()` and `Str::title()` turn "robot" into "Robots".')
            ->code(<<<'PHP'
                /**
                 * @return HasMany<Program, $this>
                 */
                public function programs(): HasMany
                {
                    return $this->hasMany(Program::class);
                }

                /**
                 * Sort categories in the order that staff chose.
                 *
                 * @param  Builder<Category>  $query
                 */
                #[Scope]
                protected function ordered(Builder $query): void
                {
                    $query->orderBy('position')->orderBy('name');
                }
                PHP)
            ->tryIt('In `php artisan tinker`, run `App\Models\Category::factory()->count(3)->make()->pluck(\'slug\')`. You see three different slugs.')
            ->commit('Fill in the Category model and factory')
            ->docs('https://laravel.com/docs/eloquent#local-scopes', 'Laravel docs: Local scopes')
            ->docs('https://laravel.com/docs/eloquent-factories#factory-states', 'Laravel docs: Factory states')
            ->check(Check::all(
                Check::fileContains('app/Models/Category.php', 'function programs'),
                Check::fileContains('database/factories/CategoryFactory.php', "'slug'"),
            )),

        Step::make('Move every program from its type to a category')
            ->run('php artisan make:migration add_category_id_to_programs_table')
            ->explain(<<<'TEXT'
                This is the most important step. The programs on your machine, and on every other contributor's machine, have a `type`. After this migration, they must have a `category_id`, and no program may lose its kind.

                A migration can change data, not only columns. Do it in three parts in `up()`:

                1. Add a nullable `category_id` foreign key to `programs`. It is nullable for a moment, because the existing programs have no category yet.
                2. For each type that the programs use, insert one category and set `category_id` on the programs of that type.
                3. Drop the `type` column. Drop its index first, because SQLite cannot drop a column that has an index.

                Use the query builder (`DB::table()`), not the models, and copy the names into an array in the migration. The migration must work in a year, after the enum is gone and the models have changed. A migration that uses app classes breaks when those classes change.

                Create a category only for a type that programs use. On an empty database, the migration then creates nothing, and the seeder can create the categories without a clash of slugs.

                Write `down()` too. It adds `type` back, copies the category slugs back to types, and drops the foreign key. Then a contributor can roll back your migration.
                TEXT)
            ->think('The foreign key uses `restrictOnDelete()`. What happens in the database when someone deletes a category that still has programs?')
            ->hint('`DB::table(\'programs\')->distinct()->pluck(\'type\')` gives each type that is in use, once.')
            ->hint('`insertGetId()` inserts a row and returns its ID. Then `DB::table(\'programs\')->where(\'type\', $type)->update([...])` moves those programs.')
            ->hint('Give the array a slug and a name for each old type, such as `\'big_night_out\' => [\'BIG Night Out\', \'big-night-out\']`. For an unknown type, fall back to `ucfirst($type)` and the type itself.')
            ->code(<<<'PHP'
                Schema::table('programs', function (Blueprint $table) {
                    $table->foreignId('category_id')->nullable()->after('id')->constrained()->restrictOnDelete();
                });

                // Move each program from its type to a category. Only types in use get a category.
                foreach (DB::table('programs')->distinct()->pluck('type') as $position => $type) {
                    [$name, $slug] = $this->types[$type] ?? [ucfirst($type), $type];

                    $categoryId = DB::table('categories')->insertGetId([
                        'name' => $name,
                        'slug' => $slug,
                        'position' => $position,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    DB::table('programs')->where('type', $type)->update(['category_id' => $categoryId]);
                }

                Schema::table('programs', function (Blueprint $table) {
                    $table->dropIndex(['type']);
                    $table->dropColumn('type');
                });
                PHP)
            ->tryIt(<<<'TEXT'
                Before you migrate, run `php artisan tinker` and then `DB::table('programs')->pluck('type', 'name')`. Note the type of each program.

                Run `php artisan migrate`. In tinker, run `DB::table('programs')->join('categories', 'categories.id', '=', 'programs.category_id')->pluck('categories.name', 'programs.name')`. Each program is in the category of its old type.

                Then prove that `down()` works. Run `php artisan migrate:rollback --step=1`, check that the types are back, and run `php artisan migrate` again.
                TEXT)
            ->commit('Move programs from types to categories')
            ->docs('https://laravel.com/docs/migrations#foreign-key-constraints', 'Laravel docs: Foreign key constraints')
            ->docs('https://laravel.com/docs/migrations#dropping-indexes', 'Laravel docs: Dropping indexes')
            ->docs('https://laravel.com/docs/queries#update-statements', 'Laravel docs: Update statements')
            ->docs('https://laravel.com/docs/migrations#rolling-back-migrations', 'Laravel docs: Rolling back migrations')
            ->check(Check::all(
                Check::table('programs', ['category_id']),
                fn (): bool => ! Schema::hasColumn('programs', 'type'),
            )),

        Step::make('Connect programs to their category')
            ->explain(<<<'TEXT'
                The database is ready. Now the `Program` model must know about it.

                Add a `category(): BelongsTo` relationship. Replace `type` with `category_id` in `#[Fillable]`, and remove the `type` cast. Update the docblock at the top: remove `$type`, add `$category_id`, and add `@property-read Category $category`. PHPStan reads the docblock, so it must match the columns.
                TEXT)
            ->think('`Category` has `programs(): HasMany`, and `Program` has `category(): BelongsTo`. Which table holds the foreign key? How does that decide which side is "belongs to"?')
            ->hint('Look at `ProgramSession::program()`. A session belongs to a program in the same way.')
            ->code(<<<'PHP'
                /**
                 * @return BelongsTo<Category, $this>
                 */
                public function category(): BelongsTo
                {
                    return $this->belongsTo(Category::class);
                }
                PHP)
            ->tryIt('In `php artisan tinker`, run `App\Models\Program::first()->category->name`. You see the category of the first program.')
            ->commit('Connect programs to categories')
            ->docs('https://laravel.com/docs/eloquent-relationships#one-to-many-inverse', 'Laravel docs: One to many (inverse)')
            ->check(Check::fileContains('app/Models/Program.php', 'function category')),

        Step::make('Update the program factory and the seeder')
            ->explain(<<<'TEXT'
                The factory and the seeder still use `ProgramType`. Fix them now, so the tests and `migrate:fresh --seed` work again.

                In `ProgramFactory`, set `category_id` to `Category::factory()`. Laravel makes a new category for each program unless the test gives one.

                The tests use the states `camp()` and `club()`. Keep them, so the old tests still read well. Add one general state, `inCategory(string $slug)`, that finds or creates the category with that slug. Then `camp()` and `club()` call `inCategory()` with `camps` and `clubs`. Any new category works with the general state, with no new code.

                In `ProgramSeeder`, create the categories first, in the order that you want on the site. Then create each program through its category: `$category->programs()->create([...])`.
                TEXT)
            ->think('Why is `inCategory()` better than one state for each category?')
            ->hint('`Category::firstOrCreate([\'slug\' => $slug], [\'name\' => Str::headline($slug)])` returns the category with that slug, and creates it if it does not exist.')
            ->hint('In the seeder, give each program a `category` key with a slug. Look up the category, then `unset()` the key before you create the program.')
            ->code(<<<'PHP'
                /**
                 * Put the program in the category with this slug. Create the category if it does not exist.
                 */
                public function inCategory(string $slug): static
                {
                    return $this->state(fn () => [
                        'category_id' => Category::firstOrCreate(['slug' => $slug], ['name' => Str::headline($slug)]),
                    ]);
                }

                public function camp(): static
                {
                    return $this->inCategory('camps');
                }
                PHP)
            ->tryIt('Run `php artisan migrate:fresh --seed`. Then, in tinker, run `App\Models\Category::ordered()->withCount(\'programs\')->get([\'name\'])`. You see each category with its number of programs.')
            ->commit('Use categories in the program factory and seeder')
            ->docs('https://laravel.com/docs/eloquent-factories#belongs-to-relationships', 'Laravel docs: Belongs to relationships in factories')
            ->docs('https://laravel.com/docs/seeding#writing-seeders', 'Laravel docs: Writing seeders')
            ->check(Check::all(
                Check::fileContains('database/factories/ProgramFactory.php', 'Category::'),
                Check::fileContains('database/seeders/ProgramSeeder.php', 'Category::'),
            )),

        Step::make('Choose a category in the program admin')
            ->explain(<<<'TEXT'
                Staff choose the type of a program in a select box today. Change it to a list of categories.

                In `ProgramRequest`, replace the `type` rule. `category_id` is required and must exist in the `categories` table. Without the `exists` rule, a changed form could send any number.

                In `Admin\ProgramController`, pass `Category::ordered()->get()` to the `create` and `edit` views. Remove the default type from `create()`. Load `category` in `index()` and stop sorting by type.

                In `resources/views/admin/programs/form.blade.php`, loop over `$categories` and use `category_id` as the field name. In the list view, show `$program->category->name`.
                TEXT)
            ->think('The list page shows the category of each program. Without `with(\'category\')`, how many queries does a list of 20 programs make?')
            ->hint('`Rule::exists(\'categories\', \'id\')` checks that a row with this ID exists.')
            ->hint('Old input is a string, but the category ID is an integer. Compare them with `(int) old(\'category_id\', $program->category_id) === $category->id`.')
            ->code(<<<'PHP'
                'category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
                PHP)
            ->tryIt('Log in as staff, open Programs, and edit a program. Change its category and save. The list shows the new category.')
            ->commit('Choose a category in the program admin')
            ->docs('https://laravel.com/docs/validation#rule-exists', 'Laravel docs: The exists rule')
            ->docs('https://laravel.com/docs/eloquent-relationships#eager-loading', 'Laravel docs: Eager loading')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/12', 'Laravel From Scratch, episode 12: Form Request Classes')
            ->check(Check::fileContains('app/Http/Requests/ProgramRequest.php', "Rule::exists('categories'")),

        Step::make('Show programs by category on the public site')
            ->explain(<<<'TEXT'
                The `/camps` and `/clubs` routes pass a type to `ProgramController::index()`. Change them to pass a category slug: `->defaults('category', 'camps')`.

                In `index()`, find the category by its slug with `firstOrFail()`. If staff delete or rename the slug, the page shows 404 instead of an error. Then list the published programs of that category through `$category->programs()`.

                In `programs/index.blade.php`, use `$category->name` for the title, and show `$category->summary` when it has one. In `programs/show.blade.php`, show `$program->category->name` in the badge.

                The routes still name two categories. That is all right for now. The Category pages feature gives every category its own page.
                TEXT)
            ->think('Staff change the slug of "Camps" to `science-camps`. What does `/camps` show? Is that a bug in the code, or a decision for staff?')
            ->hint('`Category::where(\'slug\', $category)->firstOrFail()` returns the category, or stops with a 404 page.')
            ->code(<<<'PHP'
                public function index(string $category): View
                {
                    $category = Category::where('slug', $category)->firstOrFail();

                    $programs = $category->programs()
                        ->published()
                        ->with('upcomingSessions')
                        ->orderBy('name')
                        ->get();

                    return view('programs.index', [
                        'category' => $category,
                        'programs' => $programs,
                    ]);
                }
                PHP)
            ->tryIt('Run `php artisan serve` and open http://localhost:8000/clubs and http://localhost:8000/camps. You see the same programs as before. Open a program. The badge shows its category.')
            ->commit('Show programs by category')
            ->docs('https://laravel.com/docs/eloquent#not-found-exceptions', 'Laravel docs: Not found exceptions')
            ->check(Check::fileContains('routes/web.php', "defaults('category'")),

        Step::make('Remove the enum and fix the old tests')
            ->explain(<<<'TEXT'
                Delete `app/Enums/ProgramType.php`. Then search the whole project for `ProgramType`. Your editor can do it, or run `grep -rn ProgramType app database resources routes tests`.

                The old tests in `tests/Feature/Admin/ProgramManagementTest.php` send a `type`. Change them to send a `category_id` from a factory category.

                Now run all the tests. One catalog test fails: "the camps page explains when nothing is listed". The test database is empty, so there is no `camps` category, and the page is 404. That is correct now. A category is data, so the test must create it first. Add `Category::factory()->create(['name' => 'Camps', 'slug' => 'camps'])` at the start of the test.

                Only change an old test when the behaviour changed on purpose. Here it did: the kinds of program moved from the code into the database.
                TEXT)
            ->think('Why is it dangerous to change an old test until it passes, without asking why it failed?')
            ->tryIt('Run `grep -rn ProgramType app database resources routes tests`. You see no results. Then run `php artisan test`. All the old tests pass.')
            ->commit('Remove the ProgramType enum')
            ->check(fn (): bool => ! is_file(base_path('app/Enums/ProgramType.php'))),

        Step::make('Add the routes, request and controller for staff')
            ->run('php artisan make:controller Admin/CategoryController --resource --model=Category')
            ->explain(<<<'TEXT'
                Staff need pages to manage categories. Copy the pattern of the program admin: a resource controller, a form request and four views.

                Add `Route::resource('categories', Admin\CategoryController::class)->except('show')` in the staff group in `routes/web.php`. The group already checks that the user is logged in and is staff.

                Create `CategoryRequest` with `php artisan make:request CategoryRequest`. Copy `authorize()` and `prepareForValidation()` from `ProgramRequest`, so staff can leave the slug empty. The slug must be unique, but a category may keep its own slug when staff save it.

                In the controller, `index()` lists the categories with `ordered()` and `withCount('programs')`. In `create()`, suggest the next free position with `Category::max('position') + 1`.

                `destroy()` must refuse a category that has programs. The foreign key already refuses it in the database, but then staff see an error page. So check first, and send staff back with a clear message: `back()->withErrors(['category' => 'Move its programs to another category first.'])`.
                TEXT)
            ->think('The database already protects the programs with `restrictOnDelete()`. Why does the controller check again?')
            ->hint('Look at `ProgramRequest::rules()`. `Rule::unique(\'categories\', \'slug\')->ignore($category)` allows the category to keep its own slug.')
            ->hint('`$category->programs()->exists()` asks the database if at least one program is in the category, without loading them.')
            ->code(<<<'PHP'
                public function destroy(Category $category): RedirectResponse
                {
                    if ($category->programs()->exists()) {
                        return back()->withErrors(['category' => 'Move its programs to another category first.']);
                    }

                    $category->delete();

                    return to_route('admin.categories.index')->with('status', 'Category deleted.');
                }
                PHP)
            ->tryIt('Run `php artisan route:list --name=admin.categories`. You see six routes: index, create, store, edit, update and destroy.')
            ->commit('Add the category admin controller')
            ->docs('https://laravel.com/docs/controllers#resource-controllers', 'Laravel docs: Resource controllers')
            ->docs('https://laravel.com/docs/validation#rule-unique', 'Laravel docs: The unique rule')
            ->docs('https://laravel.com/docs/eloquent-relationships#counting-related-models', 'Laravel docs: Counting related models')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/10', 'Laravel From Scratch, episode 10: Controllers')
            ->check(Check::route('admin.categories.index', 'admin.categories.store', 'admin.categories.destroy')),

        Step::make('Build the staff pages and the menu link')
            ->explain(<<<'TEXT'
                Create four views in `resources/views/admin/categories`: `index`, `create`, `edit` and `form`. Copy the program views and change what is different. The list shows the position, the name, the web address and the number of programs.

                On the edit page, show the delete button only when the category is empty. When it has programs, explain what staff must do first. Show the `category` error from `destroy()` with `@error('category')`.

                Last, add a "Categories" link under "Programs" in the staff menu in `resources/views/layouts/app.blade.php`.
                TEXT)
            ->think('The edit page hides the delete button when a category has programs. Is the check in the controller still necessary? Why?')
            ->hint('Load the count on the edit page with `$category->loadCount(\'programs\')` in the controller. The view then reads `$category->programs_count`.')
            ->hint('The menu link for Programs uses `request()->routeIs(\'admin.programs.*\')`. Use `admin.categories.*` for yours.')
            ->tryIt(<<<'TEXT'
                Log in as staff and click Categories. Create "Homeschool Classes" with position 5. Edit a program and move it into the new category.

                Then try to delete the new category. The page tells you to move its program first. Move the program back, and delete the category.
                TEXT)
            ->commit('Add the category admin pages')
            ->docs('https://laravel.com/docs/validation#quick-displaying-the-validation-errors', 'Laravel docs: Displaying the validation errors')
            ->check(Check::view('admin.categories.index')),

        Step::make('Run the checks and open your pull request')
            ->run('php artisan guide:verify program-categories')
            ->explain(<<<'TEXT'
                Run the acceptance test with `guide:verify`. Then run `composer ci:check`. It runs Pint, PHPStan and every test, as GitHub does on your pull request.

                PHPStan finds places that still expect the old `type`, such as a docblock. Fix each one.

                In your pull request, tell reviewers that the pull request has a data migration. Each reviewer must run `php artisan migrate` after they check out your branch.
                TEXT)
            ->think('A reviewer checks out your branch but does not run `migrate`. What do they see on the Clubs page?')
            ->tryIt('Run `composer ci:check`. Pint, PHPStan and all the tests pass.')
            ->commit('Fix the checks for program categories')
            ->check(Check::testPassed('ProgramCategoriesTest')),
    ]);
