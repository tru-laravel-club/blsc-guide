<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('category-descriptions')
    ->title('Category descriptions')
    ->section(Section::Content)
    ->level(1)
    ->needs(['category-pages', 'safe-html'])
    ->summary('Staff write a description for each category with the April UI editor. The category page shows it above the programs, so pages such as Parties and QUARKS need no special code.')
    ->teaches(['Adding a column', 'Reusing a cast', 'Reusing a component', 'Unescaped output'])
    ->test('CategoryDescriptionsTest.php')
    ->intro(<<<'TEXT'
        Open a category page, such as http://localhost:8000/programs/quarks. You see a title, one sentence of summary and the programs. That is enough for Clubs and Camps. It is not enough for QUARKS or Parties. Families want to know how QUARKS works before they apply, and what happens at a party before they ask for a date. That needs a few paragraphs, with headings, lists and links.

        One idea is a special page for each offering: a `quarks.blade.php`, a `parties.blade.php`, and so on. Then the text is in the code, and staff cannot change it. Each new offering needs a developer again. You removed that problem from the catalog in Program categories. Do not add it back for the text.

        So the category itself gets a `description`. Staff write it with the editor, and the category page shows it above the programs. The live dates come from the programs and sessions, as before. One page template then serves every category, now and next year.

        Most of the parts already exist. The `SafeHtml` cast from Safe HTML cleans the HTML. The `<x-editor>` component shows the toolbar from `config/editor.php`. In this feature, you connect them to one more model. That is the reward for a good design: the second use is small.

        The path of a description:

        1. Staff write the text in the editor on the category form, and save.
        2. `CategoryRequest` lets the field through. The `SafeHtml` cast on `Category` cleans the HTML before the save.
        3. The category page shows the clean HTML with `{!! !!}`, above the programs.
        TEXT)
    ->docs('https://laravel.com/docs/eloquent-mutators#custom-casts', 'Laravel docs: Custom casts')
    ->docs('https://laravel.com/docs/blade#displaying-unescaped-data', 'Laravel docs: Displaying unescaped data')
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/8', 'Laravel From Scratch, episode 8: Databases, Migrations, and Eloquent')
    ->reflect([
        'The Parties and QUARKS pages need text and live dates. Why is a category page enough, with no special page for each?',
        'The category page shows the description with `{!! !!}`. Which part of the code makes that safe? What happens if a contributor removes the cast from `Category`?',
        'A category already has a `summary` and now also a `description`. When does staff use each one? Where does each one show?',
        'Which step was the hardest? What would help the next contributor? Open an issue or a pull request in blsc-guide to improve that step.',
    ])
    ->steps([
        Step::make('Claim the feature and copy in its test')
            ->run('php artisan guide:start category-descriptions')
            ->explain(<<<'TEXT'
                Run the command below. It copies `tests/Feature/CategoryDescriptionsTest.php` into the app.

                Read the test before you write code. Most rules use `saveCategoryDescription()` at the top. It sends the category form as staff, with one description. Then each rule checks what the database saved, or what the category page shows.

                Look at "unsafe HTML is removed from the description". It is almost the same as the attack rules in `SafeHtmlTest.php`. The safe-html feature already tested the sanitizer. This rule only proves that the category uses it.
                TEXT)
            ->think('The rule "the category page shows the description above the programs" uses `assertSeeInOrder()`. What does it check that `assertSee()` does not?')
            ->tryIt('Run `php artisan test tests/Feature/CategoryDescriptionsTest.php`. All four rules fail, because the `categories` table has no `description` column yet.')
            ->commit('Add the category descriptions acceptance test')
            ->docs('https://laravel.com/docs/http-tests', 'Laravel docs: HTTP tests')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/22', 'Laravel From Scratch, episode 22: How to Get Started Testing Your Code')
            ->check(Check::fileExists('tests/Feature/CategoryDescriptionsTest.php')),

        Step::make('Add the description column')
            ->run('php artisan make:migration add_description_to_categories_table')
            ->explain(<<<'TEXT'
                Add a nullable `description` column to `categories`, after `summary`. Write `down()` too: it drops the column.

                Use `text`, not `string`. A `string` column holds a short value, such as a name. A description holds many paragraphs of HTML, so it needs a `text` column. The `description` of `programs` and the `body` of `pages` use `text` for the same reason.

                The column is nullable, because the categories that exist have no description. Many categories never need one. The Clubs page explains itself with its list of clubs.
                TEXT)
            ->think('The table already has a `summary`. Why not make `summary` longer and put the HTML there?')
            ->hint('Look at `database/migrations/*_add_cover_to_pages_table.php`. It adds columns to a table that exists, with `Schema::table()`.')
            ->code(<<<'PHP'
                Schema::table('categories', function (Blueprint $table) {
                    $table->text('description')->nullable()->after('summary');
                });
                PHP)
            ->tryIt('Run `php artisan migrate`. Then, in `php artisan tinker`, run `Schema::getColumnListing(\'categories\')`. You see `description` in the list. SQLite ignores `after()` and adds the column at the end. MySQL uses it.')
            ->commit('Add a description column to categories')
            ->docs('https://laravel.com/docs/migrations#column-method-text', 'Laravel docs: The text column type')
            ->docs('https://laravel.com/docs/migrations#column-modifiers', 'Laravel docs: Column modifiers')
            ->check(Check::table('categories', ['description'])),

        Step::make('Clean the description with the SafeHtml cast')
            ->explain(<<<'TEXT'
                Open `app/Models/Category.php`. Add `description` to `#[Fillable]`, and add a `@property string|null $description` line to the docblock.

                Then add a `casts()` method that maps `description` to `SafeHtml::class`. `Page` and `Program` already do this. Copy the method from `Page`.

                This one line is the security of the feature. The cast cleans the HTML each time the model sets the value: from the staff form, a seeder or tinker. You do not call the sanitizer in the controller, and you do not write a new allowlist. The list in `config/editor.php` applies to categories now, too.
                TEXT)
            ->think('Staff add the `underline` format to `config/editor.php` next year. Which files change for categories? Why?')
            ->hint('`Category` has no `casts()` method yet. Add the whole method, with its docblock, under `use HasFactory;`.')
            ->code(<<<'PHP'
                /**
                 * Get the attributes that should be cast.
                 *
                 * @return array<string, string>
                 */
                protected function casts(): array
                {
                    return [
                        'description' => SafeHtml::class,
                    ];
                }
                PHP)
            ->tryIt('Run `php artisan tinker`, then `(new App\Models\Category)->fill([\'description\' => \'<p onclick="x()">Hi</p><script>alert(1)</script>\'])->description`. You see `"<p>Hi</p>"`.')
            ->commit('Clean category descriptions with SafeHtml')
            ->docs('https://laravel.com/docs/eloquent-mutators#custom-casts', 'Laravel docs: Custom casts')
            ->check(Check::fileContains('app/Models/Category.php', 'SafeHtml::class')),

        Step::make('Write the description with the editor')
            ->explain(<<<'TEXT'
                Staff need a field for the description. Two files change.

                In `app/Http/Requests/CategoryRequest.php`, add a rule for `description`: `nullable` and `string`. A form request passes on only the fields that have a rule. Without the rule, `$request->validated()` drops the description, and the save changes nothing. No error tells you why.

                In `resources/views/admin/categories/form.blade.php`, add `<x-editor>` under the summary. Pass the name, a label and the old value. Tell staff where the text shows, so they know what to write.

                Use `<x-editor>`, not `<april:editor>`. The component reads the toolbar buttons from `config/editor.php`, so the toolbar and the cast always agree. The editor also needs its JavaScript, but the staff layout already loads it with `@aprilEditorScripts`.
                TEXT)
            ->think('The request has no `max` rule for `description`, but `summary` has `max:255`. Why can the description have no limit here?')
            ->hint('Look at `resources/views/admin/programs/form.blade.php`. It uses `<x-editor>` for the program description.')
            ->code(<<<'PHP'
                <x-editor name="description" label="Description (shown above the programs)" :value="old('description', $category->description)" />
                PHP)
            ->tryIt('Log in as staff, open Categories, and edit QUARKS. Write two paragraphs, make one word bold, and add a list. Save, then edit the category again. The editor shows your text with its formats.')
            ->commit('Edit category descriptions with the editor')
            ->docs('https://laravel.com/docs/validation#a-note-on-optional-fields', 'Laravel docs: A note on optional fields')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/12', 'Laravel From Scratch, episode 12: Form Request Classes')
            ->check(Check::all(
                Check::fileContains('app/Http/Requests/CategoryRequest.php', "'description'"),
                Check::fileContains('resources/views/admin/categories/form.blade.php', 'name="description"'),
            )),

        Step::make('Show the description above the programs')
            ->explain(<<<'TEXT'
                Open `resources/views/categories/show.blade.php`. Under the `<header>`, show the description when the category has one.

                Use `{!! !!}`, not `{{ }}`. With `{{ }}`, Blade escapes the HTML, and visitors see the tags as text. `{!! !!}` is safe here only because the cast cleaned the HTML before the save. Write a Blade comment above the line that says so. The next contributor then knows why the line is safe, and does not copy it for a value without the cast.

                Give the `<div>` the `rich-text` class. It styles the headings, lists and links, like the description on the program page. Add `max-w-3xl`, because long lines of text are hard to read on a wide screen.

                Last, open `database/seeders/ProgramSeeder.php` and give the QUARKS category a short description in HTML. Then every contributor sees a description after `migrate:fresh --seed`.
                TEXT)
            ->think('An empty editor sends `<p></p>`. What does the cast save for it? Why does that keep the page free of an empty box?')
            ->hint('`resources/views/programs/show.blade.php` shows the program description in the same way.')
            ->hint('The seeder makes each category from an array. Add a `description` key to the QUARKS array.')
            ->code(<<<'PHP'
                @if ($category->description)
                    {{-- The SafeHtml cast cleaned this HTML when it was saved. --}}
                    <div class="rich-text max-w-3xl leading-7">{!! $category->description !!}</div>
                @endif
                PHP)
            ->tryIt('Run `php artisan migrate:fresh --seed`, then open http://localhost:8000/programs/quarks. You see the description between the summary and the programs. Open http://localhost:8000/programs/clubs. It has no description and no empty space.')
            ->commit('Show category descriptions on the site')
            ->docs('https://laravel.com/docs/blade#displaying-unescaped-data', 'Laravel docs: Displaying unescaped data')
            ->docs('https://laravel.com/docs/seeding#writing-seeders', 'Laravel docs: Writing seeders')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/6', 'Laravel From Scratch, episode 6: Blade Directives')
            ->check(Check::fileContains('resources/views/categories/show.blade.php', '$category->description')),

        Step::make('Run the checks and open your pull request')
            ->run('php artisan guide:verify category-descriptions')
            ->explain(<<<'TEXT'
                Run the acceptance test with `guide:verify`. Then run `composer ci:check`. It runs Pint, PHPStan and every test, as GitHub does on your pull request.

                PHPStan reads the docblock of `Category`. If you forgot the `@property` line, it reports that `$description` is not defined. Add the line.

                In your pull request, tell reviewers to run `php artisan migrate`. Add a screenshot of a category page with a description. A reviewer sees the result before they check out your branch.
                TEXT)
            ->think('This feature changed six files and added no new class. Which earlier feature made that possible? What did it build that you reused?')
            ->tryIt('Run `composer ci:check`. Pint, PHPStan and all the tests pass.')
            ->commit('Fix the checks for category descriptions')
            ->check(Check::testPassed('CategoryDescriptionsTest')),
    ]);
