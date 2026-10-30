<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('session-details')
    ->title('Session titles and details')
    ->section(Section::Catalog)
    ->level(1)
    ->needs(['programs-admin'])
    ->summary('A session gets an optional title and description. A drop-in program with a different event each week, such as Saturday events, needs no extra model.')
    ->teaches(['Adding columns', 'Nullable columns', 'Model methods', 'Blade conditionals'])
    ->test('SessionDetailsTest.php')
    ->intro(<<<'TEXT'
        A session is one dated run of a program, such as "Fall 2026, Tuesdays" of a club. Every session has a label, and that is enough for a club. But some programs change each time. A Saturday drop-in program has a new science theme every week: "Slime Lab" on one Saturday, "Rocket Day" on the next. Visitors want to see the theme and a short text about it.

        You could make a new `SaturdayEvent` model for this. Then BIG Night Out needs one too, with a new theme each month, and weekly camp themes need another. Each new model needs a table, admin pages and tests. Instead, look at what is already there. A Saturday event has a date, a price and a number of spots. That is a session. It only needs a title and a description.

        In this feature, you add two optional columns to `program_sessions`: `title` and `description`. Staff fill them in on the session form. The program page shows the title instead of the label, with the date and the description. A session without a title shows its label, as before. So the clubs do not change.

        Keep this path in mind while you work. Staff add a session "Saturday, Nov 7" with the title "Slime Lab". A visitor opens the program page. The controller loads the upcoming sessions. For each one, the view calls `displayName()`. The method returns the title when there is one, and the label when there is not.
        TEXT)
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/8', 'Laravel From Scratch, episode 8: Databases, Migrations, and Eloquent')
    ->docs('https://laravel.com/docs/migrations#column-modifiers', 'Laravel docs: Column modifiers')
    ->reflect([
        'A drop-in program has a new science theme every Saturday. Which records does staff create? Does the code need to know about Saturdays?',
        'Every view uses `displayName()`, not `$session->title ?? $session->label`. What does that save you when the rule changes?',
        'Which step was the hardest? What would help the next contributor? Open an issue or a pull request in blsc-guide to improve that step.',
    ])
    ->steps([
        Step::make('Claim the feature and copy in its test')
            ->run('php artisan guide:start session-details')
            ->explain(<<<'TEXT'
                You start with the test. Run the command below. It copies `tests/Feature/SessionDetailsTest.php` into the app.

                Read the whole test before you write code. It tells you the names that the code must use: the `title` and `description` fields and the `displayName()` method. It also tells you the rules. Look for the rule that removes a title. What must the session show then?

                Look at the last rule. Two Saturdays of one program each have their own theme. The test uses only sessions. It needs no new model.
                TEXT)
            ->think('The test never mentions Saturdays in the code, only in the data. Why is that a good sign?')
            ->tryIt('Run `php artisan test tests/Feature/SessionDetailsTest.php`. The rules fail, because the sessions have no `title` column yet.')
            ->commit('Add the session details acceptance test')
            ->docs('https://laravel.com/docs/http-tests', 'Laravel docs: HTTP tests')
            ->check(Check::fileExists('tests/Feature/SessionDetailsTest.php')),

        Step::make('Add the title and description columns')
            ->run('php artisan make:migration add_title_and_description_to_program_sessions_table')
            ->explain(<<<'TEXT'
                You change an existing table, so make a new migration. Do not edit the old `create_program_sessions_table` migration. Other contributors already ran it, and Laravel does not run a migration twice.

                Use `Schema::table()` to change the table. Add a `title` string and a `description` text column. A description can be longer than 255 characters, so it is `text`, not `string`. Both are `nullable()`, because most sessions, such as a ten-week club, need neither.

                In `down()`, drop both columns. Then run `php artisan migrate`.
                TEXT)
            ->think('Your database already has sessions. What value do they get in the new columns? Why does that make `nullable()` necessary?')
            ->hint('`dropColumn()` takes an array, so one call drops both columns.')
            ->code(<<<'PHP'
                Schema::table('program_sessions', function (Blueprint $table) {
                    $table->string('title')->nullable()->after('label');
                    $table->text('description')->nullable()->after('title');
                });
                PHP)
            ->tryIt('Run `php artisan migrate`. Then, in `php artisan tinker`, run `DB::table(\'program_sessions\')->pluck(\'title\', \'label\')`. Every session has `null` as its title.')
            ->commit('Add title and description to sessions')
            ->docs('https://laravel.com/docs/migrations#creating-columns', 'Laravel docs: Creating columns')
            ->docs('https://laravel.com/docs/migrations#dropping-columns', 'Laravel docs: Dropping columns')
            ->check(Check::table('program_sessions', ['title', 'description'])),

        Step::make('Add displayName() to ProgramSession')
            ->explain(<<<'TEXT'
                Open `app/Models/ProgramSession.php`. Add `title` and `description` to `#[Fillable]`. Add them to the docblock as `string|null`, so PHPStan knows that they can be empty.

                Then add `displayName()`. It returns the title, or the label when there is no title. Every page that names a session uses this method. Later features use it too, such as a registration that says which session a child is in. So the rule lives in one place.
                TEXT)
            ->think('Staff later want "Slime Lab (Nov 7)" as the name of a session. How many files change if every view calls `displayName()`?')
            ->hint('The null coalescing operator `??` returns the left side when it is not `null`, and the right side otherwise.')
            ->code(<<<'PHP'
                /**
                 * Get the name to show visitors: the title, or the label when there is no title.
                 */
                public function displayName(): string
                {
                    return $this->title ?? $this->label;
                }
                PHP)
            ->tryIt('In `php artisan tinker`, run `App\Models\ProgramSession::first()->displayName()`. You see the label of the first session, because it has no title.')
            ->commit('Add ProgramSession::displayName()')
            ->docs('https://laravel.com/docs/eloquent#mass-assignment', 'Laravel docs: Mass assignment')
            ->docs('https://www.php.net/manual/en/language.operators.comparison.php#language.operators.comparison.coalesce', 'PHP manual: The null coalescing operator')
            ->check(Check::fileContains('app/Models/ProgramSession.php', 'function displayName')),

        Step::make('Add the fields to the session form')
            ->explain(<<<'TEXT'
                Staff fill in the title and the description on the session form. Add two rules to `ProgramSessionRequest`. Both are `nullable`. Give the title a `max:255`, because the column is a string. Give the description a limit too, such as `max:2000`, so one form cannot fill the database.

                Then add the fields to `resources/views/admin/sessions/form.blade.php`, under the label. Use `<x-form-field>` for the title and `<x-admin.textarea-field>` for the description. Tell staff in the field label that the title is optional and that visitors see it instead of the label.

                What happens when staff clear the title and save? The browser sends an empty string. Laravel's `ConvertEmptyStringsToNull` middleware turns it into `null`. So the session shows its label again.
                TEXT)
            ->think('`sessionData()` uses `$this->safe()->except([...])`. Do you need to change it for the new fields? Why not?')
            ->hint('Look at the `schedule` rule. It is an optional string, like the title.')
            ->hint('Copy the `description` field from `resources/views/admin/programs/form.blade.php`.')
            ->code(<<<'PHP'
                'title' => ['nullable', 'string', 'max:255'],
                'description' => ['nullable', 'string', 'max:2000'],
                PHP)
            ->tryIt('Log in as `staff@example.com` with the password `password`. Open Programs, edit a program, and add a session with the title "Slime Lab". Save it, open it again, and clear the title. Save. The session list shows the label again.')
            ->commit('Add title and description to the session form')
            ->docs('https://laravel.com/docs/validation#a-note-on-optional-fields', 'Laravel docs: A note on optional fields')
            ->docs('https://laravel.com/docs/requests#input-trimming-and-normalization', 'Laravel docs: Input trimming and normalization')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/12', 'Laravel From Scratch, episode 12: Form Request Classes')
            ->check(Check::fileContains('app/Http/Requests/ProgramSessionRequest.php', "'title'")),

        Step::make('Show the title and description to visitors')
            ->explain(<<<'TEXT'
                Now the visitor sees the details. Search the views for `$session->label`. Change the three places that list sessions to `$session->displayName()`: the session cards in `programs/show.blade.php`, the "Next" line in `programs/index.blade.php`, and the session list in `admin/programs/edit.blade.php`. Keep the label in `admin/sessions`, because staff edit the label there.

                In the session card on the program page, show the description when there is one. Use `@if`, so a session without a description shows no empty line.

                The card already shows the dates. So a visitor sees "Slime Lab" with "Sat, Nov 7, 2026" below it.

                Show the description with `{{ }}`, not `{!! !!}`. Staff type plain text here, and `{{ }}` escapes any HTML in it.
                TEXT)
            ->think('The session form in the admin still shows `$session->label`. Why is that different from the lists?')
            ->hint('Run `grep -rn \'session->label\' resources/views` to find each place.')
            ->code(<<<'PHP'
                <slot:title class="text-lg">{{ $session->displayName() }}</slot:title>
                <slot:description>{{ $session->dates() }}</slot:description>
                <slot:content class="space-y-1 text-sm">
                    @if ($session->description)
                        <p>{{ $session->description }}</p>
                    @endif
                PHP)
            ->tryIt('Run `php artisan serve`. Open the program that has your "Slime Lab" session. The card shows "Slime Lab", its date and its description. The other sessions show their labels.')
            ->commit('Show session titles and descriptions')
            ->docs('https://laravel.com/docs/blade#if-statements', 'Laravel docs: If statements')
            ->docs('https://laravel.com/docs/blade#displaying-data', 'Laravel docs: Displaying data')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/6', 'Laravel From Scratch, episode 6: Blade Directives')
            ->check(Check::fileContains('resources/views/programs/show.blade.php', 'displayName()')),

        Step::make('Run the checks and open your pull request')
            ->run('php artisan guide:verify session-details')
            ->explain(<<<'TEXT'
                Run the acceptance test with `guide:verify`. Then run `composer ci:check`. It runs Pint, PHPStan and every test, as GitHub does on your pull request.

                The old tests in `tests/Feature/Admin/ProgramSessionManagementTest.php` send no title. They still pass, because both fields are optional. That shows that the clubs did not change.

                In your pull request, tell reviewers to run `php artisan migrate`, because the pull request adds columns.
                TEXT)
            ->think('BIG Night Out has a new theme each month. What does staff do to show it? Do they need you?')
            ->tryIt('Run `composer ci:check`. Pint, PHPStan and all the tests pass.')
            ->commit('Fix the checks for session details')
            ->check(Check::testPassed('SessionDetailsTest')),
    ]);
