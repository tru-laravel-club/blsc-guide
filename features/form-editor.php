<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('form-editor')
    ->title('Form editor for staff')
    ->section(Section::Forms)
    ->level(3)
    ->needs(['submissions', 'programs-admin', 'program-search'])
    ->summary('Staff add, order and archive fields, then publish a new version of the form.')
    ->teaches(['Livewire components', 'Lifecycle hooks', 'Computed properties', 'wire:sort', 'Updating pivot rows', 'Closure validation rules', 'JSON where clauses'])
    ->test('FormEditorTest.php')
    ->intro(<<<'TEXT'
        Staff can build the field library, and a form can publish versions. But staff still need tinker to put fields in a form or to publish. In this feature, you give them a form editor.

        Staff create a form with a name, a web address and an email for notices. Then the edit page shows the **questions**: the fields of the form, in order. Staff add a field from the library, drag the fields into a new order, tick "Required", and remove a field. Each click changes the draft in the `form_field` table at once. Nothing changes for visitors.

        The page shows "Unpublished changes" while the draft differs from the latest version. Staff click Publish when they are ready. That calls `Form::publish()` from the last feature.

        The questions part uses **Livewire**. Each click runs a PHP method on the server, and Livewire updates the page without a full reload. Livewire is a good fit for a page with many small changes: staff must see each change at once, and a page load after every click is slow. The details of the form are a plain HTML form, as in the other staff pages.

        You built your first component in "Search the program list". Open `resources/views/components/admin/⚡program-list.blade.php` next to this guide. The form editor uses the same parts: one file with a PHP class at the top and Blade below, a gate check in `boot()`, a public property bound with `wire:model`, and a `#[Computed]` list. Three ideas are new here, and the steps introduce them one at a time: **methods that a button calls** with `wire:click`, a **model property** that the tag passes in, and **wire:sort** for drag and drop.

        Two more parts finish the forms phase. A field that has answers cannot change its type: an old answer "Grade 3" does not fit a date field. And a program can use a form, with a new `form_id` on `programs`. The sign-up features read `Program::form()` later.
        TEXT)
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/16', 'Laravel From Scratch, episode 16: Eloquent Relationships')
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/17', 'Laravel From Scratch, episode 17: Authorization Using Gates')
    ->docs('https://livewire.laravel.com/docs/4.x/components', 'Livewire docs: Components')
    ->docs('https://livewire.laravel.com/docs/4.x/wire-sort', 'Livewire docs: wire:sort')
    ->reflect([
        'A staff member removes a question by mistake. What can they do? What happens to the answers that people already gave to it?',
        'Why does the component check the `access-admin` gate in `boot()`, and not only in `mount()`?',
        'A field has answers, and staff want it to be a date instead of text. What do you tell them to do? What happens to the old answers?',
        'Which step was the hardest? What would help the next contributor? Open an issue or a pull request in blsc-guide to improve that step.',
    ])
    ->steps([
        Step::make('Claim the feature and copy in its test')
            ->run('php artisan guide:start form-editor')
            ->explain(<<<'TEXT'
                Run the command below. It copies `tests/Feature/FormEditorTest.php` into the app.

                Most rules test the Livewire component directly with `Livewire::test('admin.form-editor', ['form' => $form])`. Then they `set()` a property and `call()` a method, as a click in the browser does. `Livewire::actingAs()` signs in a user first.

                Look at the rule "a person who is not staff cannot use the editor". It does not open a page. It talks to the component. A Livewire component is a public endpoint, so it must check who is calling.
                TEXT)
            ->think('The rule "staff reorder the fields" calls `sort` with a field ID and the number `0`. Where do these two values come from in the browser?')
            ->tryIt('Run `php artisan test tests/Feature/FormEditorTest.php`. All the rules fail, because the routes and the component do not exist yet.')
            ->commit('Add the form editor acceptance test')
            ->docs('https://livewire.laravel.com/docs/4.x/testing', 'Livewire docs: Testing')
            ->check(Check::fileExists('tests/Feature/FormEditorTest.php')),

        Step::make('Let staff create a form and edit its details')
            ->run('php artisan make:request FormDetailsRequest')
            ->explain(<<<'TEXT'
                Change the `forms` route in the staff group to a resource without `show` and `destroy`: `Route::resource('forms', Admin\FormController::class)->except(['show', 'destroy'])`. It keeps the `index` route from the last feature. Nobody deletes a form, because its versions hold old answers.

                Do not name the request `FormRequest`. Laravel's base class has that name, and the two would clash. `FormDetailsRequest` checks the name, the slug (filled from the name, as in `CategoryRequest`), the description and `notify_email`, which must be an email address when staff fill it in.

                In `Admin\FormController`, add `create`, `store`, `edit` and `update`. After `store`, send staff to the edit page, because a new form has no questions yet. Add a "New form" button and an edit link to the forms list.

                The edit page shows the details form. The next steps add the questions above it.
                TEXT)
            ->think('Why does `store()` redirect to `edit` and not back to the list?')
            ->hint('Copy `CategoryRequest` and `CategoryController`. Change the names and the rules.')
            ->code(<<<'PHP'
                public function store(FormDetailsRequest $request): RedirectResponse
                {
                    $form = Form::create($request->validated());

                    return to_route('admin.forms.edit', $form)->with('status', 'Form created. Add its fields.');
                }
                PHP)
            ->tryIt('Log in as staff, open Forms and click New form. Create "School booking". You land on its edit page.')
            ->commit('Create forms and edit their details')
            ->docs('https://laravel.com/docs/controllers#restful-partial-resource-routes', 'Laravel docs: Partial resource routes')
            ->check(Check::route('admin.forms.create', 'admin.forms.store', 'admin.forms.edit', 'admin.forms.update')),

        Step::make('Tell staff when the draft differs from the live version')
            ->explain(<<<'TEXT'
                Add `hasUnpublishedChanges(): bool` to `Form`. It compares the fields of the current version with `fieldDefinitions()`. Both are lists of the same arrays, so `!==` finds any difference: a new field, a new order, a new label in the library, a new "required" flag. A form with no version always has unpublished changes.

                Read the current version with `currentVersion()->first()`, not with the `currentVersion` property. The property keeps the version that Eloquent loaded before. After a publish in the same request, it is old.
                TEXT)
            ->think('Staff change a label in the field library, not in the form. Does the form show "Unpublished changes"? Is that what staff want?')
            ->code(<<<'PHP'
                /**
                 * True when the draft fields differ from the latest published version.
                 */
                public function hasUnpublishedChanges(): bool
                {
                    return $this->currentVersion()->first()?->fields !== $this->fieldDefinitions();
                }
                PHP)
            ->tryIt('In tinker, run `App\Models\Form::firstWhere(\'slug\', \'contact\')->hasUnpublishedChanges()`. You see `false`. Change a label of the contact form in the Field library, and run it again.')
            ->commit('Detect unpublished changes in a form')
            ->check(Check::fileContains('app/Models/Form.php', 'function hasUnpublishedChanges')),

        Step::make('Create the Livewire form editor')
            ->run('php artisan make:livewire admin.form-editor')
            ->explain(<<<'TEXT'
                Run the command below. It makes one file: `resources/views/components/admin/⚡form-editor.blade.php`. The PHP class is at the top, and the Blade view is below it. Show it on the edit page with `<livewire:admin.form-editor :form="$form" />`.

                Compare it with the program list component. These parts are **the same**:

                - The command and the file: `make:livewire` with a dotted name, a class at the top, one root element in the view.
                - `boot()` calls `Gate::authorize('access-admin')`. `mount()` runs only when the page loads. `boot()` runs on every request, and every click is a new request to the server.
                - A public property with `wire:model`: here `newField`, for the "Add a field" select. It uses plain `wire:model`, without `.live`. The value waits in the browser until staff click "Add field". The program search needed `.live`, because the results change while staff type.

                This part is **new**: a model property. Give the class a public `Form $form` property. The tag passes the form in: `<livewire:admin.form-editor :form="$form" />`, and Livewire fills the property with the same name. The program list needed no input from the page, so its tag had no attributes. A model property does not travel to the browser as data. Livewire sends only the class and the ID of the model, and the checksum stops the browser from changing them. On the next request, Livewire loads the form again from the database.

                Add three **computed properties** with `#[Computed]`. In the program list, one computed property gave the table. Here three of them return different things: two collections and a boolean. Livewire runs each one at most once per request and keeps the result:

                - `fields()`: the fields of the form, in order.
                - `library()`: the active fields that the form does not use yet, for the "Add a field" select.
                - `hasUnpublishedChanges()`: calls the method on the form.

                In the view, list the fields with their type, their key and a "Required" checkbox. Above the list, show the "Unpublished changes" badge and a Publish button. Under the list, add a select bound with `wire:model="newField"` and an "Add field" button.

                The Publish button is the first button that calls a **method**: a public method of the component. `wire:click="publish"` on the button tells Livewire to call `publish()` on the server when staff click. In the program list, the browser only sent a new value for a property. Here it asks the server to run a method. Write `publish()` now. It calls `Form::publish()` from the last feature and flashes a status message.
                TEXT)
            ->think('A visitor who is not staff sends a request to the component with the ID of a form. Which line stops them?')
            ->hint('Start from your program list component. Copy `boot()` and the imports, and change the rest.')
            ->hint('In the view, read a computed property with `$this->fields`, not `$fields`, as you did with `$this->programs`.')
            ->hint('`Field::active()->whereNotIn(\'id\', $this->fields->modelKeys())` leaves out the fields that the form already has.')
            ->code(<<<'PHP'
                new class extends Component
                {
                    public Form $form;

                    public ?int $newField = null;

                    /**
                     * Run on every request, not only the first one. Each click is a new request.
                     */
                    public function boot(): void
                    {
                        Gate::authorize('access-admin');
                    }

                    /**
                     * @return Collection<int, Field>
                     */
                    #[Computed]
                    public function fields(): Collection
                    {
                        return $this->form->fields()->get();
                    }

                    public function publish(): void
                    {
                        $version = $this->form->publish();

                        session()->flash('status', "Version {$version->number} is published.");
                    }
                };
                PHP)
            ->tryIt(<<<'TEXT'
                Open the edit page of the contact form. You see its three questions. If the badge "Unpublished changes" shows, click Publish. The badge goes away, because the draft now matches the live version.

                Open the page source (Ctrl+U) and find `wire:snapshot` on the root element of the editor. Find `form` in it. You see the class of the model and the key of the form, but not its name or its fields.
                TEXT)
            ->commit('Add the Livewire form editor')
            ->docs('https://livewire.laravel.com/docs/4.x/lifecycle-hooks#boot', 'Livewire docs: The boot hook')
            ->docs('https://livewire.laravel.com/docs/4.x/computed-properties', 'Livewire docs: Computed properties')
            ->docs('https://livewire.laravel.com/docs/4.x/actions', 'Livewire docs: Calling methods from the page')
            ->check(Check::fileExists('resources/views/components/admin/⚡form-editor.blade.php')),

        Step::make('Add, require and remove fields')
            ->explain(<<<'TEXT'
                Add three methods to the component. You wrote one, `publish()`, in the last step. These three are new in one way: they take a **parameter** from the browser.

                `add()` validates `newField`: it is required, it exists in `fields` with no `archived_at`, and the form does not have it yet. Then it attaches the field at the end of the list, not required, and resets `newField`. A visitor can send any ID to a Livewire method, so the rules check what the select already hides.

                `toggleRequired(int $fieldId)` flips the `required` flag on the pivot row with `updateExistingPivot()`.

                `remove(int $fieldId)` detaches the field from the form. It does not delete or archive the field. The field stays in the library, and the old versions still hold it. Then save the positions of the other fields again, so they stay 0, 1, 2...

                In the view, use `wire:click="toggleRequired({{ $field->id }})"` on the checkbox, and `wire:click="remove({{ $field->id }})"` on a Remove button. Blade writes the ID into the HTML, for example `toggleRequired(9)`. On a click, Livewire sends the method name and the `9` to the server.

                A parameter is user input, like the `$search` property in the program list. A user can change `9` to any number in the developer tools. So never trust it: `toggleRequired()` finds the field in `$this->fields` with `findOrFail()`, so it only changes a field of this form. And `add()` validates `newField` with the same Laravel rules as a form request.
                TEXT)
            ->think('Staff remove "Allergies" from the camp form by mistake, and publish. What do the old submissions show? How do staff get the question back?')
            ->hint('Each one is a public method with no return value: `public function toggleRequired(int $fieldId): void`.')
            ->hint('`Rule::exists(\'fields\', \'id\')->whereNull(\'archived_at\')` refuses an archived field. `Rule::notIn($this->fields->modelKeys())` refuses a field that the form has.')
            ->hint('`$this->form->fields()->updateExistingPivot($id, [\'position\' => $position])` changes one pivot row.')
            ->code(<<<'PHP'
                public function add(): void
                {
                    $this->validate([
                        'newField' => [
                            'required',
                            Rule::exists('fields', 'id')->whereNull('archived_at'),
                            Rule::notIn($this->fields->modelKeys()),
                        ],
                    ], ['newField.not_in' => 'The form already has this field.'], ['newField' => 'field']);

                    $this->form->fields()->attach($this->newField, ['position' => $this->fields->count(), 'required' => false]);

                    $this->reset('newField');
                }

                public function toggleRequired(int $fieldId): void
                {
                    $field = $this->fields->findOrFail($fieldId);

                    $this->form->fields()->updateExistingPivot($fieldId, ['required' => ! $field->pivot->required]);
                }
                PHP)
            ->tryIt(<<<'TEXT'
                On the edit page of the contact form, add "School" and tick Required. The badge "Unpublished changes" shows. Open http://localhost:8000/forms/contact in another tab: no School question yet. Publish, and reload the tab.

                Now compare a method call with the program search in the Network tab. Open the Network tab, filter by "livewire", and click a Required checkbox. Open the POST request and its payload. In the program search, `updates` held the new value and `calls` held only `$commit`. Here `updates` is empty, and `calls` holds `{"method": "toggleRequired", "params": [9]}`, with the ID of your field. Click the checkbox again to undo it.
                TEXT)
            ->commit('Add, require and remove form fields')
            ->docs('https://laravel.com/docs/eloquent-relationships#attaching-detaching', 'Laravel docs: Attaching and detaching')
            ->docs('https://laravel.com/docs/eloquent-relationships#updating-a-record-on-the-intermediate-table', 'Laravel docs: Updating a pivot row')
            ->docs('https://livewire.laravel.com/docs/4.x/validation', 'Livewire docs: Validation')
            ->check(Check::all(
                Check::fileContains('resources/views/components/admin/⚡form-editor.blade.php', 'function add'),
                Check::fileContains('resources/views/components/admin/⚡form-editor.blade.php', 'function remove'),
            )),

        Step::make('Drag the fields into a new order')
            ->explain(<<<'TEXT'
                This step adds the last new idea: drag and drop. It calls a method too, but the browser chooses the parameters. Livewire 4 has a `wire:sort` directive. Put `wire:sort="sort"` on the list, and `wire:sort:item="{{ $field->id }}"` on each item. When staff drop an item, Livewire calls `sort()` with the ID of the item and its new position, counted from 0.

                In `sort()`, take the list of IDs in the current order, take out the moved ID, and put it back at the new position. Then save each position. Write `savePositions()` once and use it in `remove()` too.

                Put `wire:sort:ignore` on the checkbox and the Remove button. Then a click on them does not start a drag.
                TEXT)
            ->think('Why save every position again, and not only the position of the moved field?')
            ->hint('`$this->fields` is a collection, so you can use the collection methods on it. `modelKeys()` gives the IDs in the current order.')
            ->hint('`collect($ids)->reject(fn (int $id) => $id === $fieldId)->values()` takes the moved ID out. `values()` numbers the rest from 0 again.')
            ->hint('`$ids->splice($position, 0, [$fieldId])` puts the ID back into the collection at the new position.')
            ->code(<<<'PHP'
                public function sort(int $fieldId, int $position): void
                {
                    $ids = collect($this->fields->modelKeys())->reject(fn (int $id) => $id === $fieldId)->values();
                    $ids->splice($position, 0, [$fieldId]);

                    $this->savePositions($ids->all());
                }

                /**
                 * @param  array<int, int>  $ids
                 */
                private function savePositions(array $ids): void
                {
                    foreach ($ids as $position => $id) {
                        $this->form->fields()->updateExistingPivot($id, ['position' => $position]);
                    }
                }
                PHP)
            ->tryIt('Drag "Message" to the top of the contact form. In the Network tab, the POST request has `calls` with the method `sort` and two parameters: the ID of the field and its new position. Reload the page: the order stays. Publish, and open the public form.')
            ->commit('Reorder form fields with wire:sort')
            ->docs('https://livewire.laravel.com/docs/4.x/wire-sort', 'Livewire docs: wire:sort')
            ->docs('https://laravel.com/docs/collections#method-splice', 'Laravel docs: The splice method')
            ->check(Check::fileContains('resources/views/components/admin/⚡form-editor.blade.php', 'wire:sort=')),

        Step::make('Block a type change when a field has answers')
            ->explain(<<<'TEXT'
                A field has answers such as "Westsyde Elementary". If staff change it to a date field, those answers do not fit, and the export and the profile break. So the type is locked once a field has answers. Staff archive the field and add a new one instead.

                Add `hasAnswers(): bool` to `Field`. The answers are JSON in `submissions`. Laravel can query inside a JSON column with `->`: `whereNotNull("answers->{$this->key}")` finds the submissions with an answer under the key.

                In `FieldRequest`, add a closure rule to `type`. It fails when the request edits a field, the type is new, and the field has answers. On the field form, show the type as read-only text with a hidden input when the field has answers, and explain why.
                TEXT)
            ->think('Why check `hasAnswers()` only when the type changes, and not on every save?')
            ->hint('A closure rule takes `$attribute`, `$value` and `$fail`. Call `$fail(\'...\')` with the message.')
            ->code(<<<'PHP'
                // app/Models/Field.php
                public function hasAnswers(): bool
                {
                    return Submission::whereNotNull("answers->{$this->key}")->exists();
                }

                // app/Http/Requests/FieldRequest.php
                private function typeIsLocked(): Closure
                {
                    return function (string $attribute, mixed $value, Closure $fail): void {
                        $field = $this->field();

                        if ($field !== null && $value !== $field->type->value && $field->hasAnswers()) {
                            $fail('This field has answers, so its type cannot change. Archive it and add a new field.');
                        }
                    };
                }
                PHP)
            ->tryIt('Send the contact form once. Then open "Your name" in the Field library. The type is read-only, with the reason under it.')
            ->commit('Lock the type of fields with answers')
            ->docs('https://laravel.com/docs/queries#json-where-clauses', 'Laravel docs: JSON where clauses')
            ->docs('https://laravel.com/docs/validation#using-closures', 'Laravel docs: Closure rules')
            ->check(Check::fileContains('app/Models/Field.php', 'function hasAnswers')),

        Step::make('Let a program use a form')
            ->run('php artisan make:migration add_form_id_to_programs_table')
            ->explain(<<<'TEXT'
                A camp asks its own questions when parents register. Add a nullable `form_id` foreign key to `programs`, with `nullOnDelete()`. Write `down()` with `dropConstrainedForeignId()`.

                Add `form(): BelongsTo` to `Program`, add `form_id` to `#[Fillable]` and to the docblock. In `ProgramRequest`, `form_id` is nullable and must exist in `forms`. In the program form, add a select with "No form" and the forms by name. `Admin\ProgramController` passes `Form::orderBy('name')->get()` to `create` and `edit`.

                The sign-up features use `Program::form()` later. This feature only stores the choice.
                TEXT)
            ->think('Many programs can use the same form. Is that good or bad? When would staff want one form for each program?')
            ->code(<<<'PHP'
                Schema::table('programs', function (Blueprint $table) {
                    $table->foreignId('form_id')->nullable()->after('category_id')->constrained()->nullOnDelete();
                });
                PHP)
            ->tryIt('Run `php artisan migrate`. Edit a camp in the staff area, choose the contact form, and save. In tinker, `App\Models\Program::whereNotNull(\'form_id\')->first()->form->name` shows the name of the form.')
            ->commit('Let programs use a form')
            ->docs('https://laravel.com/docs/migrations#foreign-key-constraints', 'Laravel docs: Foreign key constraints')
            ->docs('https://laravel.com/docs/migrations#dropping-foreign-keys', 'Laravel docs: Dropping foreign keys')
            ->check(Check::all(
                Check::table('programs', ['form_id']),
                Check::fileContains('app/Models/Program.php', 'function form'),
            )),

        Step::make('Run the checks and open your pull request')
            ->run('php artisan guide:verify form-editor')
            ->explain(<<<'TEXT'
                Run the acceptance test with `guide:verify`. Then run `composer ci:check`.

                PHPStan does not read the PHP part of a single-file Livewire component, so test every public method. The acceptance test calls each one.

                In your pull request, add a short screen recording of the editor: add a field, drag it, publish.
                TEXT)
            ->think('The test calls `sort` directly. What does the test not prove about the drag in the browser? How would you check it?')
            ->tryIt('Run `composer ci:check`. Pint, PHPStan and all the tests pass.')
            ->commit('Fix the checks for the form editor')
            ->check(Check::testPassed('FormEditorTest')),
    ]);
