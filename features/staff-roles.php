<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('staff-roles')
    ->title('Staff accounts')
    ->section(Section::Foundation)
    ->level(1)
    ->needs(['accounts'])
    ->summary('Mark some users as science centre staff. A gate called access-admin decides who can open the staff pages.')
    ->teaches(['Migrations that change a table', 'Model default values', 'Factory states', 'Gates', 'Seeders'])
    ->test('StaffRolesTest.php')
    ->intro(<<<'TEXT'
        Parents and staff log in through the same login page. Parents see their own account. Staff also manage programs, forms and pages. So the app must know who is staff.

        In this feature, you add one column to the `users` table: `is_staff`. It is `false` for every new user. Only a maintainer or another staff member makes a user staff. Nobody can make themselves staff through a form.

        Then you define a gate called `access-admin`. A gate is a named question with a yes or no answer: "Can this user open the staff pages?" Today the answer is "yes, when `is_staff` is true". Routes, form requests and views all ask the gate. They never read `is_staff` themselves. So the rule lives in one place. If BLSC adds roles later, such as "front desk" and "manager", you change the gate, and every staff page follows.

        No staff page exists yet. The "Staff: manage programs" feature builds the first one, and it uses this gate.
        TEXT)
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/17', 'Laravel From Scratch, episode 17: Authorization Using Gates')
    ->docs('https://laravel.com/docs/authorization#gates', 'Laravel docs: Gates')
    ->reflect([
        'Every staff page asks the `access-admin` gate. BLSC adds a "front desk" role that can check in visitors but not edit programs. What changes?',
        '`is_staff` is not in `#[Fillable]`. What could a person do if it were?',
        'Which step was the hardest? What would help the next contributor? Open an issue or a pull request in blsc-guide to improve that step.',
    ])
    ->steps([
        Step::make('Claim the feature and copy in its test')
            ->run('php artisan guide:start staff-roles')
            ->explain(<<<'TEXT'
                You start with the test. Run the command below. It copies `tests/Feature/StaffRolesTest.php` into the app.

                Read the whole test before you write code. It names what the code must have: an `is_staff` column, a `staff()` factory state, an `access-admin` gate and a staff account in the seeder. Later features use all four names, so keep them.

                Find the rule "a person cannot make themselves staff when they register". It sends `is_staff` with the registration form, as an attacker would.
                TEXT)
            ->think('The test calls `Gate::forUser($user)->allows(\'access-admin\')`. It never opens a page. Why can a test check a gate without a page?')
            ->tryIt('Run `php artisan test tests/Feature/StaffRolesTest.php`. Five rules fail, because the `is_staff` column does not exist yet. The rule about a guest already passes: Laravel says "no" for a gate that does not exist. That is a safe default.')
            ->commit('Add the staff accounts acceptance test')
            ->docs('https://laravel.com/docs/http-tests', 'Laravel docs: HTTP tests')
            ->check(Check::fileExists('tests/Feature/StaffRolesTest.php')),

        Step::make('Add the is_staff column')
            ->run('php artisan make:migration add_is_staff_to_users_table')
            ->explain(<<<'TEXT'
                The `users` table already exists. Other contributors already ran the migration that made it, and Laravel never runs a migration twice. So you do not edit that migration. You make a new one that changes the table.

                Make it with the command below. The name ends in `to_users_table`, so the command already writes `Schema::table('users', ...)`, not `Schema::create()`. In `up()`, add a boolean `is_staff` column with the default `false`. Every user that exists now becomes "not staff".

                In `down()`, drop the column again. Then a maintainer can undo the migration with `php artisan migrate:rollback`.

                Then run `php artisan migrate`.
                TEXT)
            ->think('Why does the column need a default value? What happens to the users that are already in the table?')
            ->hint('`$table->boolean(\'is_staff\')->default(false)` makes the column. `->after(\'password\')` puts it next to the password in tools that show the columns.')
            ->hint('In `down()`, `$table->dropColumn(\'is_staff\')` removes it.')
            ->code(<<<'PHP'
                public function up(): void
                {
                    Schema::table('users', function (Blueprint $table) {
                        $table->boolean('is_staff')->default(false)->after('password');
                    });
                }

                public function down(): void
                {
                    Schema::table('users', function (Blueprint $table) {
                        $table->dropColumn('is_staff');
                    });
                }
                PHP)
            ->tryIt('Run `php artisan migrate`. Then run `php artisan migrate:rollback --step=1` and `php artisan migrate` again. Both work, so your `down()` method works.')
            ->commit('Add is_staff to users')
            ->docs('https://laravel.com/docs/migrations#updating-tables', 'Laravel docs: Updating tables')
            ->docs('https://laravel.com/docs/migrations#dropping-columns', 'Laravel docs: Dropping columns')
            ->check(Check::table('users', ['is_staff'])),

        Step::make('Teach the User model about is_staff')
            ->explain(<<<'TEXT'
                Open `app/Models/User.php`. Make three changes.

                1. Cast `is_staff` to a boolean in `casts()`. SQLite stores `true` as `1`. The cast turns it back into `true`, so `$user->is_staff === true` works.
                2. Add a `$attributes` array that sets `is_staff` to `false`. The database default works only after a save. `$attributes` also gives the default to a new user that you have not saved yet.
                3. Add `@property bool $is_staff` to the docblock, so PHPStan knows the type.

                Do not add `is_staff` to `#[Fillable]`. `User::create($input)` fills only the fillable columns. So a person who sends `is_staff=1` with the registration form stays a parent.
                TEXT)
            ->think('Open `app/Actions/Fortify/CreateNewUser.php`. It passes only three keys to `User::create()`. Why is it still good that `is_staff` is not fillable?')
            ->hint('`casts()` already returns an array. Add `\'is_staff\' => \'boolean\'` to it.')
            ->hint('`protected $attributes = [\'is_staff\' => false];` goes in the class, before `casts()`. Give it a docblock with `@var array<string, mixed>`.')
            ->code(<<<'PHP'
                /**
                 * The model's default values for attributes.
                 *
                 * @var array<string, mixed>
                 */
                protected $attributes = [
                    'is_staff' => false,
                ];
                PHP)
            ->tryIt('In `php artisan tinker`, run `(new App\Models\User)->is_staff`. You see `false`. Run `App\Models\User::first()->is_staff`. You see `false`, not `0`.')
            ->commit('Add is_staff to the User model')
            ->docs('https://laravel.com/docs/eloquent-mutators#attribute-casting', 'Laravel docs: Attribute casting')
            ->docs('https://laravel.com/docs/eloquent#default-attribute-values', 'Laravel docs: Default attribute values')
            ->docs('https://laravel.com/docs/eloquent#mass-assignment', 'Laravel docs: Mass assignment')
            ->check(Check::fileContains('app/Models/User.php', 'is_staff')),

        Step::make('Add a staff state to the user factory')
            ->explain(<<<'TEXT'
                Tests need staff users. A factory makes fake users, and a state changes some of their values. The factory already has an `unverified()` state. Add a `staff()` state next to it. It sets `is_staff` to `true`.

                Then a test writes `User::factory()->staff()->create()`, and reads like a sentence. Every staff test in the later features uses this state.
                TEXT)
            ->think('A test could also write `User::factory()->create([\'is_staff\' => true])`. Why is a named state better when twenty tests need staff?')
            ->hint('Copy `unverified()` in `database/factories/UserFactory.php`. Change the name, the comment and the value.')
            ->code(<<<'PHP'
                /**
                 * Indicate that the user is a member of the science centre staff.
                 */
                public function staff(): static
                {
                    return $this->state(fn (array $attributes) => [
                        'is_staff' => true,
                    ]);
                }
                PHP)
            ->tryIt('In `php artisan tinker`, run `App\Models\User::factory()->staff()->make()->is_staff`. You see `true`. `make()` builds the user without a save.')
            ->commit('Add a staff state to the user factory')
            ->docs('https://laravel.com/docs/eloquent-factories#factory-states', 'Laravel docs: Factory states')
            ->check(Check::fileContains('database/factories/UserFactory.php', 'function staff')),

        Step::make('Define the access-admin gate')
            ->explain(<<<'TEXT'
                Open `app/Providers/AppServiceProvider.php`. Laravel runs `boot()` on every request, before any route. Define the gate there:

                `Gate::define('access-admin', ...)` takes a name and a function. Laravel calls the function with the logged-in user. The function returns `true` for staff.

                Type the parameter as `User $user`. Then Laravel says "no" to a guest without a call to your function, because a guest has no user.

                You use the gate in three places later, in the "Staff: manage programs" feature. The staff routes use the `can:access-admin` middleware. Each staff form request calls `can('access-admin')`. The sidebar hides the staff links with `@can('access-admin')`.
                TEXT)
            ->think('The gate returns `$user->is_staff`. BLSC adds a `role` column later. How many files change: the gate only, or every staff page?')
            ->hint('Import the facade with `use Illuminate\Support\Facades\Gate;` and the model with `use App\Models\User;`.')
            ->hint('An arrow function keeps it on one line: `fn (User $user): bool => $user->is_staff`.')
            ->code(<<<'PHP'
                public function boot(): void
                {
                    $this->configureDefaults();

                    Gate::define('access-admin', fn (User $user): bool => $user->is_staff);
                }
                PHP)
            ->tryIt('In `php artisan tinker`, run `Gate::forUser(App\Models\User::factory()->staff()->make())->allows(\'access-admin\')`. You see `true`. Remove `->staff()` and run it again. You see `false`.')
            ->commit('Add the access-admin gate')
            ->docs('https://laravel.com/docs/authorization#writing-gates', 'Laravel docs: Writing gates')
            ->docs('https://laravel.com/docs/authorization#authorizing-actions-via-gates', 'Laravel docs: Authorizing with gates')
            ->check(Check::gate('access-admin')),

        Step::make('Seed a staff account')
            ->run('php artisan migrate:fresh --seed')
            ->explain(<<<'TEXT'
                Every contributor needs a staff account to try the staff pages. A seeder fills the database with sample data, so every contributor gets the same accounts.

                Open `database/seeders/DatabaseSeeder.php`. It makes one parent: `test@example.com`. Add a staff user next to it, with the name "Staff User" and the email `staff@example.com`. Use your `staff()` state. The factory gives both users the password `password`.

                Then run the command below. `migrate:fresh` deletes every table and runs all the migrations again. `--seed` runs the seeder after it. Use it only on your own computer: it deletes all the data.
                TEXT)
            ->think('Why do the guides use `staff@example.com` and `test@example.com`, and not accounts that each contributor makes by hand?')
            ->hint('Copy the `User::factory()->create([...])` call. Add `->staff()` before `->create()`.')
            ->code(<<<'PHP'
                User::factory()->staff()->create([
                    'name' => 'Staff User',
                    'email' => 'staff@example.com',
                ]);
                PHP)
            ->tryIt('Run `php artisan tinker`, then `App\Models\User::where(\'is_staff\', true)->pluck(\'email\')`. You see `staff@example.com` only.')
            ->commit('Seed a staff account')
            ->docs('https://laravel.com/docs/seeding#using-model-factories', 'Laravel docs: Seeders with model factories')
            ->docs('https://laravel.com/docs/seeding#running-seeders', 'Laravel docs: Running seeders')
            ->check(Check::fileContains('database/seeders/DatabaseSeeder.php', 'staff@example.com')),

        Step::make('Run the checks and open your pull request')
            ->run('php artisan guide:verify staff-roles')
            ->explain(<<<'TEXT'
                Run the acceptance test with `guide:verify`. Then run `composer ci:check`. It runs Pint, PHPStan and every test, as GitHub does on your pull request.

                A good test fails when the rule is broken. Prove it for one rule. Add `is_staff` to `#[Fillable]` in the User model, and change `CreateNewUser` to pass `$input` to `User::create()`. Run the test again. The registration rule fails. Undo both changes.
                TEXT)
            ->think('Which rule in the test protects the centre the most? What could a person do on the live site without it?')
            ->tryIt('Run `composer ci:check`. Pint, PHPStan and all the tests pass.')
            ->commit('Fix the checks for staff accounts')
            ->check(Check::testPassed('StaffRolesTest')),
    ]);
