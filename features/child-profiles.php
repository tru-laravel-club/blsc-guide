<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('child-profiles')
    ->title('Child profiles')
    ->section(Section::Foundation)
    ->level(2)
    ->needs(['accounts'])
    ->summary('Parents add their children once: name, birthdate and photo consent. Registrations and requests use these profiles later. Collect only what BLSC needs.')
    ->teaches(['Resource controllers', 'Form requests', 'Policies', 'hasMany relationships', 'Dates and ages'])
    ->test('ChildProfilesTest.php')
    ->intro(<<<'TEXT'
        Parents register their children for camps, clubs and parties. Without profiles, they type the same details for each sign-up. In this feature, a parent adds each child once. When you finish, a logged-in parent clicks "My children" in the account sidebar. There, the parent adds, edits and deletes their children.

        These are private details about children. So collect only what the code needs, and protect each profile. The `children` table keeps a small fixed core: a name, a birthdate and photo consent. The code reads each of these. Registrations check the age, and staff check photo consent before they post a photo. Other questions, such as medical notes or allergies, change from season to season. They do not go in this table. Later, staff add them as sensitive child fields in the form builder (see the Saved profile answers feature).

        First, you build the data: a `Child` model with a table, and a relationship between users and children. Then you build the pages with a resource controller, which has one method for each job. A policy makes sure that a parent can only see and change their own children.

        Keep this path in mind while you work. The parent opens `/children/3/edit`. The `auth` and `verified` middleware check that the parent is logged in. Laravel finds child 3 in the database. The controller asks the policy if this parent can change this child. If yes, the view shows the form. If no, the parent sees a 403 page.
        TEXT)
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/16', 'Laravel From Scratch, episode 16: Eloquent Relationships')
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/18', 'Laravel From Scratch, episode 18: Authorization Using Policies')
    ->docs('https://laravel.com/docs/controllers#resource-controllers', 'Laravel docs: Resource controllers')
    ->reflect([
        'Staff want to ask about allergies next year. Where does that question live, and who adds it?',
        'The list page uses the children of the logged-in parent, not `Child::all()`. Why is this important, even with a policy?',
        'Staff cannot open the profile of a child. Why does BLSC want this? What would you change if staff needed access later?',
        'Which step was the hardest? What would help the next contributor? Open an issue or a pull request in blsc-guide to improve that step.',
    ])
    ->steps([
        Step::make('Claim the feature and copy in its test')
            ->run('php artisan guide:start child-profiles')
            ->explain(<<<'TEXT'
                You start with the test. Run the command below. It copies `tests/Feature/ChildProfilesTest.php` into the app.

                Read the whole test before you write code. It tells you the names that the code must use: the `Child` model, the `children.*` routes, the `children()` relationship and the `ageOn()` method. It also tells you the rules, such as "a parent cannot see or change the child of another family".

                Look at what the test does not send. There is no field for medical notes or allergies. The test protects the small core of the table.
                TEXT)
            ->think('Find the rule about `user_id` in the test. What attack does it stop?')
            ->tryIt('Run `php artisan test tests/Feature/ChildProfilesTest.php`. All the rules fail, because the `Child` model does not exist yet.')
            ->commit('Add the child profiles acceptance test')
            ->docs('https://laravel.com/docs/http-tests', 'Laravel docs: HTTP tests')
            ->check(Check::fileExists('tests/Feature/ChildProfilesTest.php')),

        Step::make('Create the Child model, migration and factory')
            ->run('php artisan make:model Child -mf')
            ->explain(<<<'TEXT'
                You start with the data. A model is a PHP class for one database table. The `Child` model needs two more files. A migration makes the table. A factory makes fake children for the tests. One artisan command makes all three files.

                Laravel finds the table name from the class name. It knows irregular English plurals, so Laravel names the table "children", not "childs".
                TEXT)
            ->think('What is the table name for the `Child` model? How does Laravel know it?')
            ->hint('Run `php artisan make:model --help`. Look for the options that also make a migration and a factory.')
            ->tryIt('Look in `database/migrations`. The new file name ends in `create_children_table.php`. In `php artisan tinker`, run `(new App\Models\Child)->getTable()`. You see `"children"`. Type `exit` to leave tinker.')
            ->commit('Add the Child model')
            ->docs('https://laravel.com/docs/eloquent#generating-model-classes', 'Laravel docs: Generating model classes')
            ->docs('https://laravel.com/docs/eloquent#table-names', 'Laravel docs: Table names')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/8', 'Laravel From Scratch, episode 8: Databases, Migrations, and Eloquent')
            ->check(Check::classExists('App\Models\Child')),

        Step::make('Add the columns, then migrate')
            ->run('php artisan migrate')
            ->explain(<<<'TEXT'
                Now you describe the table in the migration. A value is a column only if the code reads it. The code reads three values about a child: the name, the birthdate for age limits, and photo consent. So the table has those three columns and a `user_id`. Data that you do not collect cannot leak.

                `user_id` connects each child to the parent's account. `constrained()` makes the database refuse a `user_id` that does not exist. `cascadeOnDelete()` deletes the children when the parent deletes the account, so no private data stays behind. Photo consent is `false` until the parent says yes.

                Add the four columns, then run `php artisan migrate`.

                Next, open the model. Add `name`, `birthdate` and `photo_consent` to `#[Fillable]`. Do not add `user_id`. The controller takes the parent from the login, never from the form. Then cast `birthdate` to a date and `photo_consent` to a boolean. Give `photo_consent` a default of `false` in `$attributes`, so a new child that you have not saved yet also says "no".
                TEXT)
            ->think('Why does `photo_consent` start as `false` and not `true`?')
            ->hint('`foreignId(\'user_id\')->constrained()` makes the column and a foreign key to the `users` table.')
            ->hint('Look at `app/Models/Program.php` for `#[Fillable]` and `casts()`. Look at `app/Models/User.php` for `$attributes`.')
            ->hint('Use the `date` cast for `birthdate` and the `boolean` cast for `photo_consent`.')
            ->code(<<<'PHP'
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->date('birthdate');
                $table->boolean('photo_consent')->default(false);
                $table->timestamps();
                PHP)
            ->tryIt('In `php artisan tinker`, run `Schema::getColumnListing(\'children\')`. You see your columns, with `id`, `created_at` and `updated_at`.')
            ->commit('Add the children columns')
            ->docs('https://laravel.com/docs/migrations#foreign-key-constraints', 'Laravel docs: Foreign key constraints')
            ->docs('https://laravel.com/docs/migrations#column-modifiers', 'Laravel docs: Column modifiers')
            ->docs('https://laravel.com/docs/eloquent#mass-assignment', 'Laravel docs: Mass assignment')
            ->docs('https://laravel.com/docs/eloquent-mutators#attribute-casting', 'Laravel docs: Attribute casting')
            ->docs('https://laravel.com/docs/eloquent#default-attribute-values', 'Laravel docs: Default attribute values')
            ->check(Check::table('children', ['user_id', 'name', 'birthdate', 'photo_consent'])),

        Step::make('Connect users and children')
            ->explain(<<<'TEXT'
                The table has a `user_id`, but the models do not know about it yet. A relationship tells Eloquent how two models connect.

                One parent has many children. Each child belongs to one parent. Add `children(): HasMany` on `User` and `user(): BelongsTo` on `Child`. Add `@property-read` lines to the docblocks, so PHPStan knows about `$user->children` and `$child->user`.

                Then you can write `$user->children()->create([...])`. Eloquent fills in `user_id` for you. You use this in the controller.

                Last, fill in `database/factories/ChildFactory.php`. Set `user_id` to `User::factory()`, so each fake child gets a fake parent. Give it a first name, a birthdate between 4 and 12 years ago, and `photo_consent` set to `false`. The test uses `Child::factory()->for($parent)` to choose the parent.
                TEXT)
            ->think('Which table has the `user_id` column? Which model gets `hasMany`, and which model gets `belongsTo`?')
            ->hint('Look at `programs()` in `app/Models/Category.php` and `category()` in `app/Models/Program.php`. They are the same pair.')
            ->hint('In `User`, return `$this->hasMany(Child::class)`. In `Child`, return `$this->belongsTo(User::class)`.')
            ->hint('In the factory, `fake()->dateTimeBetween(\'-12 years\', \'-4 years\')` gives a date in that range.')
            ->code(<<<'PHP'
                /**
                 * @return HasMany<Child, $this>
                 */
                public function children(): HasMany
                {
                    return $this->hasMany(Child::class);
                }
                PHP)
            ->tryIt('In `php artisan tinker`, run `App\Models\Child::factory()->create()->user->email`. You see the email of a new fake parent. Then run `App\Models\User::firstWhere(\'email\', \'test@example.com\')->children`. You see an empty collection, `all: []`.')
            ->commit('Connect users and children')
            ->docs('https://laravel.com/docs/eloquent-relationships#one-to-many', 'Laravel docs: One to many')
            ->docs('https://laravel.com/docs/eloquent-relationships#one-to-many-inverse', 'Laravel docs: One to many (inverse)')
            ->docs('https://laravel.com/docs/eloquent-factories#belongs-to-relationships', 'Laravel docs: Belongs to relationships in factories')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/16', 'Laravel From Scratch, episode 16: Eloquent Relationships')
            ->check(Check::all(
                Check::fileContains('app/Models/User.php', 'function children'),
                Check::fileContains('database/factories/ChildFactory.php', "'user_id'"),
            )),

        Step::make('Work out the age of a child on a date')
            ->explain(<<<'TEXT'
                Programs have age limits, such as "Ages 6–9". Later, the Registrations feature checks each child against these limits. Which age does it check? Not the age today. A parent registers in March for a camp in July. The child can have a birthday between those dates. So the check uses the age on the first day of the session.

                Add `ageOn()` to `Child`. It takes a date and returns the age in full years on that date. Other features call this method, so keep its name and its return type.

                The app uses immutable dates. Look at `AppServiceProvider`: `Date::use(CarbonImmutable::class)`. So `today()` and the `date` cast give a `CarbonImmutable`. Type the parameter as `CarbonInterface`. Then the method takes any Carbon date.
                TEXT)
            ->think('A child is born on May 1, 2018. How old is the child on April 30, 2026? On May 1, 2026? Write the answers down before you write code.')
            ->hint('Carbon can count the years between two dates: `$this->birthdate->diffInYears($date)`.')
            ->hint('In Carbon 3, `diffInYears()` returns a float, such as `7.99`. A cast to `int` keeps only the full years.')
            ->code(<<<'PHP'
                /**
                 * Get the age of the child in full years on a date, such as the first day of a session.
                 */
                public function ageOn(CarbonInterface $date): int
                {
                    return (int) $this->birthdate->diffInYears($date);
                }
                PHP)
            ->tryIt(<<<'TEXT'
                In `php artisan tinker`, make a child without saving it:

                `$child = App\Models\Child::factory()->make(['birthdate' => '2018-05-01']);`

                Then run `$child->ageOn(today()->setDate(2026, 4, 30))`. You see `7`. Run `$child->ageOn(today()->setDate(2026, 5, 1))`. You see `8`.
                TEXT)
            ->commit('Add Child::ageOn()')
            ->docs('https://laravel.com/docs/eloquent-mutators#date-casting', 'Laravel docs: Date casting')
            ->docs('https://laravel.com/docs/helpers#method-today', 'Laravel docs: The today() helper')
            ->check(Check::fileContains('app/Models/Child.php', 'function ageOn')),

        Step::make('Create a resource controller and a form request')
            ->run('php artisan make:controller ChildController --resource --model=Child')
            ->explain(<<<'TEXT'
                Now you start the pages. A resource controller has one method for each job on a resource: `index`, `create`, `store`, `show`, `edit`, `update` and `destroy`. Make it with the command below. Parents see a list and an edit form, so they do not need a `show` page. Delete the `show()` method.

                The add form and the edit form send the same fields. So put the rules in one form request: run `php artisan make:request ChildRequest`. A name is required. The birthdate must be a date before today. Photo consent is a boolean.

                A checkbox that is not ticked sends nothing. Copy the idea of `prepareForValidation()` from `ProgramRequest`: merge `$this->boolean('photo_consent')`, so a missing value becomes `false`.

                In `authorize()`, return `true` for any logged-in user. The request does not know about each child. The policy checks that in a later step.

                Fill in the controller. Copy the shape of `app/Http/Controllers/Admin/ProgramController.php`. In `index()`, list only the children of the logged-in parent. In `store()`, create the child through the relationship, so `user_id` comes from the login. After a save or a delete, redirect to the list with a status message.
                TEXT)
            ->think('A parent changes the form and sends `user_id` with the ID of another parent. What does your code do with it?')
            ->hint('In `index()`, use `$request->user()->children()->orderBy(\'name\')->get()`.')
            ->hint('In `store()`, use `$request->user()->children()->create($request->validated())`. `validated()` returns only the fields in your rules.')
            ->hint('A `user_id` from the form is not in your rules, so `validated()` drops it. `#[Fillable]` is a second guard, because it has no `user_id`.')
            ->code(<<<'PHP'
                public function store(ChildRequest $request): RedirectResponse
                {
                    $request->user()->children()->create($request->validated());

                    return to_route('children.index')->with('status', 'Child added.');
                }
                PHP)
            ->commit('Add ChildController and ChildRequest')
            ->docs('https://laravel.com/docs/controllers#actions-handled-by-resource-controllers', 'Laravel docs: Resource controller methods')
            ->docs('https://laravel.com/docs/validation#creating-form-requests', 'Laravel docs: Form requests')
            ->docs('https://laravel.com/docs/validation#preparing-input-for-validation', 'Laravel docs: Preparing input for validation')
            ->docs('https://laravel.com/docs/validation#rule-before', 'Laravel docs: The before rule')
            ->docs('https://laravel.com/docs/eloquent-relationships#the-create-method', 'Laravel docs: The create method on a relationship')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/10', 'Laravel From Scratch, episode 10: Controllers')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/12', 'Laravel From Scratch, episode 12: Form Request Classes')
            ->check(Check::all(
                Check::classExists('App\Http\Controllers\ChildController'),
                Check::classExists('App\Http\Requests\ChildRequest'),
            )),

        Step::make('Add the routes for logged-in parents')
            ->explain(<<<'TEXT'
                The controller is ready, but no URL leads to it yet. `Route::resource()` makes one route for each method of a resource controller. `->except('show')` leaves out the `show` route, because you deleted that method.

                Put it inside the `auth` and `verified` group in `routes/web.php`, next to the dashboard. The `auth` middleware sends a guest to the login page. The `verified` middleware asks for a verified email address. So only logged-in parents reach the controller.
                TEXT)
            ->think('What happens when a guest opens `/children`? Which line of code does that?')
            ->hint('Find `Route::middleware([\'auth\', \'verified\'])->group(...)` in `routes/web.php`.')
            ->hint('The route names come from the first argument: `children.index`, `children.create`, `children.edit` and more.')
            ->code(<<<'PHP'
                Route::resource('children', ChildController::class)->except('show');
                PHP)
            ->tryIt(<<<'TEXT'
                Run `php artisan route:list --name=children`. You see six routes.

                Log out, then open http://localhost:8000/children. Laravel sends you to the login page. Log in as `test@example.com` with the password `password`. Laravel opens `/children`, and you see an error that the view `children.index` is not found. That is correct. You make the views in a later step.
                TEXT)
            ->commit('Add the children routes')
            ->docs('https://laravel.com/docs/controllers#restful-partial-resource-routes', 'Laravel docs: Partial resource routes')
            ->docs('https://laravel.com/docs/authentication#protecting-routes', 'Laravel docs: Protecting routes')
            ->check(Check::route('children.index', 'children.create', 'children.edit')),

        Step::make('Protect each child with a policy')
            ->run('php artisan make:policy ChildPolicy --model=Child')
            ->explain(<<<'TEXT'
                The `auth` middleware checks that a parent is logged in. It does not check whose child it is. Laravel finds a child by the ID in the URL. So a parent can change `/children/3/edit` to `/children/4/edit` and see the details of another family.

                A policy answers the question "can this user do this to this model?". It has one method for each thing that a user can do. Make it with the command below. Laravel finds `ChildPolicy` for the `Child` model by its name.

                A parent can only see and change their own children. In `update()` and `delete()`, return `true` only when the child belongs to the user. Delete the methods that you do not use. Then, in the controller, call `Gate::authorize()` at the start of `edit()`, `update()` and `destroy()`. When the policy says no, Laravel stops with a 403 page.

                Staff do not get access here. So do not add a staff check to the policy. Staff do not need a family's private details on this page.
                TEXT)
            ->think('A parent changes the URL from `/children/3/edit` to `/children/4/edit`. Child 4 belongs to another family. What stops the parent?')
            ->hint('Compare `$user->id` with `$child->user_id`.')
            ->hint('Call `Gate::authorize(\'update\', $child)` in `edit()` and `update()`. Call `Gate::authorize(\'delete\', $child)` in `destroy()`.')
            ->hint('`index()` and `store()` do not need the policy. They use only the children of the logged-in parent.')
            ->code(<<<'PHP'
                public function update(User $user, Child $child): bool
                {
                    return $user->id === $child->user_id;
                }
                PHP)
            ->tryIt(<<<'TEXT'
                In `php artisan tinker`, make a child for a new parent:

                `$child = App\Models\Child::factory()->create();`

                Then run `$child->user->can('update', $child)`. You see `true`. Run `App\Models\User::firstWhere('email', 'staff@example.com')->can('update', $child)`. You see `false`.
                TEXT)
            ->commit('Add ChildPolicy')
            ->docs('https://laravel.com/docs/authorization#creating-policies', 'Laravel docs: Creating policies')
            ->docs('https://laravel.com/docs/authorization#writing-policies', 'Laravel docs: Writing policies')
            ->docs('https://laravel.com/docs/authorization#via-the-gate-facade', 'Laravel docs: The Gate facade')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/18', 'Laravel From Scratch, episode 18: Authorization Using Policies')
            ->check(Check::classExists('App\Policies\ChildPolicy')),

        Step::make('Create the views and add a sidebar link')
            ->explain(<<<'TEXT'
                Now you make the pages that parents see. Use the admin program views as a pattern. They are in `resources/views/admin/programs`. Make `index`, `create` and `edit` views in `resources/views/children`. Put the form fields in one `form` view, and include it in `create` and `edit`. Then the two forms cannot drift apart.

                Use `<x-layouts::app>`, because these pages are in the account area. On the list, show each child with the birthdate and the age today: `$child->ageOn(today())`. When the list is empty, say "You have not added a child yet."

                In the form, use a date field for the birthdate. Use `old()` for each value, so the form keeps the input after a validation error. Put a hidden field with the value `0` before the photo consent checkbox, as the program form does. Then the browser always sends an answer.

                Add "My children" to the Account group in `resources/views/layouts/app.blade.php`.
                TEXT)
            ->think('The form request already turns a missing checkbox into `false`. Is the hidden field still useful? What does each one protect?')
            ->hint('Copy `resources/views/admin/programs`. Change the fields and the route names. Keep `@csrf` in the form and `@method(\'PUT\')` in the edit view.')
            ->hint('For a date input, the value must look like `2018-05-01`. Use `$child->birthdate?->toDateString()`. The `?->` handles a new child that has no birthdate.')
            ->hint('For the sidebar, copy the Profile item. Use `route(\'children.index\')` and `request()->routeIs(\'children.*\')`.')
            ->tryIt(<<<'TEXT'
                Log in as `test@example.com` with the password `password`. Click "My children" in the sidebar. Add a child, change the name, and delete the child. Then save a child with no name. You see an error under the name field.

                Last, add a child and copy the URL of its edit page. Log out, log in as `staff@example.com` with the password `password`, and open that URL. You see a 403 page.
                TEXT)
            ->commit('Add the child profile pages')
            ->docs('https://laravel.com/docs/blade#forms', 'Laravel docs: Forms in Blade')
            ->docs('https://laravel.com/docs/blade#including-subviews', 'Laravel docs: Including subviews')
            ->docs('https://laravel.com/docs/validation#repopulating-forms', 'Laravel docs: Repopulating forms')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/7', 'Laravel From Scratch, episode 7: Forms')
            ->check(Check::fileContains('resources/views/layouts/app.blade.php', "route('children.index')")),

        Step::make('Run the checks and open your pull request')
            ->run('php artisan guide:verify child-profiles')
            ->explain(<<<'TEXT'
                Run the acceptance test with `guide:verify`. It is the spec of this feature. Then run `composer ci:check`. It runs Pint, PHPStan and every test, as GitHub does on your pull request.

                A good test fails when the rule is broken. Prove it for one rule. Remove the `Gate::authorize()` line from `edit()`, and run the test again. The rule "a parent cannot see or change the child of another family" fails. Put the line back.

                Extra: write a test of your own in a new file. For example, test that the edit form shows the saved birthdate. Use `actingAs()`, `get()` and `assertSee()`, as the acceptance test does.
                TEXT)
            ->think('Which rule in the test protects children the most? What would happen on the live site without it?')
            ->tryIt('Run `composer ci:check`. Pint, PHPStan and all the tests pass.')
            ->commit('Fix the checks for child profiles')
            ->docs('https://laravel.com/docs/http-tests#assert-forbidden', 'Laravel docs: assertForbidden')
            ->docs('https://laravel.com/docs/mocking#interacting-with-time', 'Laravel docs: Interacting with time in tests')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/22', 'Laravel From Scratch, episode 22: How to Get Started Testing Your Code')
            ->check(Check::testPassed('ChildProfilesTest')),
    ]);
