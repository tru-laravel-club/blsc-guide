<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('programs')
    ->title('Programs and sessions')
    ->section(Section::Foundation)
    ->level(2)
    ->summary('A Program is something BLSC offers, such as a club or a camp. A ProgramSession is one run of it, with dates, prices and spots.')
    ->teaches(['Models', 'Migrations', 'Factories', 'Enums', 'hasMany and belongsTo', 'Query scopes', 'Money in cents', 'Seeders'])
    ->test('ProgramsTest.php')
    ->intro(<<<'TEXT'
        BLSC offers clubs, camps, parties, QUARKS and BIG Night Out. Each one is a **program**, such as the Junior Robotics & Coding Club. Each program runs many times. Each run is a **session**, such as "Fall 2026, Wednesdays". One program has many sessions. Each session has its own dates, price and number of spots.

        This split matters. The name and the description of a club stay the same every term. Only the dates and the price change. So staff add a new session each term, and they do not copy the whole program. Most features of the site use these two models: the camps and clubs pages, the staff pages, registrations, member prices and class lists.

        In this feature, you build only the data: two models with their tables, an enum for the kind of program, factories for the tests, and a seeder with the real Fall 2026 programs. There are no pages yet. You try everything in `php artisan tinker`. The "Camps and clubs pages" and "Staff: manage programs" features build the pages on top of your models.

        Two rules shape the design. First, money is stored in cents, as whole numbers. Second, the models only hold data and answer questions about it, such as "which sessions are still upcoming?" or "what is the price as text?". The code that changes data comes later, in services.
        TEXT)
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/8', 'Laravel From Scratch, episode 8: Databases, Migrations, and Eloquent')
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/16', 'Laravel From Scratch, episode 16: Eloquent Relationships')
    ->docs('https://laravel.com/docs/eloquent', 'Laravel docs: Eloquent')
    ->docs('https://laravel.com/docs/eloquent-relationships#one-to-many', 'Laravel docs: One-to-many relationships')
    ->reflect([
        'The kind of program is an enum with five cases. Staff want to add "Homeschool classes" next year. Who can add it, and what must change?',
        'Why does a session store `15000` and not `150.00`?',
        'The seeder uses the real programs from blscs.org. Why is real sample data better than random words for this project?',
        'Which step was the hardest? What would help the next contributor? Open an issue or a pull request in blsc-guide to improve that step.',
    ])
    ->steps([
        Step::make('Claim the feature and copy in its test')
            ->run('php artisan guide:start programs')
            ->explain(<<<'TEXT'
                You start with the test. Run the command below. It copies `tests/Feature/ProgramsTest.php` into the app.

                Read the whole test before you write code. It names everything that the later features use: the `Program` and `ProgramSession` models, the `ProgramType` enum, the `published()` and `upcoming()` scopes, methods such as `price()` and `dates()`, and the factory states `camp()`, `club()`, `unpublished()` and `past()`. Keep these names exactly.

                The test has no page and no request. It calls the models directly, because this feature has no pages.
                TEXT)
            ->think('Find the rule about upcoming sessions. A one-day camp is today. Is it upcoming? And a one-day camp yesterday?')
            ->tryIt('Run `php artisan test tests/Feature/ProgramsTest.php`. All the rules fail, because the models do not exist yet.')
            ->commit('Add the programs acceptance test')
            ->docs('https://pestphp.com/docs/expectations', 'Pest docs: Expectations')
            ->check(Check::fileExists('tests/Feature/ProgramsTest.php')),

        Step::make('Make the ProgramType enum')
            ->run('php artisan make:enum App/Enums/ProgramType --string')
            ->explain(<<<'TEXT'
                A program is one of five kinds: a camp, a club, QUARKS, a party or BIG Night Out. The pages use the kind to show the right programs, such as only the camps on the camps page.

                You could store the kind as free text. Then one staff member types "Camp", another types "camps", and the camps page misses half of them. An enum is a fixed list of values. PHP refuses any value that is not in the list.

                Make it with the command below. `--string` makes a backed enum: each case has a string value, such as `'camp'`, that the database stores. Write the path with `App/`, or the command puts the file in a wrong folder.

                Add the five cases: `Camp`, `Club`, `Quarks`, `Party` and `BigNightOut`. Use lowercase values with underscores, such as `'big_night_out'`. Then add two methods. `label()` returns the name for one program, such as "Camp". `pluralLabel()` returns the name for a list page, such as "Camps".
                TEXT)
            ->think('The value is `big_night_out`, but the label is "BIG Night Out". Why keep two different texts?')
            ->hint('Look at the test for the exact labels. "QUARKS" and "BIG Night Out" stay the same in the plural.')
            ->hint('A `match ($this)` expression returns one value for each case: `self::Camp => \'Camp\',`.')
            ->code(<<<'PHP'
                enum ProgramType: string
                {
                    case Camp = 'camp';
                    case Club = 'club';
                    case Quarks = 'quarks';
                    case Party = 'party';
                    case BigNightOut = 'big_night_out';

                    public function label(): string
                    {
                        return match ($this) {
                            self::Camp => 'Camp',
                            self::Club => 'Club',
                            self::Quarks => 'QUARKS',
                            self::Party => 'Party',
                            self::BigNightOut => 'BIG Night Out',
                        };
                    }

                    // pluralLabel() has the same shape.
                }
                PHP)
            ->tryIt('In `php artisan tinker`, run `App\Enums\ProgramType::from(\'party\')->pluralLabel()`. You see `"Parties"`. Run `App\Enums\ProgramType::from(\'workshop\')`. You see a `ValueError`, because "workshop" is not in the list.')
            ->commit('Add the ProgramType enum')
            ->docs('https://www.php.net/manual/en/language.enumerations.backed.php', 'PHP manual: Backed enumerations')
            ->docs('https://www.php.net/manual/en/control-structures.match.php', 'PHP manual: match')
            ->check(Check::fileExists('app/Enums/ProgramType.php')),

        Step::make('Make the Program model and its table')
            ->run('php artisan make:model Program -mfs')
            ->explain(<<<'TEXT'
                The command below makes four files: the model, a migration (`-m`), a factory (`-f`) and a seeder (`-s`).

                Open the migration. A program has these columns:

                - `type`: a string. Add an index, because every list page filters by it.
                - `name` and `summary`: strings. The summary is one sentence for the cards.
                - `slug`: a unique string, such as `junior-robotics-coding-club`. The address of the program page uses it.
                - `description`: nullable text, for the long description.
                - `age_min` and `age_max`: small whole numbers, both nullable. Some programs are for every age.
                - `is_published`: a boolean, default `true`. Staff hide a draft with it.

                Run `php artisan migrate`.

                Then open `app/Models/Program.php`. Add a `#[Fillable([...])]` attribute with every column that a form can set. Add `casts()`: cast `type` to `ProgramType::class` and `is_published` to `boolean`. Add a docblock with an `@property` line for each column, so PHPStan knows the types.
                TEXT)
            ->think('Why is `slug` unique, but `name` is not?')
            ->hint('`$table->string(\'type\')->index();` makes the column and an index. `->unique()` makes a unique index.')
            ->hint('`unsignedTinyInteger()` holds 0 to 255. That is enough for an age.')
            ->hint('Look at `app/Models/User.php`. It already has `#[Fillable]`, `casts()` and a docblock with `@property` lines.')
            ->code(<<<'PHP'
                Schema::create('programs', function (Blueprint $table) {
                    $table->id();
                    $table->string('type')->index();
                    $table->string('name');
                    $table->string('slug')->unique();
                    $table->string('summary');
                    $table->text('description')->nullable();
                    $table->unsignedTinyInteger('age_min')->nullable();
                    $table->unsignedTinyInteger('age_max')->nullable();
                    $table->boolean('is_published')->default(true);
                    $table->timestamps();
                });
                PHP)
            ->tryIt('In `php artisan tinker`, run `new App\Models\Program([\'type\' => \'camp\'])` and then `$_->type`. You see `App\Enums\ProgramType {+name: "Camp", +value: "camp"}`. The cast turned the string into the enum.')
            ->commit('Add the Program model')
            ->docs('https://laravel.com/docs/migrations#available-column-types', 'Laravel docs: Column types')
            ->docs('https://laravel.com/docs/eloquent#mass-assignment', 'Laravel docs: Mass assignment')
            ->docs('https://laravel.com/docs/eloquent-mutators#enum-casting', 'Laravel docs: Enum casting')
            ->check(Check::all(
                Check::classExists('App\Models\Program'),
                Check::table('programs', ['slug', 'summary', 'is_published']),
            )),

        Step::make('Fill in the program factory')
            ->explain(<<<'TEXT'
                Tests need programs. Open `database/factories/ProgramFactory.php`. `definition()` returns the values for one fake program.

                Make a name such as "Robot Club" from a random word. Make the slug from the name with `Str::slug()`, so the two always match. The name must be unique, because the slug is unique. Use `fake()->unique()`. Set the type to `ProgramType::Club`, the ages to 6 and 9, and `is_published` to `true`.

                Then add three states. `camp()` and `club()` set the type. `unpublished()` sets `is_published` to `false`. A test then reads like a sentence: `Program::factory()->camp()->unpublished()->create()`.
                TEXT)
            ->think('The test for the clubs page makes a club and a camp. Why does it help that the factory makes a club by default?')
            ->hint('`Str::title(fake()->unique()->word()).\' Club\'` gives a name such as "Robot Club".')
            ->hint('A state returns `$this->state(fn () => [\'type\' => ProgramType::Camp])`. Look at `unverified()` in `UserFactory`.')
            ->code(<<<'PHP'
                public function definition(): array
                {
                    $name = Str::title(fake()->unique()->word()).' Club';

                    return [
                        'type' => ProgramType::Club,
                        'name' => $name,
                        'slug' => Str::slug($name),
                        'summary' => fake()->sentence(),
                        'description' => fake()->paragraphs(2, true),
                        'age_min' => 6,
                        'age_max' => 9,
                        'is_published' => true,
                    ];
                }
                PHP)
            ->tryIt('In `php artisan tinker`, run `App\Models\Program::factory()->camp()->make()->only(\'name\', \'slug\', \'type\')`. You see a name, a matching slug and the `Camp` case.')
            ->commit('Fill in the program factory')
            ->docs('https://laravel.com/docs/eloquent-factories#defining-model-factories', 'Laravel docs: Defining model factories')
            ->docs('https://laravel.com/docs/eloquent-factories#factory-states', 'Laravel docs: Factory states')
            ->check(Check::fileContains('database/factories/ProgramFactory.php', 'function unpublished')),

        Step::make('Ask questions about a program')
            ->explain(<<<'TEXT'
                A model can answer questions about its data. Add two of them to `Program`.

                First, a query scope. Public pages show only published programs. Without a scope, every query repeats `where('is_published', true)`, and one forgotten copy shows a draft to the public. Add a `published()` scope with the `#[Scope]` attribute. Then a page writes `Program::published()->get()`.

                Second, `ageRange()`. It returns the ages as text for the cards: "Ages 6–9" when both ages are set, "Ages 9+" with only a minimum, "Up to age 12" with only a maximum, and `null` with no ages.

                Both methods only read data. They never save, change or delete. That work belongs in services, which you meet in "Staff: manage programs".
                TEXT)
            ->think('Every list of programs on the public site must hide drafts. What is the risk when each controller writes its own `where()`?')
            ->hint('A scope is a `protected` method with `#[Scope]` above it. It takes a `Builder $query` and returns nothing. Import `Illuminate\Database\Eloquent\Attributes\Scope` and `Illuminate\Database\Eloquent\Builder`.')
            ->hint('A `match (true)` checks each condition in order and returns the first one that is true.')
            ->code(<<<'PHP'
                /**
                 * Only include programs that staff have published.
                 *
                 * @param  Builder<Program>  $query
                 */
                #[Scope]
                protected function published(Builder $query): void
                {
                    $query->where('is_published', true);
                }
                PHP)
            ->tryIt('In `php artisan tinker`, run `App\Models\Program::factory()->make([\'age_min\' => 9, \'age_max\' => null])->ageRange()`. You see `"Ages 9+"`. Run `App\Models\Program::published()->toRawSql()`. You see the `where` that the scope adds.')
            ->commit('Add the published scope and the age range')
            ->docs('https://laravel.com/docs/eloquent#local-scopes', 'Laravel docs: Local scopes')
            ->check(Check::all(
                Check::fileContains('app/Models/Program.php', 'function published'),
                Check::fileContains('app/Models/Program.php', 'function ageRange'),
            )),

        Step::make('Make the ProgramSession model and its table')
            ->run('php artisan make:model ProgramSession -mf')
            ->explain(<<<'TEXT'
                The model is not called `Session`, because Laravel already uses that name for login sessions.

                A session has these columns:

                - `program_id`: the program that it belongs to. Use `foreignId()->constrained()`. Add `cascadeOnDelete()`: a session means nothing without its program.
                - `label`: a string, such as "Fall 2026, Tuesdays".
                - `starts_on`: a date. `ends_on`: a nullable date. A one-day camp has no end date.
                - `schedule`: a nullable string, such as "Tuesdays, 3:00–4:30 PM".
                - `price_cents` and `member_price_cents`: whole numbers. The member price is nullable.
                - `capacity`: a nullable number of spots. `null` means "no limit".
                - `registration_opens_at` and `registration_closes_at`: nullable date and time columns.

                Prices are in cents, such as `15000` for $150.00. Computers store decimal numbers such as 0.1 with small errors, and money must be exact. So the app stores whole cents and turns them into dollars only for display.

                Fill in the model as you did for `Program`: `#[Fillable]`, `casts()` and the docblock. Cast the two dates to `date` and the two registration times to `datetime`. Do not add `program_id` to `#[Fillable]`. A session is always made through its program, as you see in the next step.

                Then fill in `ProgramSessionFactory`. Set `program_id` to `Program::factory()`, so each fake session gets a fake program. Start it next week and end it in ten weeks. Use `15000` and `13500` for the prices and `12` for the capacity. Open the registration yesterday. Add a `past()` state that ended last week.
                TEXT)
            ->think('Why does the app store `15000` and not `150.00`? Try `0.1 + 0.2` in tinker.')
            ->hint('`unsignedInteger(\'price_cents\')` holds up to about $42 million. That is enough.')
            ->hint('In the factory, `today()->addWeek()` and `now()->subDay()` give dates relative to today. Then the test data never gets old.')
            ->code(<<<'PHP'
                Schema::create('program_sessions', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('program_id')->constrained()->cascadeOnDelete();
                    $table->string('label');
                    $table->date('starts_on');
                    $table->date('ends_on')->nullable();
                    $table->string('schedule')->nullable();
                    $table->unsignedInteger('price_cents');
                    $table->unsignedInteger('member_price_cents')->nullable();
                    $table->unsignedSmallInteger('capacity')->nullable();
                    $table->dateTime('registration_opens_at')->nullable();
                    $table->dateTime('registration_closes_at')->nullable();
                    $table->timestamps();
                });
                PHP)
            ->tryIt('Run `php artisan migrate`. In `php artisan tinker`, run `App\Models\ProgramSession::factory()->create()->starts_on`. You see a date next week, as a `CarbonImmutable` object.')
            ->commit('Add the ProgramSession model')
            ->docs('https://laravel.com/docs/migrations#foreign-key-constraints', 'Laravel docs: Foreign key constraints')
            ->docs('https://laravel.com/docs/eloquent-mutators#date-casting', 'Laravel docs: Date casting')
            ->check(Check::all(
                Check::classExists('App\Models\ProgramSession'),
                Check::table('program_sessions', ['program_id', 'price_cents', 'starts_on']),
            )),

        Step::make('Connect programs and sessions')
            ->explain(<<<'TEXT'
                The `program_sessions` table has a `program_id`, but the models do not know about it yet. A relationship tells Eloquent how two models connect.

                One program has many sessions. Add `sessions(): HasMany` to `Program`. Sort the sessions by `starts_on`, so every page shows them in date order. Each session belongs to one program. Add `program(): BelongsTo` to `ProgramSession`.

                Most pages show only the sessions that are still to come. Add an `upcoming()` scope to `ProgramSession`. A session is upcoming when it has not ended yet. A one-day session has no end date. It is upcoming when it starts today or later.

                Then add `upcomingSessions()` to `Program`. It returns `$this->sessions()->upcoming()`: the same relationship with the scope. A page can then load only the upcoming sessions of many programs in one query.
                TEXT)
            ->think('Is a camp on today\'s date upcoming? Your scope must answer "yes" at 8 AM and at 8 PM. Why is `whereDate()` with `today()` safer than a compare with `now()`?')
            ->hint('Write `@return HasMany<ProgramSession, $this>` above `sessions()`, so PHPStan knows what it returns.')
            ->hint('Group the two conditions in one `where(fn (Builder $query) => ...)`. Without the group, a later `where()` mixes with your `orWhere()`.')
            ->hint('The scope reads: ends on or after today, or (has no end date and starts on or after today).')
            ->code(<<<'PHP'
                /**
                 * Only include sessions that have not finished yet.
                 *
                 * @param  Builder<ProgramSession>  $query
                 */
                #[Scope]
                protected function upcoming(Builder $query): void
                {
                    $today = today();

                    $query->where(fn (Builder $query) => $query
                        ->whereDate('ends_on', '>=', $today)
                        ->orWhere(fn (Builder $query) => $query
                            ->whereNull('ends_on')
                            ->whereDate('starts_on', '>=', $today)));
                }
                PHP)
            ->tryIt('In `php artisan tinker`, run `$program = App\Models\Program::factory()->has(App\Models\ProgramSession::factory()->count(2), \'sessions\')->create();`. Then run `$program->sessions->count()`. You see `2`. Run `$program->sessions->first()->program->name`. You see the name of the program.')
            ->commit('Connect programs and sessions')
            ->docs('https://laravel.com/docs/eloquent-relationships#one-to-many', 'Laravel docs: One to many')
            ->docs('https://laravel.com/docs/eloquent-relationships#one-to-many-inverse', 'Laravel docs: One to many (inverse)')
            ->docs('https://laravel.com/docs/queries#logical-grouping', 'Laravel docs: Logical grouping')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/16', 'Laravel From Scratch, episode 16: Eloquent Relationships')
            ->check(Check::all(
                Check::fileContains('app/Models/Program.php', 'function upcomingSessions'),
                Check::fileContains('app/Models/ProgramSession.php', 'function program'),
            )),

        Step::make('Show prices, dates and the registration window')
            ->explain(<<<'TEXT'
                Every page that shows a session needs the same text: the price in dollars, the dates, and "is registration open?". Write each one once, in the model. Then all pages agree.

                Add four methods to `ProgramSession`:

                1. `price()` returns the price as text, such as "$150.00".
                2. `memberPrice()` returns the member price as text, or `null` when there is none.
                3. `dates()` returns "Oct 6 – Dec 8, 2026" for a session with two dates, and "Fri, Oct 23, 2026" for a one-day session.
                4. `isRegistrationOpen()` returns `true` when the registration has opened and has not closed yet. An empty time means "no limit" on that side.

                `price()` and `memberPrice()` format money in the same way. Put the format in one private method, `formatCents()`. Laravel's `Number::currency()` formats a number as money. Give it the currency `CAD` and the locale `en_CA`.
                TEXT)
            ->think('The format of a price lives in one private method. The centre wants "$150" without ".00". How many lines change?')
            ->hint('`Number::currency($cents / 100, in: \'CAD\', locale: \'en_CA\')` returns "$150.00".')
            ->hint('In `dates()`, `$this->starts_on->format(\'M j\')` gives "Oct 6". `format(\'D, M j, Y\')` gives "Fri, Oct 23, 2026". A session that ends on the day it starts is a one-day session too.')
            ->hint('In `isRegistrationOpen()`, `$this->registration_opens_at->lte($now)` means "opened at or before now".')
            ->code(<<<'PHP'
                public function price(): string
                {
                    return $this->formatCents($this->price_cents);
                }

                public function isRegistrationOpen(): bool
                {
                    $now = now();

                    return ($this->registration_opens_at === null || $this->registration_opens_at->lte($now))
                        && ($this->registration_closes_at === null || $this->registration_closes_at->gt($now));
                }

                private function formatCents(int $cents): string
                {
                    return (string) Number::currency($cents / 100, in: 'CAD', locale: 'en_CA');
                }
                PHP)
            ->tryIt('In `php artisan tinker`, run `$s = App\Models\ProgramSession::factory()->make([\'starts_on\' => \'2026-10-23\', \'ends_on\' => null, \'price_cents\' => 5000]);`, then `$s->dates()` and `$s->price()`. You see `"Fri, Oct 23, 2026"` and `"$50.00"`.')
            ->commit('Format session prices and dates')
            ->docs('https://laravel.com/docs/helpers#method-number-currency', 'Laravel docs: Number::currency()')
            ->docs('https://carbon.nesbot.com/docs/#api-formatting', 'Carbon docs: Formatting')
            ->check(Check::all(
                Check::fileContains('app/Models/ProgramSession.php', 'function price'),
                Check::fileContains('app/Models/ProgramSession.php', 'function isRegistrationOpen'),
            )),

        Step::make('Seed the real Fall 2026 programs')
            ->run('php artisan migrate:fresh --seed')
            ->explain(<<<'TEXT'
                Contributors need programs to look at. Random words such as "Velit Club" make a page hard to judge. So the seeder adds the real programs from blscs.org, as listed in October 2026.

                Open `database/seeders/ProgramSeeder.php`. Add these seven programs. Each one has one session.

                - Clubs (each $150.00, members $135.00, 12 spots, registration opens 2026-09-01 9:00 AM): "3D Design & Printing Club" (ages 9+, Tuesdays from 2026-10-06 to 2026-12-08, 3:00–4:30 PM), "Junior Robotics & Coding Club" (ages 6–9, Wednesdays from 2026-10-07 to 2026-12-09, 3:30–4:30 PM), "Senior Tech & Innovation Club" (ages 9+, Wednesdays from 2026-10-07 to 2026-12-09, 3:00–4:30 PM), "Film & Animation Club" (ages 9+, Thursdays from 2026-10-08 to 2026-12-10, 3:00–4:30 PM).
                - A camp: "Frightfully Fun Pro-D Day Camp" (ages 6–9, one day on 2026-10-23, 9:00 AM – 3:00 PM, $50.00, 20 spots).
                - QUARKS: "QUARKS" (2026/27, Mondays from 2026-11-02 to 2027-03-01, 4:00–6:00 PM, $250.00).
                - BIG Night Out: "BIG Night Out: Murder Mystery" (one evening on 2026-10-29, $30.00).

                Write each program a summary of one sentence. Make each slug from the name with `Str::slug()`. Save each session through its program: `$program->sessions()->createMany($sessions)`. Eloquent fills in `program_id`.

                Last, call the seeder from `DatabaseSeeder`: `$this->call(ProgramSeeder::class);`. Then run the command below.
                TEXT)
            ->think('The four clubs share most of their session values. How can you write them once and change only the dates?')
            ->hint('Keep the shared club values in one array, such as `$clubSession`. Then write `[...$clubSession, \'label\' => \'Fall 2026, Tuesdays\', ...]` for each club. The spread operator `...` copies the array.')
            ->hint('Keep all the programs in one array, each with a `sessions` key. Loop over it: take out the sessions, create the program, then create its sessions.')
            ->code(<<<'PHP'
                foreach ($programs as $data) {
                    $sessions = $data['sessions'];
                    unset($data['sessions']);

                    $program = Program::create([...$data, 'slug' => Str::slug($data['name'])]);
                    $program->sessions()->createMany($sessions);
                }
                PHP)
            ->tryIt('Run `php artisan tinker`, then `App\Models\Program::published()->pluck(\'name\')`. You see the seven programs. Run `App\Models\Program::firstWhere(\'slug\', \'junior-robotics-coding-club\')->sessions->first()->price()`. You see `"$150.00"`.')
            ->commit('Seed the Fall 2026 programs')
            ->docs('https://laravel.com/docs/seeding#calling-additional-seeders', 'Laravel docs: Calling additional seeders')
            ->docs('https://laravel.com/docs/eloquent-relationships#the-create-method', 'Laravel docs: The create method on a relationship')
            ->check(Check::fileContains('database/seeders/DatabaseSeeder.php', 'ProgramSeeder')),

        Step::make('Run the checks and open your pull request')
            ->run('php artisan guide:verify programs')
            ->explain(<<<'TEXT'
                Run the acceptance test with `guide:verify`. Then run `composer ci:check`. It runs Pint, PHPStan and every test, as GitHub does on your pull request. PHPStan reads your docblocks. If it says that a property does not exist, add the missing `@property` line.

                A good test fails when the rule is broken. Prove it for one rule. In the `upcoming()` scope, change `'>='` to `'>'` for `starts_on`. Run the test again. The one-day session of today drops out, and a rule fails. Change it back.
                TEXT)
            ->think('The test makes sessions relative to `today()`. What would go wrong with a fixed date, such as `2026-11-01`, next year?')
            ->tryIt('Run `composer ci:check`. Pint, PHPStan and all the tests pass.')
            ->commit('Fix the checks for programs and sessions')
            ->check(Check::testPassed('ProgramsTest')),
    ]);
