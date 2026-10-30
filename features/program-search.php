<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

/*
 * The search lives in the controller after step 4, and in the component after step 8.
 * These checks look in both files, so a step stays done after a later step moves the code.
 */
$component = 'resources/views/components/admin/⚡program-list.blade.php';

$eitherContains = static function (string $needle) use ($component): bool {
    foreach (['app/Http/Controllers/Admin/ProgramController.php', 'resources/views/admin/programs/index.blade.php', $component] as $file) {
        if (str_contains((string) @file_get_contents(base_path($file)), $needle)) {
            return true;
        }
    }

    return false;
};

return Feature::make('program-search')
    ->title('Search the program list')
    ->section(Section::Catalog)
    ->level(2)
    ->needs(['programs-admin', 'instant-navigation'])
    ->summary('Staff search the programs by name or summary. You build the search twice: first with a GET form and a controller, then with a Livewire component that shows results while staff type.')
    ->teaches(['Local scopes with parameters', 'GET forms and query strings', 'Livewire components', 'wire:model.live and debounce', 'Computed properties', 'The #[Url] attribute', 'Livewire security', 'Testing Livewire components'])
    ->test('ProgramSearchTest.php')
    ->intro(<<<'TEXT'
        Open the staff list of programs at `/admin/programs`. Today it shows every program on one page. Each season the centre adds camps, clubs, QUARKS sessions and parties, so the list grows every year. Staff need to find "Robotics" without scrolling.

        You build the search **twice**, on purpose. First the traditional way: a form sends `GET /admin/programs?search=robot`, and the controller filters the list. The whole page loads again, and the search lives in the address, so staff can bookmark it or send it to a colleague. Then the reactive way: a small Livewire component shows the results while staff type, with no page load.

        Both ways use **the same backend**: one `search` scope on the `Program` model. The project rule is "controllers do HTTP, services do the work". A controller or a component receives the request, checks the user, calls the code that does the work, and returns a response. Work that changes data goes into a service class in `app/Services`. A search changes nothing. It is a query, so it lives on the model as a scope, next to `published()`. The controller and the component are two front doors to that one scope. When you change the search rule later, you change it in one place.

        This is your first Livewire component, so the guide goes slowly. Each step adds one new idea. Read the guide page "The reactive way" before you start. Keep it open: the table "A Livewire component is a controller that stays on the page" maps each new idea to something that you already know.
        TEXT)
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/7', 'Laravel From Scratch, episode 7: Forms')
    ->docs('/guide/reactive', 'BLSC guide: The reactive way')
    ->docs('https://livewire.laravel.com/docs/4.x/quickstart', 'Livewire docs: Quickstart')
    ->reflect([
        'You built the search twice. Which parts did you write only once? What would you change to also search by category name?',
        'Staff will want to filter by category or by "Draft" next year. Where does each new filter go: the scope, the controller, the component or the view?',
        'Why does the component check the `access-admin` gate in `boot()`, and not only in `mount()`?',
        'Which step was the hardest? What would help the next contributor? Open an issue or a pull request in blsc-guide to improve that step.',
    ])
    ->steps([
        Step::make('Claim the feature and copy in its test')
            ->run('php artisan guide:start program-search')
            ->explain(<<<'TEXT'
                Run the command below. It copies `tests/Feature/ProgramSearchTest.php` into the app.

                Read the test from top to bottom. It has three kinds of rules, one for each part that you build:

                - The first two rules call `Program::search()` directly. They test the shared backend.
                - The next three rules send a GET request with `?search=` in the address. They test the traditional front door, as a browser uses it.
                - The last three rules use `Livewire::test('admin.program-list')`. They test the component without a browser: `set('search', 'robot')` does what typing in the box does.

                The names `search`, `admin.program-list` and `?search=` come from the test. Use them exactly.
                TEXT)
            ->think('The rule "a person who is not staff cannot use the search" tests the page and the component separately. Why is one of them not enough?')
            ->tryIt('Run `php artisan test tests/Feature/ProgramSearchTest.php`. Most rules fail, because `Program::search()` does not exist yet.')
            ->commit('Add the program search acceptance test')
            ->docs('https://laravel.com/docs/http-tests#making-requests', 'Laravel docs: Making requests')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/22', 'Laravel From Scratch, episode 22: How to Get Started Testing Your Code')
            ->check(Check::fileExists('tests/Feature/ProgramSearchTest.php')),

        Step::make('Write the search once, as a scope')
            ->explain(<<<'TEXT'
                Start with the backend, because both front doors need it. Open `app/Models/Program.php` and find the `published` scope. A scope is a piece of a query with a name. `Program::published()->get()` reads better than `Program::where('is_published', true)->get()`, and the rule lives in one place.

                A scope can take a value. Add a `search` scope with a second parameter, `?string $term`. Laravel passes the query first, and your value second: `Program::search('robot')`.

                The scope does three things:

                1. It trims the term. When the term is empty, it returns and adds nothing, so an empty search finds every program.
                2. It looks for the term in `name` or in `summary`. `whereLike('name', "%{$term}%")` finds the term anywhere in the name. `%` means "any text here". `whereLike` ignores upper and lower case on every database that Laravel supports.
                3. It puts the two conditions in a group, inside `where(function ...)`.

                The group is important. Without it, the SQL is `name LIKE ? OR summary LIKE ?`. Then `Program::published()->search('robot')` becomes `is_published = 1 AND name LIKE ? OR summary LIKE ?`, and a draft with "robot" in its summary gets in. The group adds the brackets: `is_published = 1 AND (name LIKE ? OR summary LIKE ?)`.
                TEXT)
            ->think('Is a search a "service" or a "scope"? Look at the project rule in the intro. Does the search change any data?')
            ->hint('Copy the `published` scope. Rename it to `search`, and add `?string $term` after `Builder $query`.')
            ->hint('`trim((string) $term)` turns `null` into an empty string, so one `if` handles both.')
            ->hint('`$query->where(function (Builder $query) use ($term) { ... })` makes the group. Inside it, call `whereLike()` and then `orWhereLike()`.')
            ->code(<<<'PHP'
                /**
                 * Find the programs whose name or summary contains the term, in any case.
                 * An empty term finds every program.
                 *
                 * @param  Builder<Program>  $query
                 */
                #[Scope]
                protected function search(Builder $query, ?string $term): void
                {
                    $term = trim((string) $term);

                    if ($term === '') {
                        return;
                    }

                    $query->where(function (Builder $query) use ($term) {
                        $query->whereLike('name', "%{$term}%")
                            ->orWhereLike('summary', "%{$term}%");
                    });
                }
                PHP)
            ->tryIt(<<<'TEXT'
                Run `php artisan tinker`, then these three lines:

                `App\Models\Program::search('CLUB')->pluck('name')` shows the clubs, although you typed capital letters.
                `App\Models\Program::search('club')->toRawSql()` shows the SQL with the brackets.
                `App\Models\Program::search('')->count()` shows the number of all programs.
                TEXT)
            ->commit('Add a search scope to programs')
            ->docs('https://laravel.com/docs/eloquent#dynamic-scopes', 'Laravel docs: Dynamic scopes')
            ->docs('https://laravel.com/docs/queries#additional-where-clauses', 'Laravel docs: whereLike and other where clauses')
            ->docs('https://laravel.com/docs/queries#logical-grouping', 'Laravel docs: Logical grouping')
            ->check(Check::fileContains('app/Models/Program.php', 'function search')),

        Step::make('Add a search form that sends a GET request')
            ->explain(<<<'TEXT'
                Now the traditional front door. A form can send its data in two ways:

                - **POST** puts the data in the body of the request. Use it when the request changes something, such as "save this program". Laravel asks for a CSRF token, and the browser warns before it sends a POST again.
                - **GET** puts the data in the address, after a `?`: `/admin/programs?search=robot`. This part is the **query string**. Use GET when the request only reads. The address then holds the whole search, so staff can reload it, bookmark it and send it to a colleague.

                A search only reads, so it uses GET. A GET form needs no `@csrf`.

                Open `resources/views/admin/programs/index.blade.php`. Above the table, add `<form method="GET" action="{{ route('admin.programs.index') }}">`. Inside it, put an input with `name="search"`, and a Search button. The browser builds the query string from the `name` of each input. Use the `x-form-field` component, as the other staff forms do.
                TEXT)
            ->think('The browser makes the address `?search=robot` by itself. Which attribute of the input decides the word "search" in it?')
            ->hint('`<x-form-field name="search" label="Search by name or summary" type="search" autocomplete="off" />` makes the label and the input.')
            ->code(<<<'PHP'
                <form method="GET" action="{{ route('admin.programs.index') }}" role="search" class="mb-4 flex max-w-xl items-end gap-3">
                    <div class="flex-1">
                        <x-form-field name="search" label="Search by name or summary" type="search" autocomplete="off" />
                    </div>
                    <april:button type="submit" variant="outline">Search</april:button>
                </form>
                PHP)
            ->tryIt('Log in as `staff@example.com` with the password `password`, and open Programs. Type "club" and press Enter. The address changes to `/admin/programs?search=club`, but the list does not change. The server receives the search, but nothing reads it yet. The next step fixes that.')
            ->commit('Add a search form to the program list')
            ->docs('https://developer.mozilla.org/en-US/docs/Learn_web_development/Extensions/Forms/Sending_and_retrieving_form_data#the_get_method', 'MDN: The GET method')
            ->check(fn (): bool => $eitherContains('name="search"')),

        Step::make('Read the search in the controller')
            ->explain(<<<'TEXT'
                Open `app/Http/Controllers/Admin/ProgramController.php`. Give `index()` a `Request $request` parameter. Laravel fills it with the current request.

                Read the search with `$request->string('search')`. It gives a string object, even when the address has no `search`, so `->trim()->toString()` always works. Do not use `$request->search` or `$request->input('search')`: a visitor can send `?search[]=x`, and then the value is an array, not a string.

                Pass the search to the scope: `Program::search($search)->with(...)`. The rest of the query stays as it is. This is the whole controller part: read the input, call the backend, return the view. That is all the HTTP work that a controller does.

                Pass `$search` to the view too. The view needs it twice:

                - In the input, as `:value="$search"`. After the page loads again, the box still shows what staff typed.
                - In the empty row. "No programs yet." is wrong when there are programs that do not match. Show `No programs match "{{ $search }}".` when the search is not empty.

                No form request here. Any text is a valid search, and the scope sends the term to the database as a bound value, so the text cannot change the SQL.
                TEXT)
            ->think('Staff search for "dinosaur" and nothing matches. What must the empty row say, and why does "No programs yet." confuse them?')
            ->hint('`$search = $request->string(\'search\')->trim()->toString();`')
            ->hint('Return both values: `view(\'admin.programs.index\', [\'search\' => $search, \'programs\' => Program::search($search)->...->get()])`.')
            ->code(<<<'PHP'
                public function index(Request $request): View
                {
                    $search = $request->string('search')->trim()->toString();

                    return view('admin.programs.index', [
                        'search' => $search,
                        'programs' => Program::search($search)
                            ->with('category')
                            ->withCount(['sessions', 'applications', 'inquiries as open_inquiries_count' => fn ($query) => $query->where('status', InquiryStatus::Open)])
                            ->orderBy('name')
                            ->get(),
                    ]);
                }
                PHP)
            ->tryIt(<<<'TEXT'
                Search for "club" again. The list shows only the clubs, and the box still says "club". Copy the address, open a new tab, and paste it. You see the same list. Search for "dinosaur". The table says that no program matches.

                Run `php artisan test tests/Feature/ProgramSearchTest.php`. The scope rules and the three address rules pass. The Livewire rules still fail, because the component does not exist yet.
                TEXT)
            ->commit('Filter the program list by the search')
            ->docs('https://laravel.com/docs/requests#retrieving-input-from-the-query-string', 'Laravel docs: Retrieving input from the query string')
            ->docs('https://laravel.com/docs/requests#retrieving-stringable-input-values', 'Laravel docs: Retrieving stringable input values')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/10', 'Laravel From Scratch, episode 10: Controllers')
            ->check(fn (): bool => $eitherContains('Program::search(')),

        Step::make('Decide if the page needs Livewire')
            ->explain(<<<'TEXT'
                Stop here for a moment. The search works. It has a URL, it works without JavaScript, and an HTTP test covers it. For many pages, this is the end. The project rule is: start with a controller, and add a component only when the user needs it.

                So ask the question from "The reactive way": must the user see a change from the server without leaving the page?

                Here the answer is yes. Staff often do not know the exact name. They type "rob", look, type "robot", look again. With the form, each look costs a full page load and a press on Enter. With results that change while they type, staff find the program in one go. "Results while they type" is the first example in the table "When to choose which".

                Compare it with the program edit form. Staff fill it in and save it once. A page load after the save is fine, so that form stays a controller.
                TEXT)
            ->think('Name one more staff page in this app where results while typing would help, and one where a normal form is better. Why?')
            ->tryIt('Search for "r", then "ro", then "rob" with the form. Count the full page loads in the Network tab of the developer tools: one for each search.')
            ->docs('/guide/reactive', 'BLSC guide: The reactive way')
            ->check(fn (): bool => $eitherContains('Program::search(')),

        Step::make('Create your first Livewire component')
            ->run('php artisan make:livewire admin.program-list')
            ->explain(<<<'TEXT'
                Run the command below. It makes one file: `resources/views/components/admin/⚡program-list.blade.php`. The name maps to the file: the dot is a folder, so `admin.program-list` is the file `program-list` in the folder `admin`. The ⚡ in the file name tells you that the file is a Livewire component and not a plain Blade component. Your editor finds it when you type "program-list".

                Open the file. It has two parts:

                - At the top, between `<?php` and `?>`, is a PHP class: `new class extends Component { ... }`. It is an anonymous class, a class without a name. Livewire knows it by the file name. Properties and methods go in it.
                - Below `?>` is the Blade view of the component. It must have exactly **one** root element, such as one `<div>`. Livewire uses that element to find the component on the page.

                Open `resources/views/pages/settings/⚡profile.blade.php`. It has the same two parts. The settings pages came with the starter kit, and they are Livewire components too.

                Put the component on the page with a tag: add `<livewire:admin.program-list />` to `resources/views/admin/programs/index.blade.php`, between the search form and the table. Replace the quote in the `<div>` of the component with a short text, such as "Live search comes here".
                TEXT)
            ->think('The settings pages live in `resources/views/pages`, and your component lives in `resources/views/components`. What is the difference between a page component and a component on a page?')
            ->hint('The tag uses the same name as the command: `<livewire:admin.program-list />`.')
            ->code(<<<'PHP'
                <?php

                use Livewire\Component;

                new class extends Component
                {
                    //
                };
                ?>

                <div>
                    Live search comes here.
                </div>
                PHP)
            ->tryIt('Reload the program list. Your text shows above the table. Open the page source of the browser (Ctrl+U) and search for `wire:snapshot`. Livewire added it to your root `<div>`. It holds the state of the component.')
            ->commit('Add an empty program list component')
            ->docs('https://livewire.laravel.com/docs/4.x/components#creating-components', 'Livewire docs: Creating components')
            ->docs('https://livewire.laravel.com/docs/4.x/components#how-file-paths-map-to-component-names', 'Livewire docs: How file paths map to component names')
            ->check(Check::all(
                Check::fileExists($component),
                Check::fileContains('resources/views/admin/programs/index.blade.php', '<livewire:admin.program-list'),
            )),

        Step::make('Connect the search box to a property')
            ->explain(<<<'TEXT'
                A component keeps its input in **public properties**. In a controller, the input comes in the request. In a component, it lives in a property, and Livewire keeps it between requests.

                Add `public string $search = '';` to the class.

                Then move the search form from the index page into the `<div>` of the component, in place of your text. Remove the Search button. Add `wire:model.live.debounce.300ms="search"` to the input. Read it from left to right:

                - `wire:model="search"` connects the input to the `$search` property. When staff type, Livewire copies the text into the property.
                - `.live` sends the text to the server while staff type. Without it, Livewire waits for the next request, such as a button click.
                - `.debounce.300ms` waits until staff stop typing for 300 milliseconds. Then it sends one request. Without it, "robotics" sends eight requests, one for each letter.

                Keep the `<form method="GET">` around the input. If JavaScript fails, Enter still sends the GET search from the last steps.

                To see the property change, add `<p>You typed: {{ $search }}</p>` under the input for now. In the view, a public property is a normal variable.
                TEXT)
            ->think('Staff type "robot" fast, then stop. How many requests go to the server with `.debounce.300ms`? How many without `.live`?')
            ->hint('The `x-form-field` component passes extra attributes to the input. So `<x-form-field name="search" ... wire:model.live.debounce.300ms="search" />` works.')
            ->code(<<<'PHP'
                <div>
                    <form method="GET" action="{{ route('admin.programs.index') }}" role="search" class="mb-4 max-w-xl">
                        <x-form-field name="search" label="Search by name or summary" type="search" :value="$search" autocomplete="off" wire:model.live.debounce.300ms="search" />
                    </form>

                    <p>You typed: {{ $search }}</p>
                </div>
                PHP)
            ->tryIt('Reload the program list and type "robot" in the box. "You typed" changes a moment after you stop. The table does not change yet. In the Network tab, filter by "livewire". You see one request after each pause, not one for each letter.')
            ->commit('Bind the search box to the component')
            ->docs('https://livewire.laravel.com/docs/4.x/wire-model#live-updating', 'Livewire docs: Live updating')
            ->docs('https://livewire.laravel.com/docs/4.x/wire-model#customizing-the-debounce', 'Livewire docs: Customizing the debounce')
            ->check(Check::fileContains($component, 'wire:model.live')),

        Step::make('Show the results with a computed property')
            ->explain(<<<'TEXT'
                Now the component shows the list. Add a method `programs()` with the `#[Computed]` attribute. It returns the same query as the controller: `Program::search($this->search)` with the same `with()`, `withCount()` and `orderBy()`. The scope did not change. This is the payoff of the first step: the second front door calls the same backend.

                Why a computed property, and not a public `$programs` property? Every public property travels to the browser and back with each request. A list of programs would make every request large, and the browser could change it. A computed property stays on the server. Livewire runs it at most once in each request and keeps the result for that request. In the view, read it as `$this->programs`.

                Move the whole `<april:data-table>` from the index page into the component, under the form. Change `$programs` to `$this->programs`. Remove the "You typed" line.

                Each row needs a key, so Livewire knows which row is which when the list changes: `:wire:key="'program-'.$program->id"`. Write it with the colon. Livewire reads a plain `wire:key="..."` before April UI reads its tags, and the page breaks with a syntax error.

                The controller has nothing to filter now. `index()` returns `view('admin.programs.index')`, and the index page shows the status message, the New program button and the component. Remove the `Request` parameter and the imports that you do not use. Pint tells you about them.
                TEXT)
            ->think('The computed property runs on every request. What would happen to the page if the program list were a public property instead?')
            ->hint('`use Livewire\Attributes\Computed;` at the top of the file. Copy the import of `InquiryStatus` from the controller too.')
            ->hint('The return type is `Collection<int, Program>` from `Illuminate\Database\Eloquent\Collection`, as in the form editor.')
            ->code(<<<'PHP'
                /**
                 * The programs that match the search, with the numbers that the list shows.
                 *
                 * @return Collection<int, Program>
                 */
                #[Computed]
                public function programs(): Collection
                {
                    return Program::search($this->search)
                        ->with('category')
                        ->withCount(['sessions', 'applications', 'inquiries as open_inquiries_count' => fn ($query) => $query->where('status', InquiryStatus::Open)])
                        ->orderBy('name')
                        ->get();
                }
                PHP)
            ->tryIt(<<<'TEXT'
                Reload the program list and type "robot". The table changes while you type, with no page load.

                Now run `php artisan test tests/Feature/ProgramSearchTest.php`. Three rules fail, such as "staff search the program list through the address". Open `/admin/programs?search=robot`: the list shows every program. The component does not read the address yet. The next step fixes it.
                TEXT)
            ->commit('Show the search results in the component')
            ->docs('https://livewire.laravel.com/docs/4.x/computed-properties#basic-usage', 'Livewire docs: Computed properties')
            ->docs('https://livewire.laravel.com/docs/4.x/computed-properties#performance-advantage', 'Livewire docs: The performance advantage')
            ->check(Check::all(
                Check::fileContains($component, '#[Computed]'),
                Check::fileContains($component, 'Program::search('),
            )),

        Step::make('Keep the search in the address')
            ->explain(<<<'TEXT'
                The traditional search had one big strength: the address held the search. The component lost it. Staff cannot send a link to "the robot programs" any more, and a reload clears the box.

                Add the `#[Url]` attribute above `public string $search`. It does two things:

                - When the page loads, Livewire reads `?search=` from the address and puts it in the property. So the old GET links, and the GET form when JavaScript fails, work again.
                - When the property changes, Livewire changes the address in the browser. It uses the browser history API, as `wire:navigate` does, so the page does not load again.

                When the search is empty, the address has no `?search=`, because an empty string is the start value of the property.
                TEXT)
            ->think('Staff type "robot", copy the address, and send it to a colleague. Which line of your code fills the box on the colleague\'s screen?')
            ->hint('`use Livewire\Attributes\Url;`, then put `#[Url]` on the line above the property.')
            ->code(<<<'PHP'
                #[Url]
                public string $search = '';
                PHP)
            ->tryIt('Type "robot". The address changes to `/admin/programs?search=robot` while you type. Reload the page: the box and the list stay the same. Run the test again. Only the rule "a person who is not staff cannot use the search" still fails.')
            ->commit('Keep the program search in the address')
            ->docs('https://livewire.laravel.com/docs/4.x/url#basic-usage', 'Livewire docs: URL query parameters')
            ->docs('https://livewire.laravel.com/docs/4.x/url#initializing-properties-from-the-url', 'Livewire docs: Initializing properties from the URL')
            ->check(Check::fileContains($component, '#[Url')),

        Step::make('Check the user on every request')
            ->explain(<<<'TEXT'
                The page `/admin/programs` is in the staff route group, so only staff can open it. Is the component safe, then? Not by itself.

                Look at the Network tab again. When staff type, the request does not go to `/admin/programs`. It goes to a Livewire address that starts with `/livewire-` and ends with `/update`. Every component on every page uses that address. Each keystroke that reaches the server is a **new request**, and the component is built again from the snapshot.

                Livewire has two hooks that run at the start:

                - `mount()` runs once, when the page first shows the component.
                - `boot()` runs on **every** request: the first one and each update.

                A check in `mount()` protects only the first request. So add `boot()` and call `Gate::authorize('access-admin')` in it, as the form editor does. A user who is not staff then gets "403 Forbidden" on every request.

                Livewire does help: for update requests, it runs the `auth` and `can` middleware of the page again. But the component must not depend on the page that holds it. A teammate can put it on another page next year. The component guards itself.

                Also remember that a property is user input. The browser sends `$search` with each request, and a user can change it in the developer tools. Here that is safe. The `string` type refuses an array, the scope binds the value, and the search only reads programs that staff may see anyway. For a property that must never change, such as an ID, Livewire has `#[Locked]`.
                TEXT)
            ->think('A user who is not staff copies a Livewire request from the developer tools of a staff member and sends it again. What stops them?')
            ->hint('`use Illuminate\Support\Facades\Gate;` at the top of the file.')
            ->code(<<<'PHP'
                /**
                 * Run on every request, not only the first one. Each update from the browser is a new request.
                 */
                public function boot(): void
                {
                    Gate::authorize('access-admin');
                }
                PHP)
            ->tryIt('Run `php artisan test tests/Feature/ProgramSearchTest.php`. All the rules pass. Then move the gate check into a `mount()` method, and run the test again. It still passes, because the test only builds the component once. Move it back to `boot()`, and read the think question again.')
            ->commit('Authorize every program list request')
            ->docs('https://livewire.laravel.com/docs/4.x/lifecycle-hooks#boot', 'Livewire docs: The boot hook')
            ->docs('https://livewire.laravel.com/docs/4.x/security#configuring-persistent-middleware', 'Livewire docs: Persistent middleware')
            ->docs('https://livewire.laravel.com/docs/4.x/properties#dont-trust-property-values', 'Livewire docs: Do not trust property values')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/17', 'Laravel From Scratch, episode 17: Authorization Using Gates')
            ->check(Check::all(
                Check::fileContains($component, 'function boot'),
                Check::fileContains($component, 'access-admin'),
            )),

        Step::make('Read one Livewire request in the Network tab')
            ->explain(<<<'TEXT'
                A Livewire update is a normal HTTP request. Read one, so the component is not magic.

                Open the program list, open the Network tab, and type "robotics" fast. One request appears, because of the debounce. It is a **POST** to `/livewire-.../update`. It is a POST and not a GET, because it can change the state of the component.

                Click it and open the payload. It is JSON with two keys:

                - `_token` is the CSRF token. Livewire sends it for you, so the CSRF middleware accepts the POST.
                - `components` is a list with one entry for your component. `snapshot` is the state from the last response: `data` holds `search` with its old value, and `memo` holds the name of the component and the page path. `checksum` is a signature. When the snapshot comes back changed, Livewire refuses it. `updates` holds the new value: `{"search": "robotics"}`. `calls` lists the methods to run. Here there is none of yours, only Livewire's own `$commit`.

                Now open the response. It is JSON too: a new `snapshot` with `"search": "robotics"`, and `effects` with the new HTML of the component. Livewire puts the HTML into the page and changes only the parts that are different.

                So the snapshot is signed, but `updates` is free input. That is why the last step checks the user on every request.
                TEXT)
            ->think('The response holds the HTML of the whole component. Is that more or less data than a full page load? What does the browser not need to do again?')
            ->tryIt('Do the steps above. Find `"updates":{"search":"robotics"}` in the payload, and `"search":"robotics"` in the new snapshot of the response. Then type one more letter slowly, and compare the old snapshot of the second request with the new snapshot of the first response. They are the same.')
            ->docs('/guide/reactive', 'BLSC guide: The reactive way')
            ->check(Check::all(
                Check::fileContains($component, 'wire:model.live'),
                Check::fileContains($component, 'function boot'),
            )),

        Step::make('Run the checks and open your pull request')
            ->run('php artisan guide:verify program-search')
            ->explain(<<<'TEXT'
                Read the test once more. You now know what each kind of rule proves:

                - `Program::search()` rules prove the shared backend. They are fast, and they need no HTTP.
                - `$this->get(route('admin.programs.index', ['search' => 'rocket']))` sends a GET request through the routes and middleware, as a browser does. It proves the address works.
                - `Livewire::test('admin.program-list')->set('search', 'robot')->assertSee(...)` builds the component and changes the property, as typing does. `Livewire::withQueryParams()` puts `?search=` in the address for the test, so it proves `#[Url]`.

                Run the acceptance test with `guide:verify`. Then run `composer ci:check`. The old `tests/Feature/Admin/ProgramManagementTest.php` passes without a change: "staff can see every program, including drafts" still opens the list page, and the component draws the table in the first response.

                PHPStan does not read the PHP part of a single-file component, so the tests are your safety net there.
                TEXT)
            ->think('The tests never type into a real browser. What could still break in the browser, and how would you check it by hand?')
            ->tryIt('Run `composer ci:check`. Pint, PHPStan and all the tests pass. In your pull request, add a short screen recording: type a search, reload the page, and open the address in a new tab.')
            ->commit('Fix the checks for the program search')
            ->docs('https://livewire.laravel.com/docs/4.x/testing#testing-properties', 'Livewire docs: Testing properties')
            ->docs('https://livewire.laravel.com/docs/4.x/testing#setting-url-parameters', 'Livewire docs: Setting URL parameters')
            ->docs('https://livewire.laravel.com/docs/4.x/testing#testing-authorization', 'Livewire docs: Testing authorization')
            ->check(Check::testPassed('ProgramSearchTest')),
    ]);
