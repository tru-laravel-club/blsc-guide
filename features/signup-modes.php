<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('signup-modes')
    ->title('Sign-up modes')
    ->section(Section::Catalog)
    ->level(2)
    ->needs(['programs-admin'])
    ->summary('Each program has a sign-up mode: none, register, apply or request. The code uses the mode, not the category, to decide what a visitor can do.')
    ->teaches(['Backed enums', 'Enum casts', 'Column defaults', 'Enum validation'])
    ->test('SignupModesTest.php')
    ->intro(<<<'TEXT'
        Visitors do different things on different program pages. For a club or a camp, a parent registers a child and pays. For QUARKS, a student sends an application, and staff accept or decline it. For a party, a family sends a request, and staff reply. For a Saturday drop-in event, a visitor reads the page and comes in person.

        So the program page must know what to offer. It is tempting to check the category: "if the category is QUARKS, show Apply". Do not do that. Staff own the categories. They rename them, add new ones, and put different programs in the same category. A one-day drop-in camp and a summer camp are both camps, but only one takes registrations.

        In this feature, each program gets its own sign-up mode. The mode is a `SignupMode` enum with four cases: `None`, `Register`, `Apply` and `Request`. Staff choose the mode in the program form. The program page shows one box that tells the visitor what to do, such as "Drop in during opening hours" or "Applications open soon". Later features turn the box into a button: Applications for `Apply`, Requests for `Request`, and Registrations for `Register`.

        Why is the mode an enum, when the categories are a table? Each mode needs its own code: a registration flow, an application flow, a request flow. Staff cannot add a mode without a developer, because a new mode needs new code anyway. A category needs no code, so it is data.

        Keep this path in mind while you work. A visitor opens the QUARKS page. The controller loads the program. Eloquent reads `apply` from the `signup_mode` column and casts it to `SignupMode::Apply`. The view asks the enum for its text with `callToAction()`. No line of code knows the name "QUARKS".
        TEXT)
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/8', 'Laravel From Scratch, episode 8: Databases, Migrations, and Eloquent')
    ->docs('https://laravel.com/docs/eloquent-mutators#enum-casting', 'Laravel docs: Enum casting')
    ->reflect([
        'Categories are a table, but sign-up modes are an enum. Why? What is the difference?',
        'A new program starts with the mode `none`. Why is that a safer default than `register`?',
        'Which step was the hardest? What would help the next contributor? Open an issue or a pull request in blsc-guide to improve that step.',
    ])
    ->steps([
        Step::make('Claim the feature and copy in its test')
            ->run('php artisan guide:start signup-modes')
            ->explain(<<<'TEXT'
                You start with the test. Run the command below. It copies `tests/Feature/SignupModesTest.php` into the app.

                Read the whole test before you write code. It tells you the names that the code must use: the `SignupMode` enum, its `callToAction()` method, and the `signup_mode` field. It also gives the exact text for each mode.

                Look at the dataset at the top. Pest runs the first rule once for each row: once for each mode. Look at the last rule, too. Two programs in the same category use different modes. That rule proves that the code reads the mode, not the category.
                TEXT)
            ->think('The test sends `apply` as the mode, but it expects `SignupMode::Apply` from the model. What turns the text into an enum case?')
            ->tryIt('Run `php artisan test tests/Feature/SignupModesTest.php`. All the rules fail, because the `SignupMode` enum does not exist yet.')
            ->commit('Add the sign-up modes acceptance test')
            ->docs('https://laravel.com/docs/http-tests', 'Laravel docs: HTTP tests')
            ->check(Check::fileExists('tests/Feature/SignupModesTest.php')),

        Step::make('Create the SignupMode enum')
            ->run('php artisan make:enum App/Enums/SignupMode --string')
            ->explain(<<<'TEXT'
                An enum is a type with a fixed list of values. A backed enum gives each case a value that the database can store, such as `'apply'`. The `--string` option makes a string-backed enum. Write the full namespace `App/Enums/` in the name. Then the file always goes in `app/Enums`, even when that folder does not exist yet.

                Add the four cases: `None`, `Register`, `Apply` and `Request`, with the values `none`, `register`, `apply` and `request`.

                Then add two methods. `label()` returns the name that staff see in the program form, such as "Registration". `callToAction()` returns the text that visitors see on the program page, such as "Registration opens soon". Take the exact texts from the test.

                Put the text in the enum, not in the views. Then the admin form, the program page and later features all use the same words.
                TEXT)
            ->think('A later feature adds a fifth mode. With a `match` in each method, what does PHP do when you forget to add the new case to `callToAction()`?')
            ->hint('Use `match ($this) { self::None => ..., self::Register => ..., ... }` in each method.')
            ->hint('The test file has a `dataset(\'modes\', ...)` with each value and its text.')
            ->code(<<<'PHP'
                enum SignupMode: string
                {
                    case None = 'none';
                    case Register = 'register';
                    case Apply = 'apply';
                    case Request = 'request';

                    public function callToAction(): string
                    {
                        return match ($this) {
                            self::None => 'Drop in during opening hours',
                            self::Register => 'Registration opens soon',
                            self::Apply => 'Applications open soon',
                            self::Request => 'Requests open soon',
                        };
                    }
                }
                PHP)
            ->tryIt('In `php artisan tinker`, run `App\Enums\SignupMode::from(\'apply\')->callToAction()`. You see `"Applications open soon"`. Then run `App\Enums\SignupMode::from(\'lottery\')`. PHP throws a `ValueError`, because `lottery` is not a case.')
            ->commit('Add the SignupMode enum')
            ->docs('https://www.php.net/manual/en/language.enumerations.backed.php', 'PHP manual: Backed enumerations')
            ->docs('https://www.php.net/manual/en/control-structures.match.php', 'PHP manual: match')
            ->check(Check::fileContains('app/Enums/SignupMode.php', 'function callToAction')),

        Step::make('Add the signup_mode column')
            ->run('php artisan make:migration add_signup_mode_to_programs_table')
            ->explain(<<<'TEXT'
                The programs table needs a column for the mode. You change an existing table, so make a new migration. Do not edit the old `create_programs_table` migration. Other contributors already ran it, and Laravel does not run a migration twice.

                Use `Schema::table()`, not `Schema::create()`. Add a string column `signup_mode` with the default `none`. The programs that exist already get `none`, so no program offers a sign-up that staff did not choose.

                Store the value as a string, not as a database enum type. Then a new mode needs no change to the column.

                In `down()`, drop the column. Then run `php artisan migrate`.
                TEXT)
            ->think('Your database already has programs. What value do they get in the new column? Why does the column need a default?')
            ->hint('`$table->string(\'signup_mode\')->default(\'none\')->after(\'category_id\')`. `after()` only sets the column order in the table.')
            ->code(<<<'PHP'
                public function up(): void
                {
                    Schema::table('programs', function (Blueprint $table) {
                        $table->string('signup_mode')->default('none')->after('category_id');
                    });
                }

                public function down(): void
                {
                    Schema::table('programs', function (Blueprint $table) {
                        $table->dropColumn('signup_mode');
                    });
                }
                PHP)
            ->tryIt('Run `php artisan migrate`. Then, in `php artisan tinker`, run `DB::table(\'programs\')->pluck(\'signup_mode\', \'name\')`. Every program has `none`.')
            ->commit('Add the signup_mode column to programs')
            ->docs('https://laravel.com/docs/migrations#creating-columns', 'Laravel docs: Creating columns')
            ->docs('https://laravel.com/docs/migrations#column-modifiers', 'Laravel docs: Column modifiers')
            ->check(Check::table('programs', ['signup_mode'])),

        Step::make('Cast the column to the enum')
            ->explain(<<<'TEXT'
                The database stores text, such as `apply`. The code wants `SignupMode::Apply`. An enum cast converts between the two. When Eloquent reads the row, it makes the enum case. When Eloquent saves, it stores the value.

                Open `app/Models/Program.php`. Add `signup_mode` to `#[Fillable]`, and add `'signup_mode' => SignupMode::class` to `casts()`. Add `@property SignupMode $signup_mode` to the docblock, so PHPStan knows the type.

                The database default only works when the row is saved. A `new Program` in the create form has no mode yet. So also add `'signup_mode' => 'none'` to `$attributes`. Then a new program in memory has the same default as the database.
                TEXT)
            ->think('Without the `$attributes` default, what is `(new Program)->signup_mode`? What happens in the create form when it calls `->value` on it?')
            ->hint('Look at `$attributes` in `app/Models/User.php`. It gives `is_staff` a default in the same way.')
            ->hint('Use the raw value `\'none\'` in `$attributes`, not the enum case. The cast turns it into `SignupMode::None` when you read it.')
            ->code(<<<'PHP'
                protected $attributes = [
                    'signup_mode' => 'none',
                ];

                protected function casts(): array
                {
                    return [
                        'is_published' => 'boolean',
                        'signup_mode' => SignupMode::class,
                    ];
                }
                PHP)
            ->tryIt('In `php artisan tinker`, run `(new App\Models\Program)->signup_mode`. You see the `None` case of `App\Enums\SignupMode`, not the text `none`.')
            ->commit('Cast the sign-up mode to SignupMode')
            ->docs('https://laravel.com/docs/eloquent-mutators#enum-casting', 'Laravel docs: Enum casting')
            ->docs('https://laravel.com/docs/eloquent#default-attribute-values', 'Laravel docs: Default attribute values')
            ->check(Check::fileContains('app/Models/Program.php', 'SignupMode::class')),

        Step::make('Choose the mode in the program admin')
            ->explain(<<<'TEXT'
                Staff choose the mode in the program form. Add a select with the label "How visitors sign up". Loop over `SignupMode::cases()`. Use the value of each case as the option value, and `label()` as the text.

                Then validate it in `ProgramRequest`. `Rule::enum(SignupMode::class)` accepts only the values of the enum. A changed form cannot send `lottery`.

                The rule also has `sometimes`. Look at the old tests in `tests/Feature/Admin/ProgramManagementTest.php`. They save programs without a mode. With `sometimes`, a request without the field is still valid, and the database default fills it in. The form always sends the field, so staff always choose.

                Last, give the seeded programs a mode in `ProgramSeeder`. Clubs and camps use `register`. QUARKS uses `apply`.
                TEXT)
            ->think('The rule is `[\'sometimes\', \'required\', Rule::enum(...)]`. What does each part do when the field is missing? When it is empty? When it is `lottery`?')
            ->hint('Copy the category select in `resources/views/admin/programs/form.blade.php`. Change the field name and the loop.')
            ->hint('Compare strings in `@selected()`: `old(\'signup_mode\', $program->signup_mode->value) === $mode->value`.')
            ->code(<<<'PHP'
                'signup_mode' => ['sometimes', 'required', Rule::enum(SignupMode::class)],
                PHP)
            ->tryIt('Run `php artisan migrate:fresh --seed`. Log in as `staff@example.com` with the password `password`. Open Programs and edit QUARKS. The select shows "Application". Change it to "Request" and save. The form keeps your choice.')
            ->commit('Choose the sign-up mode in the program form')
            ->docs('https://laravel.com/docs/validation#rule-enum', 'Laravel docs: The enum rule')
            ->docs('https://laravel.com/docs/validation#validating-when-present', 'Laravel docs: Validating when present')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/12', 'Laravel From Scratch, episode 12: Form Request Classes')
            ->check(Check::fileContains('app/Http/Requests/ProgramRequest.php', 'Rule::enum(SignupMode::class)')),

        Step::make('Show the mode on the program page')
            ->explain(<<<'TEXT'
                Now the visitor sees the mode. Open `resources/views/programs/show.blade.php`. Above "Upcoming dates", add one box with `$program->signup_mode->callToAction()`.

                The view asks the enum for the text. It does not check the mode itself, and it never checks the category. Later features change this box for one mode at a time.
                TEXT)
            ->think('The Applications feature turns the box into an "Apply" button for `Apply` programs. Which file does it change? Does it need to know about QUARKS?')
            ->hint('Look at `resources/views/components/admin/status.blade.php`. It uses `<april:alert>` with an icon and a title.')
            ->code(<<<'PHP'
                <april:alert>
                    <slot:icon><x-lucide-info class="size-4" /></slot:icon>
                    <slot:title>{{ $program->signup_mode->callToAction() }}</slot:title>
                </april:alert>
                PHP)
            ->tryIt('Run `php artisan serve`. Open the Clubs page and click a club. The box says "Registration opens soon". Open the BIG Night Out program. Its address ends in `big-night-out-murder-mystery`. The box says "Drop in during opening hours".')
            ->commit('Show the sign-up mode on the program page')
            ->docs('https://laravel.com/docs/blade#displaying-data', 'Laravel docs: Displaying data')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/5', 'Laravel From Scratch, episode 5: Pass Data to Views')
            ->check(Check::fileContains('resources/views/programs/show.blade.php', 'callToAction()')),

        Step::make('Run the checks and open your pull request')
            ->run('php artisan guide:verify signup-modes')
            ->explain(<<<'TEXT'
                Run the acceptance test with `guide:verify`. Then run `composer ci:check`. It runs Pint, PHPStan and every test, as GitHub does on your pull request.

                PHPStan reads the `@property SignupMode $signup_mode` line. If you forgot it, PHPStan cannot know that `->callToAction()` exists.

                In your pull request, tell reviewers to run `php artisan migrate`, because the pull request adds a column.
                TEXT)
            ->think('All existing programs got the mode `none`. Who changes them to the right mode on the live site: a developer or staff?')
            ->tryIt('Run `composer ci:check`. Pint, PHPStan and all the tests pass.')
            ->commit('Fix the checks for sign-up modes')
            ->check(Check::testPassed('SignupModesTest')),
    ]);
