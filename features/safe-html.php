<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('safe-html')
    ->title('Safe HTML')
    ->section(Section::Content)
    ->level(2)
    ->needs(['programs-admin'])
    ->summary('A SafeHtml cast cleans the HTML from the April UI editor with an allowlist. Every model that stores editor HTML uses the cast.')
    ->teaches(['Composer and npm', 'Version constraints and lock files', 'Autoloading', 'HTML sanitizing', 'Allowlists', 'Custom casts', 'Config files', 'Blade components', 'Unescaped output'])
    ->test('SafeHtmlTest.php')
    ->intro(<<<'TEXT'
        Open a program in the staff area and look at the Description box. It is a plain text box. Staff cannot make a word bold, add a heading or add a link. The program page shows the text with `{{ }}`, so Blade escapes every `<` and `>`. That is safe, but it is only plain text.

        In this feature, staff write with `<april:editor>`, a rich text editor from April UI. The editor sends HTML to the server, such as `<p><strong>Bring a lunch.</strong></p>`. The program page must show that HTML as HTML, so Blade must not escape it.

        That is dangerous. HTML can hold JavaScript: a `<script>` tag, an `onclick` attribute or a `javascript:` link. If the page shows that HTML, the script runs in the browser of every visitor. This attack is called cross-site scripting (XSS). A visitor cannot reach the staff form, but a stolen staff password or a changed request can. So the server never trusts the HTML that it gets.

        The answer is an allowlist. A sanitizer reads the HTML and keeps only the tags and attributes on the list. It removes everything else. You do not list the bad things, because you cannot know all of them. You list the good things.

        The path of a description:

        1. Staff click buttons on the editor toolbar. The editor puts the HTML in a hidden input.
        2. The form sends the HTML. The `SafeHtml` cast on the model cleans it before the model saves it.
        3. The program page shows the clean HTML with `{!! !!}`.

        One list in `config/editor.php` decides both the toolbar buttons and the allowed tags. When the list changes, the toolbar and the sanitizer change together. This is design rule 3: one place for each change.
        TEXT)
    ->docs('https://symfony.com/doc/current/html_sanitizer.html', 'Symfony docs: HTML Sanitizer')
    ->docs('https://laravel.com/docs/eloquent-mutators#custom-casts', 'Laravel docs: Custom casts')
    ->docs('https://laravel.com/docs/blade#displaying-unescaped-data', 'Laravel docs: Displaying unescaped data')
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/6', 'Laravel From Scratch, episode 6: Blade Directives')
    ->reflect([
        'The editor only makes safe HTML. Why must the server clean it again?',
        'Staff want underlined text. Which files change? What must happen in April UI first?',
        'A new model stores editor HTML, such as the help text of a form field. What must its developer do, so that the page that shows it is safe?',
        'Which step was the hardest? What would help the next contributor? Open an issue or a pull request in blsc-guide to improve that step.',
    ])
    ->steps([
        Step::make('Claim the feature and copy in its test')
            ->run('php artisan guide:start safe-html')
            ->explain(<<<'TEXT'
                Run the command below. It copies `tests/Feature/SafeHtmlTest.php` into the app.

                Read the test before you write code. Most rules use the function `saveProgramDescription()` at the top. It logs in as staff, sends the program form with one description, and returns the description that the database saved. So each rule reads like this: "this HTML goes in, and this HTML comes out".

                Look at the attack rules. A `<script>` tag, an `onclick` attribute and a `javascript:` link must not come out. Then look at "the allowed formats stay". A sanitizer that removes all HTML is safe, but it is not useful. Both kinds of rule are necessary.
                TEXT)
            ->think('The test changes `editor.formats` in one rule and then checks the toolbar and the saved HTML. What does that tell you about where the list of formats lives?')
            ->tryIt('Run `php artisan test tests/Feature/SafeHtmlTest.php`. Most rules fail. The program page rule fails, because the page escapes the `<strong>` tag.')
            ->commit('Add the safe HTML acceptance test')
            ->docs('https://laravel.com/docs/http-tests', 'Laravel docs: HTTP tests')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/22', 'Laravel From Scratch, episode 22: How to Get Started Testing Your Code')
            ->check(Check::fileExists('tests/Feature/SafeHtmlTest.php')),

        Step::make('Choose a library and install it with Composer')
            ->run('composer require symfony/html-sanitizer')
            ->explain(<<<'TEXT'
                Do not write your own sanitizer with `str_replace()` or a regular expression. HTML has many ways to hide a script: upper case tags, broken tags, encoded characters and more. A sanitizer must read the HTML the way a browser reads it. This is a solved problem, so use a library.

                A package manager installs libraries and the libraries that they need. PHP uses Composer. JavaScript uses npm. Python uses pip, and Ruby uses Bundler. They all work in the same way, so what you learn here applies to all of them.

                Choose a library with care. Every package runs with full access to your app and its data. Before you add one, open its page on Packagist, the list of public Composer packages, and check:

                - Who maintains it, and when the last release was.
                - How many projects use it.
                - Its license. The website is open source, so the license must allow that.
                - Its PHP version. It must work with the PHP version of the app.

                Symfony HtmlSanitizer passes these checks. It reads the HTML into a tree, keeps only the parts on your allowlist, and writes new HTML. Laravel uses many Symfony packages already.

                Run the command below. Composer finds the newest version that works with your PHP version and your other packages, downloads it into `vendor/`, and writes it into `composer.json` and `composer.lock`.
                TEXT)
            ->think('`strip_tags()` is part of PHP. It can keep some tags, such as `<strong>`. Why is it not enough? Hint: what does it do with the attributes of a tag that it keeps?')
            ->tryIt(<<<'TEXT'
                Open https://packagist.org/packages/symfony/html-sanitizer. Find the license, the latest version and the PHP version that it needs.

                Then run `php artisan tinker`, then `(new Symfony\Component\HtmlSanitizer\HtmlSanitizer((new Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig)->allowElement('p')))->sanitize('<p onclick="x()">Hi</p><script>alert(1)</script>')`. You see `"<p>Hi</p>"`.
                TEXT)
            ->docs('https://symfony.com/doc/current/html_sanitizer.html', 'Symfony docs: HTML Sanitizer')
            ->docs('https://getcomposer.org/doc/01-basic-usage.md#installing-dependencies', 'Composer docs: Installing dependencies')
            ->check(Check::fileContains('composer.json', 'symfony/html-sanitizer')),

        Step::make('Read what Composer changed')
            ->explain(<<<'TEXT'
                Before you commit, look at what Composer changed. Run `git status`. Two files changed, and one big folder did not show up.

                `composer.json` lists what the app needs. Composer added a line such as `"symfony/html-sanitizer": "^7.4"`. This is a version constraint. Versions have three numbers: major, minor and patch, such as `7.4.22`. A new major version can break your code. A minor or patch version must not. The caret `^7.4` means "7.4 or later, but not 8.0". So Composer can install bug fixes, but never a breaking change.

                `composer.lock` lists the exact version of every package that Composer installed, including the packages that your packages need. Commit it. Other contributors, the checks on GitHub and Laravel Cloud run `composer install`, which installs exactly the versions in the lock file. So everybody runs the same code.

                `composer update` is different. It looks for newer versions and rewrites the lock file. Run it only on purpose, in its own pull request, because it changes code that you did not write.

                `vendor/` holds the downloaded code. It is in `.gitignore`, so git never commits it. Anyone can make it again from the lock file.

                Last, autoloading. You never write `require` for a class. Composer maps each namespace to a folder. `Symfony\Component\HtmlSanitizer\` points to `vendor/symfony/html-sanitizer`, and `App\` points to your `app/` folder. When PHP needs a class, Composer finds the file from its name. That is why the name of a class must match its file path.
                TEXT)
            ->think('A contributor opens a pull request that changes the menu. The pull request also changes `composer.lock` with 30 new versions. What can go wrong? What do you ask in your review?')
            ->hint('Run `git diff composer.json` and `git diff --stat composer.lock`.')
            ->hint('`composer show symfony/html-sanitizer` shows the installed version, the license, and the packages that it needs.')
            ->tryIt(<<<'TEXT'
                Run `composer show symfony/html-sanitizer`. You see the exact version from the lock file, the MIT license, and the `psr-4` autoload rule.

                Then run `grep HtmlSanitizer vendor/composer/autoload_psr4.php`. You see the line that maps the namespace to its folder. Composer wrote this file for you.

                Then run `git status`. You see `composer.json` and `composer.lock`, but nothing from `vendor/`.
                TEXT)
            ->commit('Install symfony/html-sanitizer')
            ->docs('https://getcomposer.org/doc/articles/versions.md#caret-version-range-', 'Composer docs: Caret version range')
            ->docs('https://getcomposer.org/doc/01-basic-usage.md#commit-your-composer-lock-file-to-version-control', 'Composer docs: Commit your composer.lock file')
            ->docs('https://getcomposer.org/doc/01-basic-usage.md#autoloading', 'Composer docs: Autoloading')
            ->check(Check::fileContains('composer.lock', '"name": "symfony/html-sanitizer"')),

        Step::make('List the allowed formats in one config file')
            ->explain(<<<'TEXT'
                Create `config/editor.php`. It returns an array with one key, `formats`.

                Each format is one toolbar button. Its value lists the HTML tags that the button makes. Each tag lists the attributes that it may keep. For example, `bold` makes `<strong>` with no attributes, and `link` makes `<a>` with an `href`.

                Start with the formats that the centre needs: `bold`, `italic`, `heading`, `bulletList`, `orderedList`, `blockquote` and `link`. The key must be the name of an April UI button, because the toolbar reads it in a later step. Open `vendor/yungifez/april-ui/docs/pages/1.x/components/editor.blade.md` to see the button names. The editor makes these tags: `heading` makes `<h2>`, a list makes `<ul>` or `<ol>` with `<li>`.

                The April editor has a fixed set of buttons. It has no button for underline, colour or images. Custom buttons are a planned April UI feature. Until then, a format can only be on the list if April UI has a button for it.
                TEXT)
            ->think('`bulletList` and `orderedList` both list `li`. Is that a problem? What happens to `li` when you remove only one of them?')
            ->hint('Copy the comment style of another file in `config/`. Explain in the comment that the toolbar and the sanitizer both read the list.')
            ->hint('Write each format as `\'button\' => [\'tag\' => [attributes]]`. One shape for every format is easier to loop over than a mix of shapes.')
            ->code(<<<'PHP'
                return [

                    'formats' => [
                        'bold' => ['strong' => []],
                        'italic' => ['em' => []],
                        'heading' => ['h2' => []],
                        'bulletList' => ['ul' => [], 'li' => []],
                        'orderedList' => ['ol' => [], 'li' => []],
                        'blockquote' => ['blockquote' => []],
                        'link' => ['a' => ['href']],
                    ],

                ];
                PHP)
            ->tryIt('Run `php artisan tinker`, then `array_keys(config(\'editor.formats\'))`. You see the seven button names.')
            ->commit('Add the editor formats config')
            ->docs('https://laravel.com/docs/configuration#accessing-configuration-values', 'Laravel docs: Accessing configuration values')
            ->check(Check::fileContains('config/editor.php', "'formats'")),

        Step::make('Build the sanitizer from the config')
            ->explain(<<<'TEXT'
                Create `app/Support/SafeHtmlSanitizer.php`. The constructor builds a Symfony `HtmlSanitizer` from the config. The `clean(string $html): string` method returns the clean HTML.

                Build the `HtmlSanitizerConfig` in four parts:

                1. Choose what happens to a tag that is not on the list. `HtmlSanitizerAction::Block` removes the tag but keeps its text. Staff then lose a format, but never their words. Drop `script` and `style` completely, because their content is code, not text.
                2. Allow `p` and `br` always. Every editor text has paragraphs, and they are not a button.
                3. Allow links only with `https`, `http` and `mailto`, and allow relative links such as `/news`. A `javascript:` link then loses its `href`.
                4. Loop over `config('editor.formats')` and allow each tag with its attributes.

                Be careful: the config object is immutable. Each method returns a new object and does not change the old one. So you must assign the result: `$config = $config->allowElement(...)`.

                By default, the sanitizer cuts the HTML after 20,000 characters. A long page is longer, so turn off the limit with `withMaxInputLength(-1)`.
                TEXT)
            ->think('The default can be `Drop` (remove the tag and its text) or `Block` (remove the tag, keep its text). Which is better for staff? Which is better for `<script>`? How does the code get both?')
            ->hint('Look at the "Configuration" part of the Symfony docs. `defaultAction()`, `dropElement()`, `allowElement()`, `allowLinkSchemes()` and `allowRelativeLinks()` are the methods that you need.')
            ->hint('`config(\'editor.formats\')` returns `mixed` for PHPStan. Add `/** @var array<string, array<string, list<string>>> $formats */` above the variable that holds it.')
            ->code(<<<'PHP'
                public function __construct()
                {
                    $config = (new HtmlSanitizerConfig)
                        ->defaultAction(HtmlSanitizerAction::Block)
                        ->dropElement('script')
                        ->dropElement('style')
                        ->allowElement('p')
                        ->allowElement('br')
                        ->allowLinkSchemes(['https', 'http', 'mailto'])
                        ->allowRelativeLinks()
                        ->withMaxInputLength(-1);

                    /** @var array<string, array<string, list<string>>> $formats */
                    $formats = config('editor.formats', []);

                    foreach ($formats as $tags) {
                        foreach ($tags as $tag => $attributes) {
                            $config = $config->allowElement($tag, $attributes);
                        }
                    }

                    $this->sanitizer = new HtmlSanitizer($config);
                }

                public function clean(string $html): string
                {
                    return $this->sanitizer->sanitize($html);
                }
                PHP)
            ->tryIt('Run `php artisan tinker`, then `app(App\Support\SafeHtmlSanitizer::class)->clean(\'<h2 onclick="x()">Hi</h2><u>under</u><a href="javascript:alert(1)">Go</a>\')`. You see `"<h2>Hi</h2>under<a>Go</a>"`. The `<u>` tag is gone, but its text stays.')
            ->commit('Add the SafeHtml sanitizer')
            ->docs('https://symfony.com/doc/current/html_sanitizer.html#default-action', 'Symfony docs: Tags that are not allowed')
            ->docs('https://symfony.com/doc/current/html_sanitizer.html#max-input-length', 'Symfony docs: Max input length')
            ->check(Check::fileExists('app/Support/SafeHtmlSanitizer.php')),

        Step::make('Clean the HTML in a cast')
            ->run('php artisan make:cast SafeHtml')
            ->explain(<<<'TEXT'
                Where do you call `clean()`? You can call it in `ProgramRequest`, but then a seeder, a tinker session or a future import skips it. Put it on the model instead. A cast runs each time the model sets the attribute, wherever the value comes from.

                Run the command below. It makes `app/Casts/SafeHtml.php`. In `set()`, return `null` for `null`, and otherwise return the clean HTML. Resolve the sanitizer with `app(SafeHtmlSanitizer::class)`. In `get()`, return the value as it is. It was cleaned when it was saved, so the page does not clean it again on each visit.

                An empty editor sends `<p></p>`, not an empty string. Without care, every program gets an empty paragraph. So when the clean HTML has no text, return `null`.

                Then open `app/Models/Program.php` and add `'description' => SafeHtml::class` to `casts()`. Any model that stores editor HTML uses the same cast. Later features use it for pages, posts and category descriptions.
                TEXT)
            ->think('The cast cleans on `set`, not on `get`. Which rows in the database are not clean, then? You fix them in a later step.')
            ->hint('`strip_tags($html)` gives the text without tags. `trim()` removes spaces. If the result is `\'\'`, the HTML has no text.')
            ->hint('Add `@implements CastsAttributes<string|null, string|null>` to the class docblock, so PHPStan knows the types.')
            ->code(<<<'PHP'
                public function set(Model $model, string $key, mixed $value, array $attributes): ?string
                {
                    if ($value === null) {
                        return null;
                    }

                    $html = app(SafeHtmlSanitizer::class)->clean($value);

                    return trim(strip_tags($html)) === '' ? null : $html;
                }
                PHP)
            ->tryIt('Run `php artisan tinker`, then `App\Models\Program::first()->fill([\'description\' => \'<p onclick="x()">Hi</p>\'])->description`. You see `"<p>Hi</p>"`. Nothing is saved, because you did not call `save()`.')
            ->commit('Clean program descriptions with a SafeHtml cast')
            ->docs('https://laravel.com/docs/eloquent-mutators#custom-casts', 'Laravel docs: Custom casts')
            ->check(Check::all(
                Check::fileExists('app/Casts/SafeHtml.php'),
                Check::fileContains('app/Models/Program.php', 'SafeHtml::class'),
            )),

        Step::make('Wrap the April editor in a component')
            ->explain(<<<'TEXT'
                Now the toolbar. Create the anonymous component `resources/views/components/editor.blade.php`. It takes `name`, `label` and `value`, like `<x-form-field>`. It shows a label, the editor and the error for the field.

                Pass the buttons from the config to the `buttons` prop of `<april:editor>`. Use the keys of `config('editor.formats')`, and add `undo` and `redo`. They make no HTML, so they are not in the allowlist, but staff need them. With `buttons`, April UI hides every button that is not in the array.

                The editor uses TipTap, a JavaScript editor. April UI does not load it on every page, so pages without an editor stay small. Add `@aprilEditorScripts` after `@aprilScripts` in `resources/views/layouts/app.blade.php`. That is the staff layout, so only staff download the editor.

                Last, open `resources/views/admin/programs/form.blade.php`. Change the description textarea to `<x-editor name="description" label="Description" :value="old('description', $program->description)" />`. Every future form uses `<x-editor>`, never `<april:editor>` directly. Then no form can forget the config.
                TEXT)
            ->think('A contributor writes `<april:editor name="body" />` directly in a new form. Which buttons does the toolbar show? What does the sanitizer do with the HTML from the extra buttons?')
            ->hint('Read `vendor/yungifez/april-ui/docs/pages/1.x/components/editor.blade.md`. It shows the `buttons` prop and where `@aprilEditorScripts` goes.')
            ->hint('In a `@php` block, `[...array_keys(config(\'editor.formats\', [])), \'undo\', \'redo\']` makes the array of buttons.')
            ->code(<<<'PHP'
                @props([
                    'name',
                    'label',
                    'value' => null,
                ])

                @php
                    $buttons = [...array_keys(config('editor.formats', [])), 'undo', 'redo'];
                @endphp

                <div class="space-y-2">
                    <april:label>{{ $label }}</april:label>
                    <april:editor :name="$name" :value="$value ?? ''" :buttons="$buttons" {{ $attributes }} />
                    @error($name)
                        <p class="text-sm text-destructive">{{ $message }}</p>
                    @enderror
                </div>
                PHP)
            ->tryIt(<<<'TEXT'
                Log in as staff and edit a program. The description has a toolbar. Make a word bold, add a heading and a list, and save. The editor shows your formats again after the save.

                Then remove `'blockquote'` from `config/editor.php` and reload the page. The quote button is gone. Put it back.
                TEXT)
            ->commit('Write program descriptions with the editor')
            ->docs('https://laravel.com/docs/blade#anonymous-components', 'Laravel docs: Anonymous components')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/4', 'Laravel From Scratch, episode 4: Layout Files')
            ->check(Check::all(
                Check::fileExists('resources/views/components/editor.blade.php'),
                Check::fileContains('resources/views/layouts/app.blade.php', '@aprilEditorScripts'),
                Check::fileContains('resources/views/admin/programs/form.blade.php', '<x-editor'),
            )),

        Step::make('Turn the old descriptions into HTML')
            ->run('php artisan make:migration convert_program_descriptions_to_html')
            ->explain(<<<'TEXT'
                The cast cleans a value when the model saves it. But the database already has descriptions from before the cast. They are plain text, and nobody cleaned them. In the next step, the page shows the description without escaping. Then a `<` in an old description becomes HTML.

                Fix the old rows with a data migration. For each program with a description, split the text into paragraphs at blank lines. Escape each paragraph with `e()`, keep its line breaks with `nl2br()`, and wrap it in `<p>`. The old page showed the text in the same way, so visitors see no change. Use `DB::table()`, not the model, like the migration in Program categories.

                In `down()`, turn the HTML back into text: change `</p><p>` to a blank line, remove the `<br>` tags, then use `strip_tags()` and `html_entity_decode()`.

                Then make new data HTML too. In `ProgramFactory`, wrap the description in `<p>` tags. In `ProgramSeeder`, do the same for the two descriptions.
                TEXT)
            ->think('Escaped text is always safe HTML. Why is `e()` enough here, with no sanitizer?')
            ->hint('`DB::table(\'programs\')->whereNotNull(\'description\')->eachById(function (object $program) { ... })` reads the rows in small groups.')
            ->hint('`preg_split(\'/\R{2,}/\', $text)` splits the text at each blank line. `\R` matches any kind of line break.')
            ->code(<<<'PHP'
                DB::table('programs')->whereNotNull('description')->eachById(function (object $program) {
                    $paragraphs = preg_split('/\R{2,}/', trim($program->description)) ?: [];

                    $html = collect($paragraphs)
                        ->map(fn (string $paragraph) => '<p>'.nl2br(e(trim($paragraph)), false).'</p>')
                        ->implode('');

                    DB::table('programs')->where('id', $program->id)->update(['description' => $html]);
                });
                PHP)
            ->tryIt(<<<'TEXT'
                Before you migrate, run `php artisan tinker`, then `DB::table('programs')->whereNotNull('description')->pluck('description')`. You see plain text.

                Run `php artisan migrate` and run the same line again. Each description is now in `<p>` tags, and an `&` is now `&amp;`. Run `php artisan migrate:rollback --step=1` to prove that `down()` works, then migrate again.
                TEXT)
            ->commit('Convert program descriptions to HTML')
            ->docs('https://laravel.com/docs/queries#chunking-results', 'Laravel docs: Chunking results')
            ->check(Check::all(
                fn (): bool => glob(database_path('migrations/*_convert_program_descriptions_to_html.php')) !== [],
                Check::fileContains('database/factories/ProgramFactory.php', '<p>'),
            )),

        Step::make('Show the description as HTML on the program page')
            ->explain(<<<'TEXT'
                Open `resources/views/programs/show.blade.php`. It shows the description with `nl2br(e(...))`. Change it to `{!! $program->description !!}` inside a `<div class="rich-text">`.

                `{{ }}` escapes the value. `{!! !!}` prints it as it is. Use `{!! !!}` only for an attribute with the `SafeHtml` cast. For anything else, a reviewer must refuse the pull request. Add a short Blade comment above the line, so the next reader knows why it is safe.

                Tailwind removes the default styles of HTML tags, so a list has no bullets and a heading looks like text. Add a `.rich-text` class to `resources/css/app.css` in `@layer components`. Give `h2`, `ul`, `ol`, `blockquote` and `a` inside it a style with `@apply`. Pages and posts use the same class later.

                The CSS is built by tools from npm, the package manager for JavaScript. npm works like Composer: `package.json` lists the tools, `package-lock.json` holds the exact versions, and `node_modules/` is never committed. After you pull new code, run `npm ci`. It installs exactly the versions in the lock file, as `composer install` does. Then run `npm run build`. It runs the `build` script in `package.json`, which writes the finished CSS and JavaScript to `public/build`.
                TEXT)
            ->think('Search the views for `{!!`. For each result, can you name the cast or the code that makes the value safe?')
            ->hint('`.rich-text ul { @apply list-disc pl-6; }` gives a bullet list its bullets back.')
            ->code(<<<'PHP'
                @if ($program->description)
                    {{-- The SafeHtml cast cleaned this HTML when it was saved. --}}
                    <div class="rich-text">{!! $program->description !!}</div>
                @endif
                PHP)
            ->tryIt('Run `php artisan serve` and open the program that you edited. You see the bold word, the heading and the list. Open the page source. The description is HTML, and it has no `onclick` or `<script>`.')
            ->commit('Show program descriptions as HTML')
            ->docs('https://laravel.com/docs/blade#displaying-unescaped-data', 'Laravel docs: Displaying unescaped data')
            ->docs('https://docs.npmjs.com/cli/v10/commands/npm-ci', 'npm docs: npm ci')
            ->docs('https://laravel.com/docs/vite#running-vite', 'Laravel docs: Running Vite')
            ->check(Check::fileContains('resources/views/programs/show.blade.php', '{!! $program->description !!}')),

        Step::make('Run the checks and open your pull request')
            ->run('php artisan guide:verify safe-html')
            ->explain(<<<'TEXT'
                Run the acceptance test with `guide:verify`. Then run `composer ci:check`. It runs Pint, PHPStan and every test, as GitHub does on your pull request.

                The old tests still pass. A plain text description is safe HTML too: the sanitizer keeps the text and escapes a `&` as `&amp;`.

                In your pull request, tell reviewers that the pull request has a data migration. Each reviewer must run `php artisan migrate` after they check out your branch. Also tell them to run `npm run build`, because the CSS changed.
                TEXT)
            ->think('A reviewer forgets to run `migrate`. Is their program page unsafe, or does it only look wrong? Why?')
            ->tryIt('Run `composer ci:check`. Pint, PHPStan and all the tests pass.')
            ->commit('Fix the checks for safe HTML')
            ->check(Check::testPassed('SafeHtmlTest')),
    ]);
