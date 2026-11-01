<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('programs-admin')
    ->title('Staff: manage programs')
    ->section(Section::Foundation)
    ->level(3)
    ->needs(['programs', 'staff-roles'])
    ->summary('Staff create, edit and delete programs and their sessions. Every later staff page copies this pattern.')
    ->teaches(['Service classes', 'Form requests', 'Resource controllers', 'Route groups and middleware', 'Nested routes', 'Route model binding', 'Blade components'])
    ->test('Admin/ProgramManagementTest.php')
    ->intro(<<<'TEXT'
        The seeder put the Fall 2026 programs in the database. But staff cannot add a camp or change a price without a developer. In this feature, you build the staff pages for programs and their sessions. When you finish, a staff member clicks "Programs" in the sidebar, sees every program with its drafts, and adds, edits and deletes programs and sessions.

        This is the first staff page of the site. Every later staff page copies its pattern, so build it with care. A request to a staff page goes through three classes. Each class has one job:

        1. A **form request** checks that the user is staff, cleans the input, and validates it.
        2. The **controller** takes the valid data and gives it to a service. Then it returns a view or a redirect.
        3. The **service** does the work. It saves, changes or deletes the data, and it applies the business rules.

        This is the rule of the whole project: **controllers do HTTP, services do the work**. A controller never calls `create()`, `update()`, `delete()` or `save()` on a model. Every write goes through a class in `app/Services`, even a plain save.

        Why? A controller only knows about one web request. But the same work often has more than one door: a staff page, a Livewire component, a command or a scheduled job. When the work is in a service, every door calls the same code, and each business rule lives in one place. A test can also call the service directly, without a web request.

        A resource controller has one method for each job: list, show the form to create, save, show the form to edit, update and delete. One line in `routes/web.php` makes all of the routes. All staff routes sit in one group with the `can:access-admin` middleware, so no staff page can forget the check.
        TEXT)
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/10', 'Laravel From Scratch, episode 10: Controllers')
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/12', 'Laravel From Scratch, episode 12: Form Request Classes')
    ->docs('https://laravel.com/docs/controllers#resource-controllers', 'Laravel docs: Resource controllers')
    ->docs('https://laravel.com/docs/controllers#method-injection', 'Laravel docs: Method injection')
    ->docs('/guide/services', 'BLSC guide: Controllers and services')
    ->reflect([
        'The controller never calls `create()` or `delete()` on a model. Next year, staff want an email to the manager each time a program is deleted. Which file changes?',
        'The route group checks `can:access-admin`, and each form request checks it again. Why both?',
        'Staff type a price in dollars, but the database stores cents. Which class turns one into the other? Why there?',
        'Which step was the hardest? What would help the next contributor? Open an issue or a pull request in blsc-guide to improve that step.',
    ])
    ->steps([
        Step::make('Claim the feature and copy in its test')
            ->run('php artisan guide:start programs-admin')
            ->explain(<<<'TEXT'
                You start with the test. Run the command below. It copies `tests/Feature/Admin/ProgramManagementTest.php` into the app. The `Admin` folder keeps the tests of the staff pages together.

                Read the whole test before you write code. It names the routes: `admin.programs.*` for programs, `admin.programs.sessions.store` to add a session, and `admin.sessions.*` to change one. It also names the two services, `ProgramService` and `ProgramSessionService`. Later features call them, so keep the names.

                Look at the two rules that call a service directly, without a request. They prove that the work does not depend on a web page.
                TEXT)
            ->think('Find the rule "staff can add a session with prices in dollars". It sends `135.50` and expects `13550`. Which class must turn dollars into cents?')
            ->tryIt('Run `php artisan test tests/Feature/Admin/ProgramManagementTest.php`. All the rules fail, because the routes and the services do not exist yet.')
            ->commit('Add the program management acceptance test')
            ->docs('https://laravel.com/docs/http-tests', 'Laravel docs: HTTP tests')
            ->check(Check::fileExists('tests/Feature/Admin/ProgramManagementTest.php')),

        Step::make('Write the program service')
            ->run('php artisan make:class Services/ProgramService')
            ->explain(<<<'TEXT'
                Start with the class that does the work. Then the controller, in a later step, has nothing to do but call it.

                Run the command below. It makes an empty class in `app/Services`. Remove the empty constructor: the service needs nothing to start. Add three methods:

                - `create(array $data): Program` makes a program from the valid data and returns it.
                - `update(Program $program, array $data): void` changes a program.
                - `delete(Program $program): void` deletes a program. The foreign key on `program_sessions` has `cascadeOnDelete()`, so the database deletes its sessions too.

                Each method takes a model or an array. None of them knows about the request. So a test, a command or a later Livewire component can call the same methods.

                The methods are very short today. That is all right. When the centre wants a new rule, such as "log every change to a program", it goes in the service. Then the staff page and every other caller follow the rule, and no controller changes.
                TEXT)
            ->think('`create()` takes an array, not a `ProgramRequest`. Why is that useful when a command or a test creates a program?')
            ->hint('Each `$data` parameter is an array. Add `@param  array<string, mixed>  $data` to the docblock, so PHPStan knows the type of the keys and values.')
            ->hint('`Program::create($data)` makes and saves a program from the fillable fields in `$data`.')
            ->code(<<<'PHP'
                class ProgramService
                {
                    /**
                     * @param  array<string, mixed>  $data  the validated fields of the program form
                     */
                    public function create(array $data): Program
                    {
                        return Program::create($data);
                    }

                    /**
                     * @param  array<string, mixed>  $data  the validated fields of the program form
                     */
                    public function update(Program $program, array $data): void
                    {
                        $program->update($data);
                    }

                    /**
                     * Delete a program. The database deletes its sessions too.
                     */
                    public function delete(Program $program): void
                    {
                        $program->delete();
                    }
                }
                PHP)
            ->docs('/guide/services', 'BLSC guide: Controllers and services')
            ->tryIt('Run `php artisan test tests/Feature/Admin/ProgramManagementTest.php --filter="program service"`. The rule "the program service saves, changes and deletes a program without a request" passes. Then, in `php artisan tinker`, run `app(App\Services\ProgramService::class)`. Laravel makes the service for you.')
            ->commit('Add the program service')
            ->docs('https://laravel.com/docs/container#zero-configuration-resolution', 'Laravel docs: Zero configuration resolution')
            ->check(Check::classExists('App\Services\ProgramService')),

        Step::make('Write the program form request')
            ->run('php artisan make:request ProgramRequest')
            ->explain(<<<'TEXT'
                A form request holds the rules of one form. Laravel runs it before the controller. When the data is not valid, Laravel sends the user back to the form with the errors, and the controller never runs. The create form and the edit form send the same fields, so one request serves both.

                Make it with the command below. Fill in three methods:

                1. `authorize()` asks the gate: `$this->user()?->can('access-admin') ?? false`.
                2. `prepareForValidation()` cleans the input before the rules run. When staff leave the slug empty, make it from the name with `Str::slug()`. Turn `is_published` into a real boolean with `$this->boolean('is_published')`: a checkbox that is not ticked sends nothing.
                3. `rules()` lists the rules. The type must be a case of `ProgramType`. The name and the summary are required, at most 255 characters. The description is optional. Both ages are optional whole numbers from 0 to 99, and the maximum is not lower than the minimum. The slug is required, uses only letters, numbers, dashes and underscores, and is unique in `programs`.

                The unique rule needs care. On the edit form, the program already has its slug. A plain `unique` rule finds that slug in the table and refuses it. `Rule::unique('programs', 'slug')->ignore($program)` leaves out the program that staff edit. On the create form there is no program yet, and `ignore(null)` leaves out nothing.
                TEXT)
            ->think('The staff route group will check the gate too. Why does the form request check it again?')
            ->hint('Get the program that staff edit with `$this->route(\'program\')`. On the create form it is `null`.')
            ->hint('`Rule::enum(ProgramType::class)` accepts only the values of the enum. `gte:age_min` means "greater than or equal to the field age_min".')
            ->hint('In `prepareForValidation()`, `$this->input(\'slug\') ?: $this->input(\'name\', \'\')` takes the slug, or the name when the slug is empty.')
            ->code(<<<'PHP'
                protected function prepareForValidation(): void
                {
                    $this->merge([
                        'slug' => Str::slug($this->input('slug') ?: $this->input('name', '')),
                        'is_published' => $this->boolean('is_published'),
                    ]);
                }

                public function rules(): array
                {
                    /** @var Program|null $program */
                    $program = $this->route('program');

                    return [
                        'type' => ['required', Rule::enum(ProgramType::class)],
                        'name' => ['required', 'string', 'max:255'],
                        'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('programs', 'slug')->ignore($program)],
                        // summary, description, age_min, age_max and is_published
                    ];
                }
                PHP)
            ->tryIt('Run `php artisan test tests/Feature/Admin/ProgramManagementTest.php`. The rules still fail, because no route uses the request yet. That is correct. You add the routes in two steps.')
            ->commit('Add the program form request')
            ->docs('https://laravel.com/docs/validation#form-request-validation', 'Laravel docs: Form request validation')
            ->docs('https://laravel.com/docs/validation#preparing-input-for-validation', 'Laravel docs: Preparing input for validation')
            ->docs('https://laravel.com/docs/validation#rule-unique', 'Laravel docs: The unique rule')
            ->docs('https://laravel.com/docs/validation#rule-enum', 'Laravel docs: The enum rule')
            ->check(Check::classExists('App\Http\Requests\ProgramRequest')),

        Step::make('Make the program controller')
            ->run('php artisan make:controller Admin/ProgramController --resource --model=Program')
            ->explain(<<<'TEXT'
                The command below makes a resource controller in `app/Http/Controllers/Admin`. The public site already has a `ProgramController`. The `Admin` namespace keeps the two apart. The command asks no questions, because `--model` names the model.

                Staff do not need a `show` page: the edit page shows everything. Delete `show()`. Then fill in the other methods. Each one is a few lines:

                - `index()` lists every program, drafts too, with the number of sessions: `Program::withCount('sessions')`. Sort by type, then by name.
                - `create()` shows the form with a new, unsaved program: a published club by default.
                - `store()` takes the `ProgramRequest` and the `ProgramService`. It gives the valid data to `$programs->create()`, and redirects to the edit page of the new program with the status "Program created."
                - `edit()` loads the sessions and shows the form.
                - `update()` calls `$programs->update()`, and redirects back to the edit page with "Program saved."
                - `destroy()` calls `$programs->delete()`, and redirects to the list with "Program deleted."

                Ask for the service as a parameter: `store(ProgramRequest $request, ProgramService $programs)`. Laravel sees the type, makes a `ProgramService`, and passes it in. You never write `new ProgramService`.

                Change the `Request` parameter of `store()` and `update()` to `ProgramRequest`. Then Laravel runs your rules before the method. The command writes no return types. Add them: `View` for the pages and `RedirectResponse` for the redirects. PHPStan asks for them.
                TEXT)
            ->think('The controller never calls `Program::create()`. What does it still do? Make a list of its jobs.')
            ->hint('`$request->validated()` returns only the fields in your rules. Pass it to the service.')
            ->hint('`to_route(\'admin.programs.edit\', $program)->with(\'status\', \'Program created.\')` redirects and keeps the message for the next page.')
            ->code(<<<'PHP'
                public function store(ProgramRequest $request, ProgramService $programs): RedirectResponse
                {
                    $program = $programs->create($request->validated());

                    return to_route('admin.programs.edit', $program)->with('status', 'Program created.');
                }
                PHP)
            ->commit('Add the program controller')
            ->docs('https://laravel.com/docs/controllers#actions-handled-by-resource-controllers', 'Laravel docs: Resource controller methods')
            ->docs('https://laravel.com/docs/eloquent-relationships#counting-related-models', 'Laravel docs: Counting related models')
            ->docs('https://laravel.com/docs/responses#redirecting-named-routes', 'Laravel docs: Redirecting to named routes')
            ->docs('https://laravel.com/docs/session#flash-data', 'Laravel docs: Flash data')
            ->check(Check::all(
                Check::classExists('App\Http\Controllers\Admin\ProgramController'),
                Check::fileContains('app/Http/Controllers/Admin/ProgramController.php', 'ProgramService'),
            )),

        Step::make('Add the staff routes')
            ->explain(<<<'TEXT'
                All staff pages share three things: the URL starts with `/admin`, the route name starts with `admin.`, and only staff can open them. A route group gives these to every route inside it.

                Open `routes/web.php`. Add a group at the end, before `require`:

                - `middleware(['auth', 'verified', 'can:access-admin'])`. `auth` sends a guest to the login page. `can:access-admin` asks your gate, and shows "403 Forbidden" to a parent.
                - `prefix('admin')` puts `/admin` before each URL.
                - `name('admin.')` puts `admin.` before each route name.

                Inside the group, one line makes the routes of the controller: `Route::resource('programs', Admin\ProgramController::class)->except('show')`.

                Import the namespace, not each class: `use App\Http\Controllers\Admin;`. Then `Admin\ProgramController` is clear next to the public `ProgramController`.
                TEXT)
            ->think('A contributor adds a new staff page next year and forgets to check the gate in the controller. Is the page open to parents?')
            ->hint('Chain the three methods on `Route::middleware([...])`, then call `->group(function () { ... })`.')
            ->code(<<<'PHP'
                Route::middleware(['auth', 'verified', 'can:access-admin'])
                    ->prefix('admin')
                    ->name('admin.')
                    ->group(function () {
                        Route::resource('programs', Admin\ProgramController::class)->except('show');
                    });
                PHP)
            ->tryIt('Run `php artisan route:list --name=admin`. You see six routes, from `admin.programs.index` to `admin.programs.destroy`. Log in as `test@example.com` and open http://localhost:8000/admin/programs. You see "403 Forbidden".')
            ->commit('Add the staff routes for programs')
            ->docs('https://laravel.com/docs/routing#route-groups', 'Laravel docs: Route groups')
            ->docs('https://laravel.com/docs/authorization#via-middleware', 'Laravel docs: Authorizing via middleware')
            ->docs('https://laravel.com/docs/controllers#restful-partial-resource-routes', 'Laravel docs: Partial resource routes')
            ->check(Check::route('admin.programs.index', 'admin.programs.create', 'admin.programs.edit', 'admin.programs.destroy')),

        Step::make('Make the shared staff components')
            ->explain(<<<'TEXT'
                Every staff page needs the same three parts. Make each one a Blade component in `resources/views/components/admin`, so the next staff page uses `<x-admin.status />` and does not copy HTML.

                1. `status.blade.php` shows the status message after a redirect, such as "Program saved.". Show an `<april:alert>` only when `session('status')` has a value.
                2. `delete-button.blade.php` is a delete form with a confirm dialog. It takes the `action` URL and a `title`, such as "Delete Robotics Club?", and an optional `description`. The form sends `DELETE` with `@method('DELETE')`. The button opens an `<april:alert-dialog>`, and only the button in the dialog sends the form. Staff cannot delete by one wrong click.
                3. `textarea-field.blade.php` is a labelled textarea with its error, like the starter kit's `<x-form-field>`. You need it because of a bug in April UI 1.3.6: `<april:textarea>` shows no content, so an edit form loses the old text. Write a plain `<textarea>` with the April UI classes. When April UI fixes the bug, only this file changes. The README asks you to report April UI bugs: check that the bug has an issue in yungifez/april-ui, and open one if not.

                Declare the inputs of each component with `@props`. A value with a default, such as `'description' => 'This cannot be undone.'`, is optional.
                TEXT)
            ->think('The textarea bug is in a package. Why is a small component of your own better than a fix in every form?')
            ->hint('Look at `resources/views/components/form-field.blade.php`. It has `@props`, a label, the input and `@error`. The textarea field has the same shape.')
            ->hint('For the textarea classes, copy the class list from `vendor/yungifez/april-ui/resources/views/components/textarea.blade.php`.')
            ->hint('The dialog has a `<slot:trigger>` with the red button, and a `<slot:content>` with a header, a cancel button and `<april:alert-dialog-action type="submit">`.')
            ->code(<<<'BLADE'
                {{-- resources/views/components/admin/delete-button.blade.php --}}
                @props([
                    'action',
                    'title',
                    'description' => 'This cannot be undone.',
                ])

                <form method="POST" action="{{ $action }}">
                    @csrf
                    @method('DELETE')

                    <april:alert-dialog>
                        <slot:trigger>
                            <april:button variant="destructive" size="sm">{{ $slot->isEmpty() ? 'Delete' : $slot }}</april:button>
                        </slot:trigger>
                        <slot:content>
                            <april:alert-dialog-header>
                                <slot:title>{{ $title }}</slot:title>
                                <slot:description>{{ $description }}</slot:description>
                            </april:alert-dialog-header>
                            <april:alert-dialog-footer>
                                <april:alert-dialog-cancel>Cancel</april:alert-dialog-cancel>
                                <april:alert-dialog-action type="submit">Delete</april:alert-dialog-action>
                            </april:alert-dialog-footer>
                        </slot:content>
                    </april:alert-dialog>
                </form>
                BLADE)
            ->commit('Add the shared staff components')
            ->docs('https://laravel.com/docs/blade#anonymous-components', 'Laravel docs: Anonymous components')
            ->docs('https://laravel.com/docs/blade#data-properties-attributes', 'Laravel docs: Data properties and attributes')
            ->docs('https://laravel.com/docs/routing#form-method-spoofing', 'Laravel docs: Form method spoofing')
            ->check(Check::all(
                Check::view('components.admin.status'),
                Check::view('components.admin.delete-button'),
                Check::view('components.admin.textarea-field'),
            )),

        Step::make('Make the program pages and the sidebar link')
            ->explain(<<<'TEXT'
                Make four views in `resources/views/admin/programs`. Use the staff layout, `<x-layouts::app>`, with a `title`.

                1. `index.blade.php`: `<x-admin.status />`, a "New program" button, and an `<april:data-table>` with the name (a link to the edit page), the type label, the number of sessions and a "Published" or "Draft" badge. When there are no programs, say "No programs yet."
                2. `form.blade.php`: only the fields, with `@csrf` at the top. The name, the slug ("leave empty to fill from the name"), the type as a select of `ProgramType::cases()`, the two ages, the summary, the description with `<x-admin.textarea-field>`, and an "is published" checkbox.
                3. `create.blade.php`: a form that posts to `admin.programs.store`, includes `form`, and has "Create program" and "Cancel" buttons.
                4. `edit.blade.php`: `<x-admin.status />`, a form that sends `PUT` to `admin.programs.update`, includes `form`, and has a "Save program" button. Add a "View on site" link when the program is published. Then a "Delete this program" section with `<x-admin.delete-button>`. Say that it also deletes all of its sessions.

                Create and edit share one `form`, so the two forms cannot drift apart. Use `old('name', $program->name)` for each value. After a validation error, the form shows what staff typed, not the saved value.

                A checkbox that is not ticked sends nothing. Put `<input type="hidden" name="is_published" value="0">` before it. Then the browser always sends an answer.

                Last, the sidebar. Open `resources/views/layouts/app.blade.php`. Add a "Staff" group between the Workspace and the Account groups, with a "Programs" link to `admin.programs.index`. Give the link `wire:navigate`, like the other sidebar links: every new link to a page of the site uses it, so the page opens without a full reload (the Instant navigation feature explains how). Wrap the group in `@can('access-admin') ... @endcan`, so parents do not see it. Mark the link active on all program and session pages: `request()->routeIs('admin.programs.*', 'admin.sessions.*')`.
                TEXT)
            ->think('The sidebar hides the Staff group from parents. Why do the routes still need the `can:access-admin` middleware?')
            ->hint('Copy the Workspace group in `layouts/app.blade.php` for the Staff group. Keep `wire:navigate` on the link. The icon can be `<x-lucide-flask-conical class="size-4" />`.')
            ->hint('For the type select, loop over `App\Enums\ProgramType::cases()`. Mark the current type with `@selected(old(\'type\', $program->type?->value) === $type->value)`.')
            ->hint('For the checkbox, `<april:checkbox id="is_published" name="is_published" value="1" :checked="(bool) old(\'is_published\', $program->is_published)" />`.')
            ->code(<<<'BLADE'
                {{-- resources/views/admin/programs/edit.blade.php --}}
                <x-layouts::app :title="$program->name">
                    <x-admin.status />

                    <div class="max-w-3xl space-y-12">
                        <form method="POST" action="{{ route('admin.programs.update', $program) }}" class="space-y-6">
                            @method('PUT')
                            @include('admin.programs.form')

                            <april:button type="submit">Save program</april:button>
                        </form>

                        {{-- The Sessions section goes here in a later step. --}}

                        <section class="space-y-3 rounded-lg border border-destructive/40 p-4">
                            <h2 class="font-semibold">Delete this program</h2>
                            <p class="text-sm text-muted-foreground">This also deletes all of its sessions.</p>
                            <x-admin.delete-button :action="route('admin.programs.destroy', $program)" title="Delete {{ $program->name }}?">
                                Delete program
                            </x-admin.delete-button>
                        </section>
                    </div>
                </x-layouts::app>
                BLADE)
            ->tryIt('Log in as `staff@example.com` with the password `password`. Click "Programs" in the sidebar. Create a draft program with an empty slug. The slug fills in from the name. Make the maximum age lower than the minimum age, and save. You see an error, and the form keeps what you typed. Delete the program.')
            ->commit('Add the program pages')
            ->docs('https://laravel.com/docs/blade#including-subviews', 'Laravel docs: Including subviews')
            ->docs('https://laravel.com/docs/validation#repopulating-forms', 'Laravel docs: Repopulating forms')
            ->docs('https://laravel.com/docs/authorization#via-blade-templates', 'Laravel docs: Authorizing in Blade')
            ->check(Check::all(
                Check::view('admin.programs.form'),
                Check::view('admin.programs.edit'),
                Check::fileContains('resources/views/layouts/app.blade.php', "route('admin.programs.index')"),
            )),

        Step::make('Write the session service and form request')
            ->run('php artisan make:class Services/ProgramSessionService')
            ->explain(<<<'TEXT'
                Sessions follow the same pattern. Start again with the work and the rules.

                Make the service with the command below. Give it three methods:

                - `create(Program $program, array $data): ProgramSession` adds a session to the program with `$program->sessions()->create($data)`. Eloquent fills in `program_id`. This is why `program_id` is not in `#[Fillable]`: a session is always made through its program.
                - `update(ProgramSession $session, array $data): void` and `delete(ProgramSession $session): void`.

                Later features add rules here. For example, the Registrations feature stops staff from deleting a session that has registrations. That rule goes in `delete()`, not in the controller.

                Then run `php artisan make:request ProgramSessionRequest`. `authorize()` asks the gate, as in `ProgramRequest`. The rules: the label is required. The first day is a required date, and the last day is an optional date on or after it. The price is required, at least 0, with at most two decimals. The member price is optional, with the same rules. The capacity is an optional whole number of at least 1. The registration times are optional, and it closes after it opens.

                Staff type prices in dollars, such as `150.00`. The table stores cents. Add a `sessionData()` method to the request. It returns the valid data without `price` and `member_price`, and with `price_cents` and `member_price_cents` instead. An empty member price becomes `null`.
                TEXT)
            ->think('Why does the dollars-to-cents work go in the form request, and not in the service? Who else would call the service with cents?')
            ->hint('`$this->safe()->except([\'price\', \'member_price\'])` returns the valid data without the two price fields.')
            ->hint('`(int) round(((float) $dollars) * 100)` turns "135.50" into `13550`. Without `round()`, `0.29 * 100` gives `28.999999999999996`, and `(int)` cuts it to `28`.')
            ->hint('`decimal:0,2` allows 0, 1 or 2 decimals. `after_or_equal:starts_on` compares two fields.')
            ->code(<<<'PHP'
                /**
                 * Get the validated data with dollar amounts converted to cents.
                 *
                 * @return array<string, mixed>
                 */
                public function sessionData(): array
                {
                    $data = $this->safe()->except(['price', 'member_price']);

                    $data['price_cents'] = $this->toCents($this->validated('price'));
                    $data['member_price_cents'] = filled($this->validated('member_price'))
                        ? $this->toCents($this->validated('member_price'))
                        : null;

                    return $data;
                }

                private function toCents(int|float|string $dollars): int
                {
                    return (int) round(((float) $dollars) * 100);
                }
                PHP)
            ->tryIt('Run `php artisan test tests/Feature/Admin/ProgramManagementTest.php --filter=service`. Both service rules pass.')
            ->commit('Add the session service and form request')
            ->docs('https://laravel.com/docs/validation#working-with-validated-input', 'Laravel docs: Working with validated input')
            ->docs('https://laravel.com/docs/validation#rule-decimal', 'Laravel docs: The decimal rule')
            ->docs('https://laravel.com/docs/eloquent-relationships#the-create-method', 'Laravel docs: The create method on a relationship')
            ->check(Check::all(
                Check::classExists('App\Services\ProgramSessionService'),
                Check::classExists('App\Http\Requests\ProgramSessionRequest'),
            )),

        Step::make('Make the session controller and nested routes')
            ->run('php artisan make:controller Admin/ProgramSessionController --resource --model=ProgramSession --parent=Program')
            ->explain(<<<'TEXT'
                A session belongs to a program. So the address to add one names the program: `/admin/programs/{program}/sessions/create`. These are nested routes.

                To edit a session, the session id is enough. `/admin/programs/3/sessions/7/edit` repeats what the session already knows. Shallow routes leave the program out where the session id is enough: `/admin/sessions/7/edit`. Only `create` and `store` keep the program.

                Make the controller with the command below. `--parent=Program` adds the program to each method. Then fix the methods for shallow routes, and add the return types:

                - Delete `index()` and `show()`. The program edit page lists its sessions.
                - `create(Program $program)` and `store(ProgramSessionRequest $request, Program $program, ProgramSessionService $sessions)` keep the program.
                - `edit()`, `update()` and `destroy()` take only `ProgramSession $session`. Get the program from `$session->program`.

                Each method that saves calls the service with `$request->sessionData()`. Each one redirects to the edit page of the program, with "Session added.", "Session saved." or "Session deleted."

                Add the routes in the staff group: `Route::resource('programs.sessions', Admin\ProgramSessionController::class)->shallow()->except(['index', 'show'])`.
                TEXT)
            ->think('Compare `/admin/programs/3/sessions/7/edit` with `/admin/sessions/7/edit`. What can go wrong with the long address when session 7 belongs to program 4?')
            ->hint('The parameter names must match the route. `programs.sessions` makes the parameters `{program}` and `{session}`. So the controller uses `ProgramSession $session`, not `$programSession`.')
            ->hint('In `destroy()`, keep `$program = $session->program` before you delete. After the delete, you still need it for the redirect.')
            ->code(<<<'PHP'
                public function store(ProgramSessionRequest $request, Program $program, ProgramSessionService $sessions): RedirectResponse
                {
                    $sessions->create($program, $request->sessionData());

                    return to_route('admin.programs.edit', $program)->with('status', 'Session added.');
                }
                PHP)
            ->tryIt('Run `php artisan route:list --name=sessions`. Compare the URLs of the create route and the edit route.')
            ->commit('Add the session controller and routes')
            ->docs('https://laravel.com/docs/controllers#restful-nested-resources', 'Laravel docs: Nested resources')
            ->docs('https://laravel.com/docs/controllers#shallow-nesting', 'Laravel docs: Shallow nesting')
            ->check(Check::all(
                Check::route('admin.programs.sessions.create', 'admin.sessions.edit', 'admin.sessions.destroy'),
                Check::fileContains('app/Http/Controllers/Admin/ProgramSessionController.php', 'ProgramSessionService'),
            )),

        Step::make('Make the session pages')
            ->explain(<<<'TEXT'
                Make three views in `resources/views/admin/sessions`, as you did for programs: `form`, `create` and `edit`.

                The form has the label, the first and the last day (`type="date"`), the schedule, the price and the member price in dollars (`type="number" step="0.01"`), the number of spots, and the two registration times (`type="datetime-local"`). Write a short help text in each label, such as "Last day (leave empty for one day)".

                The form shows dollars, but the model holds cents. Show `$session->price_cents / 100` when the session exists. A date input needs the value as `2026-10-06`: use `toDateString()`. A `datetime-local` input needs `2026-09-01T09:00`: use `format('Y-m-d\TH:i')`.

                The edit page has "Save session", "Back to program" and a delete button.

                Last, list the sessions on the program edit page. Add a "Sessions" section between the form and the delete section: an "Add session" button and a table with the label (a link to the edit page), the dates, the price and the spots. When there are no sessions, say "No sessions yet."
                TEXT)
            ->think('A new session has no `price_cents` yet. What does `$session->price_cents / 100` do then? How does `$session->exists` help?')
            ->hint('The `?->` operator handles a new session without dates: `$session->starts_on?->toDateString()`.')
            ->hint('The edit page has a `$program` and a `$session`. Give both from the controller: `\'program\' => $session->program`.')
            ->code(<<<'BLADE'
                <div class="grid gap-6 md:grid-cols-3">
                    <x-form-field name="price" label="Price ($)" type="number" step="0.01" min="0" :value="old('price', $session->exists ? $session->price_cents / 100 : null)" required />
                    <x-form-field name="member_price" label="Member price ($)" type="number" step="0.01" min="0" :value="old('member_price', $session->member_price_cents === null ? null : $session->member_price_cents / 100)" />
                    <x-form-field name="capacity" label="Spots" type="number" min="1" :value="old('capacity', $session->capacity)" />
                </div>
                BLADE)
            ->tryIt('Log in as `staff@example.com`. Open a club, and click "Add session". Add "Winter 2027" with the price `150` and the member price `135.50`. On the program page, the table shows "$150.00". Open the session again. The form shows `135.5`. Delete the session.')
            ->commit('Add the session pages')
            ->docs('https://developer.mozilla.org/en-US/docs/Web/HTML/Reference/Elements/input/datetime-local', 'MDN: The datetime-local input')
            ->check(Check::all(
                Check::view('admin.sessions.form'),
                Check::view('admin.sessions.edit'),
                Check::fileContains('resources/views/admin/programs/edit.blade.php', 'admin.programs.sessions.create'),
            )),

        Step::make('Run the checks and open your pull request')
            ->run('php artisan guide:verify programs-admin')
            ->explain(<<<'TEXT'
                Run the acceptance test with `guide:verify`. Then run `composer ci:check`. It runs Pint, PHPStan and every test, as GitHub does on your pull request.

                Check the rule of the project yourself. Search the two admin controllers for `::create(`, `->update(`, `->delete(` and `->save(`. Each write must be a call to a service, such as `$programs->delete($program)`.

                A good test fails when the rule is broken. Prove it for one rule. Remove `'can:access-admin'` from the route group, and run the test again. The rule "users who are not staff cannot manage programs" fails. The rule about sessions still passes, because `ProgramSessionRequest` checks the gate too. Put the middleware back.
                TEXT)
            ->think('The next contributor builds the staff pages for categories. Which files of this feature do they copy? Which do they reuse without a copy?')
            ->tryIt('Run `composer ci:check`. Pint, PHPStan and all the tests pass.')
            ->commit('Fix the checks for the program staff pages')
            ->check(Check::testPassed('ProgramManagementTest')),
    ]);
