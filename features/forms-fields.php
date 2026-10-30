<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('forms-fields')
    ->title('Forms and fields')
    ->section(Section::Forms)
    ->level(2)
    ->needs(['staff-roles'])
    ->summary('A shared field library and forms that use it. Each field has a fixed key, a type, a scope and an optional role.')
    ->teaches(['Backed enums', 'Enum and JSON casts', 'belongsToMany with pivot columns', 'Validating arrays', 'Archiving instead of deleting', 'Idempotent seeders'])
    ->test('FormsFieldsTest.php')
    ->intro(<<<'TEXT'
        The centre asks the same questions in many places: the school of the child, allergies, an emergency contact, the people who can pick up the child. Camps, clubs, QUARKS applications and party requests all ask some of them. Each season, staff want to add a question, change the words of another, or stop asking one.

        If each question is a column in a table, every change needs a developer and a migration. So in this feature, you build a **field library**. A `Field` is one question. A `Form` is a list of fields from the library, in an order that staff choose. Staff build forms from fields, with no code change.

        Each field has two names. The **key**, such as `pickup_people`, is fixed. The app saves answers under the key, so the key can never change. The **label**, such as "People who can pick up your child", is the text that people read. Staff can change it at any time, because no answer depends on it.

        A field also has a **type** (short text, date, one choice...), a **scope** and an optional **role**. The scope says where an answer belongs: to the child, to the family, or to this one sign-up. The role is for code. When the registration list needs the emergency phone, it asks for the role `EmergencyPhone`, never for the label "Emergency contact phone". Staff can rename the label, and the code still works.

        One more rule: staff never delete a field or a choice. They archive it. Old answers must keep their question, so the row stays in the database.

        In this feature, you build the library, the staff pages for it, and the link between forms and fields. The next features show a form, save the answers, and give staff a form editor.
        TEXT)
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/8', 'Laravel From Scratch, episode 8: Databases, Migrations, and Eloquent')
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/16', 'Laravel From Scratch, episode 16: Eloquent Relationships')
    ->docs('https://laravel.com/docs/eloquent-relationships#many-to-many', 'Laravel docs: Many to many relationships')
    ->docs('https://laravel.com/docs/eloquent-mutators#enum-casting', 'Laravel docs: Enum casting')
    ->reflect([
        'Why is the key of a field fixed, but its label is not? What breaks if staff can change a key?',
        'Registration lists need the emergency phone of each child. Why does that code ask for the role `EmergencyPhone`, and not for the field with the label "Emergency contact phone"?',
        'Staff want to ask about dietary needs next season. List what they do. Which files change?',
        'Which step was the hardest? What would help the next contributor? Open an issue or a pull request in blsc-guide to improve that step.',
    ])
    ->steps([
        Step::make('Claim the feature and copy in its test')
            ->run('php artisan guide:start forms-fields')
            ->explain(<<<'TEXT'
                Run the command below. It copies `tests/Feature/FormsFieldsTest.php` into the app.

                Read the whole test before you write code. It names the parts that you build: the `Field` and `Form` models, three enums (`FieldType`, `FieldScope` and `FieldRole`), the `admin.fields.*` routes and a `FormSeeder`.

                Look at the rules about keys and choices. The test sends a new `key` when staff edit a field, and then checks that the key did not change. It archives a choice and checks that the choice is still there. These rules protect old answers.
                TEXT)
            ->think('The test never deletes a field. Why not? What would happen to an old answer if its field were deleted?')
            ->tryIt('Run `php artisan test tests/Feature/FormsFieldsTest.php`. All the rules fail, because the models do not exist yet.')
            ->commit('Add the forms and fields acceptance test')
            ->docs('https://laravel.com/docs/http-tests', 'Laravel docs: HTTP tests')
            ->check(Check::fileExists('tests/Feature/FormsFieldsTest.php')),

        Step::make('Create the three enums')
            ->run("php artisan make:enum App/Enums/FieldType --string\nphp artisan make:enum App/Enums/FieldScope --string\nphp artisan make:enum App/Enums/FieldRole --string")
            ->explain(<<<'TEXT'
                Some values in this feature are a closed list that the code reads. An enum keeps each list in one place, and PHP refuses any other value.

                - `FieldType`: `Text`, `LongText`, `Email`, `Phone`, `Date`, `Number`, `Choice`, `Checkboxes` and `Consent`. The code reads the type to choose an input and the validation rules.
                - `FieldScope`: `Child`, `Family` and `Signup`. A later feature saves child and family answers to the profile.
                - `FieldRole`: `Allergies`, `MedicalNotes`, `EmergencyName`, `EmergencyPhone` and `PickupPeople`. Code that needs one of these answers asks for the role.

                Run the commands below. The full namespace `App/Enums/` in each name puts the file in `app/Enums`, even when that folder does not exist yet. Give each case a snake_case value, such as `case LongText = 'long_text';`. The database stores the value.

                Give each enum a `label()` method for the staff pages. Give `FieldType` one more method, `hasOptions()`. It is true for `Choice` and `Checkboxes`, because only these types have a list of choices.
                TEXT)
            ->think('The scope and the role are enums, but the label of a field is a column. What is the difference between a value that the code reads and a value that the code only shows?')
            ->hint('`in_array($this, [self::Choice, self::Checkboxes], true)` is true when the case is one of the two.')
            ->code(<<<'PHP'
                enum FieldType: string
                {
                    case Text = 'text';
                    case LongText = 'long_text';
                    case Email = 'email';
                    case Phone = 'phone';
                    case Date = 'date';
                    case Number = 'number';
                    case Choice = 'choice';
                    case Checkboxes = 'checkboxes';
                    case Consent = 'consent';

                    public function label(): string
                    {
                        return match ($this) {
                            self::Text => 'Short text',
                            self::LongText => 'Long text',
                            // ...one label for each case
                        };
                    }

                    /**
                     * Only these types have a list of choices.
                     */
                    public function hasOptions(): bool
                    {
                        return in_array($this, [self::Choice, self::Checkboxes], true);
                    }
                }
                PHP)
            ->tryIt('In `php artisan tinker`, run `App\Enums\FieldType::Choice->hasOptions()` and `App\Enums\FieldType::Date->hasOptions()`. You see `true` and `false`.')
            ->commit('Add the field type, scope and role enums')
            ->docs('https://laravel.com/docs/eloquent-mutators#enum-casting', 'Laravel docs: Enum casting')
            ->check(Check::all(
                Check::classExists('App\Enums\FieldType'),
                Check::classExists('App\Enums\FieldScope'),
                Check::classExists('App\Enums\FieldRole'),
            )),

        Step::make('Create the Field model and its table')
            ->run('php artisan make:model Field -mf')
            ->explain(<<<'TEXT'
                Run the command below. Then add the columns to the new migration.

                - `key`: a unique string. Answers are saved under it.
                - `label` and `help`: the text that people read. `help` is optional.
                - `type` and `scope`: strings that hold the enum values.
                - `options`: a nullable JSON column. Each choice is an object with a fixed `value`, a `label` and an `archived` flag.
                - `role`: a nullable, unique string. Two fields with the same role would make the code guess which answer to read.
                - `sensitive`: a boolean, default `false`. Sensitive answers stay out of exports.
                - `review_after_months`: a nullable small integer. Parents confirm a sensitive answer again after this time.
                - `archived_at`: a nullable timestamp. A date, not a boolean, so staff can see when they archived the field.

                Run `php artisan migrate`.
                TEXT)
            ->think('A choice has a `value` and a `label`. Why not store only the label, such as "Grade 3"?')
            ->hint('A unique column can hold many `NULL` values. So `->nullable()->unique()` lets many fields have no role, but only one field have each role.')
            ->code(<<<'PHP'
                $table->id();
                $table->string('key')->unique();
                $table->string('label');
                $table->text('help')->nullable();
                $table->string('type');
                $table->json('options')->nullable();
                $table->string('scope');
                $table->string('role')->nullable()->unique();
                $table->boolean('sensitive')->default(false);
                $table->unsignedSmallInteger('review_after_months')->nullable();
                $table->timestamp('archived_at')->nullable();
                $table->timestamps();
                PHP)
            ->tryIt('Run `php artisan migrate`. Then, in `php artisan tinker`, run `Schema::getColumnListing(\'fields\')`. You see your columns.')
            ->commit('Add the Field model and table')
            ->docs('https://laravel.com/docs/migrations#column-method-json', 'Laravel docs: The json column type')
            ->check(Check::table('fields', ['key', 'label', 'help', 'type', 'options', 'scope', 'role', 'sensitive', 'review_after_months', 'archived_at'])),

        Step::make('Fill in the Field model and its factory')
            ->explain(<<<'TEXT'
                Open `app/Models/Field.php`. Use `app/Models/Program.php` as your example.

                Add the columns to `#[Fillable]`, with `key`: staff set it once, on create. Leave out `archived_at`. Only the archive method sets it, never a form.

                Add casts. `type`, `scope` and `role` cast to their enums, so `$field->type` is a `FieldType` and not a string. `options` casts to `array`, so Laravel turns the JSON into a PHP array and back. `sensitive` is a boolean and `archived_at` is a datetime.

                Add an `active` scope that keeps only the fields with no `archived_at`, and an `isArchived()` method. Write the docblock at the top, as in `Program`. PHPStan reads it.

                In `database/factories/FieldFactory.php`, make a label and build the key from it with `Str::slug($label, '_')`. Then add states that the test uses: `choice(array $choices)`, `sensitive()` and `archived()`.
                TEXT)
            ->think('The app uses `CarbonImmutable` for dates (see `AppServiceProvider`). Which type do you write for `$archived_at` in the docblock?')
            ->hint('`Str::slug(\'School name\', \'_\')` gives `school_name`. The second argument is the separator.')
            ->hint('In `choice()`, turn `[\'small\' => \'Small\']` into `[[\'value\' => \'small\', \'label\' => \'Small\', \'archived\' => false]]` with `collect()->map()->values()->all()`.')
            ->code(<<<'PHP'
                protected function casts(): array
                {
                    return [
                        'type' => FieldType::class,
                        'options' => 'array',
                        'scope' => FieldScope::class,
                        'role' => FieldRole::class,
                        'sensitive' => 'boolean',
                        'archived_at' => 'datetime',
                    ];
                }

                /**
                 * Only include fields that staff have not archived.
                 *
                 * @param  Builder<Field>  $query
                 */
                #[Scope]
                protected function active(Builder $query): void
                {
                    $query->whereNull('archived_at');
                }
                PHP)
            ->tryIt('In `php artisan tinker`, run `App\Models\Field::factory()->choice([\'s\' => \'Small\'])->make()->options`. You see one choice with a value, a label and `archived => false`.')
            ->commit('Fill in the Field model and factory')
            ->docs('https://laravel.com/docs/eloquent-mutators#array-and-json-casting', 'Laravel docs: Array and JSON casting')
            ->docs('https://laravel.com/docs/eloquent#local-scopes', 'Laravel docs: Local scopes')
            ->check(Check::all(
                Check::fileContains('app/Models/Field.php', 'FieldType::class'),
                Check::fileContains('database/factories/FieldFactory.php', 'function choice'),
            )),

        Step::make('Create forms and connect them to fields')
            ->run("php artisan make:model Form -mf\nphp artisan make:migration create_form_field_table")
            ->explain(<<<'TEXT'
                A form has a `name`, a unique `slug`, an optional `description` and an optional `notify_email`. A later feature sends an email to that address for each new answer.

                One field can be in many forms, and one form has many fields. That is a many-to-many relationship. It needs a third table, the **pivot table**, with one row for each field in each form. The pivot row is also the right place for facts about "this field in this form": its `position` and whether it is `required`. The same "Allergies" field can be required in a camp form and optional in a party form.

                Create the `form_field` table with `form_id`, `field_id`, `position` and `required`. Make the pair of IDs unique, so a form cannot have the same field twice. Use `restrictOnDelete()` for `field_id`: the database itself refuses to delete a field that a form uses.

                Laravel names a pivot table from the two models in alphabetical order, so it looks for `field_form`. Our table is `form_field`, so pass the name as the second argument of `belongsToMany()`. Then add `withPivot()` to read the two columns, and `orderByPivot('position')` to sort the fields.

                Add `forms()` to `Field` too, with the same table name.
                TEXT)
            ->think('Why is `required` a column on the pivot table, and not on the `fields` table?')
            ->hint('`$table->unique([\'form_id\', \'field_id\']);` makes the pair unique.')
            ->hint('After `withPivot()`, read the values as `$field->pivot->required` and `$field->pivot->position`.')
            ->code(<<<'PHP'
                /**
                 * The fields of the form, in the order that staff chose.
                 *
                 * @return BelongsToMany<Field, $this>
                 */
                public function fields(): BelongsToMany
                {
                    return $this->belongsToMany(Field::class, 'form_field')
                        ->withPivot(['position', 'required'])
                        ->withTimestamps()
                        ->orderByPivot('position');
                }
                PHP)
            ->tryIt('Run `php artisan migrate`. In tinker, make a form and two fields with factories. Attach them with `$form->fields()->attach($field, [\'position\' => 1, \'required\' => true])`. Then `$form->fresh()->fields->pluck(\'key\')` lists them in position order.')
            ->commit('Add forms and the form_field pivot table')
            ->docs('https://laravel.com/docs/eloquent-relationships#many-to-many', 'Laravel docs: Many to many relationships')
            ->docs('https://laravel.com/docs/eloquent-relationships#retrieving-intermediate-table-columns', 'Laravel docs: Retrieving intermediate table columns')
            ->docs('https://laravel.com/docs/eloquent-relationships#ordering-queries-via-intermediate-table-columns', 'Laravel docs: Ordering via intermediate table columns')
            ->check(Check::all(
                Check::table('forms', ['name', 'slug', 'description', 'notify_email']),
                Check::table('form_field', ['form_id', 'field_id', 'position', 'required']),
                Check::fileContains('app/Models/Form.php', "orderByPivot('position')"),
            )),

        Step::make('Validate a field, and keep the key fixed')
            ->run('php artisan make:request FieldRequest')
            ->explain(<<<'TEXT'
                Staff create and edit fields with a form. `FieldRequest` checks what they send. Copy `authorize()` from `CategoryRequest`.

                **The key.** On create, fill the key from the label when staff leave it empty, as `CategoryRequest` does for the slug. On edit, leave the key out of `rules()`. A value without a rule is not in `validated()`, so the controller never saves it. The key cannot change, even if someone sends a new one.

                **The enums.** `Rule::enum(FieldType::class)` accepts only the values of the enum. Use it for `type`, `scope` and `role`. The role must also be unique, except for the field itself.

                **The choices.** The form sends rows: `options[0][value]`, `options[0][label]` and `options[0][archived]`. In `prepareForValidation()`, clean the rows: drop rows where both value and label are empty, and give each new row a value from its label. An existing row always sends its value in a hidden input, so it is never dropped. Staff cannot remove a choice. They can only archive it.

                For a type without choices, replace `options` with an empty array. Then require `options` only when the type has choices.
                TEXT)
            ->think('Staff empty the label of an existing choice and save. What happens to the choice? What does the person see?')
            ->hint('Use `$this->route(\'field\')` to find the field. It is `null` on create.')
            ->hint('`Rule::requiredIf(fn () => FieldType::tryFrom((string) $this->input(\'type\'))?->hasOptions() ?? false)` requires the choices only for the two types.')
            ->hint('Validate each row with a `*`: `\'options.*.value\' => [\'required\', \'alpha_dash\', \'distinct\']`. `distinct` refuses two choices with the same value.')
            ->code(<<<'PHP'
                protected function prepareForValidation(): void
                {
                    $type = FieldType::tryFrom((string) $this->input('type'));

                    $this->merge([
                        'sensitive' => $this->boolean('sensitive'),
                        'options' => $type?->hasOptions() ? $this->options() : [],
                    ]);

                    if ($this->field() === null) {
                        $this->merge(['key' => Str::slug($this->input('key') ?: $this->input('label', ''), '_')]);
                    }
                }

                private function options(): array
                {
                    return collect((array) $this->input('options', []))
                        ->filter(fn ($option) => is_array($option) && (filled($option['value'] ?? null) || filled($option['label'] ?? null)))
                        ->map(fn (array $option) => [
                            'value' => (string) (($option['value'] ?? null) ?: Str::slug((string) $option['label'], '_')),
                            'label' => (string) ($option['label'] ?? ''),
                            'archived' => filter_var($option['archived'] ?? false, FILTER_VALIDATE_BOOLEAN),
                        ])
                        ->values()
                        ->all();
                }
                PHP)
            ->tryIt('Read your `rules()` and answer: on edit, which rule mentions `key`? None, so `$request->validated()` has no `key`.')
            ->commit('Validate fields with FieldRequest')
            ->docs('https://laravel.com/docs/validation#preparing-input-for-validation', 'Laravel docs: Preparing input for validation')
            ->docs('https://laravel.com/docs/validation#validating-nested-array-input', 'Laravel docs: Validating nested array input')
            ->docs('https://laravel.com/docs/validation#rule-enum', 'Laravel docs: The enum rule')
            ->docs('https://laravel.com/docs/validation#rule-required-if', 'Laravel docs: The required_if rule')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/12', 'Laravel From Scratch, episode 12: Form Request Classes')
            ->check(Check::fileContains('app/Http/Requests/FieldRequest.php', 'hasOptions')),

        Step::make('Add the controller, the routes, and archive instead of delete')
            ->run('php artisan make:controller Admin/FieldController --model=Field')
            ->explain(<<<'TEXT'
                Add the routes in the staff group in `routes/web.php`: a resource without `show` and without `destroy`. There is no delete route, so nobody can delete a field by mistake.

                Add two more routes: `POST fields/{field}/archive` named `fields.archive`, and `POST fields/{field}/restore` named `fields.restore`. Archive sets `archived_at` to now. Restore sets it back to `null`.

                `archived_at` is not fillable, so `update(['archived_at' => now()])` does nothing. Set the property and call `save()`.

                In `index()`, list the active fields first, then the archived ones, each sorted by label. `create()` and `edit()` pass the enum cases for the select boxes: `FieldType::cases()` and so on.
                TEXT)
            ->think('The table already refuses to delete a field that a form uses. Why have no delete route at all?')
            ->hint('`Field::orderByRaw(\'archived_at is not null\')->orderBy(\'label\')` puts the active fields first.')
            ->code(<<<'PHP'
                Route::resource('fields', Admin\FieldController::class)->except(['show', 'destroy']);
                Route::post('fields/{field}/archive', [Admin\FieldController::class, 'archive'])->name('fields.archive');
                Route::post('fields/{field}/restore', [Admin\FieldController::class, 'restore'])->name('fields.restore');

                // In the controller:
                public function archive(Field $field): RedirectResponse
                {
                    $field->archived_at = now();
                    $field->save();

                    return to_route('admin.fields.index')->with('status', 'Field archived.');
                }
                PHP)
            ->tryIt('Run `php artisan route:list --name=admin.fields`. You see index, create, store, edit, update, archive and restore, and no destroy.')
            ->commit('Add the field library controller and routes')
            ->docs('https://laravel.com/docs/controllers#restful-partial-resource-routes', 'Laravel docs: Partial resource routes')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/10', 'Laravel From Scratch, episode 10: Controllers')
            ->check(Check::route('admin.fields.index', 'admin.fields.store', 'admin.fields.update', 'admin.fields.archive', 'admin.fields.restore')),

        Step::make('Build the staff pages and the menu link')
            ->explain(<<<'TEXT'
                Create `index`, `create`, `edit` and `form` views in `resources/views/admin/fields`. Copy the category views and change what is different.

                The list shows the label, the key, the type, the scope and the role. Show an "Archived" badge on archived fields, and a "Sensitive" badge on sensitive ones.

                In `form.blade.php`, show the key as an input only on create. On edit, show it read-only, with the sentence "The key never changes. Answers are saved under it." Then staff know why they cannot edit it.

                For the choices, loop over the existing options and add three empty rows. An existing row has a hidden `value` input, a label input and an "Archived" checkbox. An empty row has only a label input. Staff save to get more empty rows. A later feature can make this nicer with Livewire.

                On the edit page, add an "Archive field" button, or a "Restore field" button when the field is archived. Last, add a "Field library" link to the staff menu in `resources/views/layouts/app.blade.php`.
                TEXT)
            ->think('After a failed save, the page must show what staff typed. Which helper gives the old rows of choices?')
            ->hint('`[...old(\'options\', $field->options ?? []), ...array_fill(0, 3, [])]` gives the existing rows and three empty rows.')
            ->hint('`old(\'type\', $field->type?->value) === $type->value` selects the current type. Compare strings with strings.')
            ->tryIt(<<<'TEXT'
                Log in as staff and click Field library. Create "T-shirt size" with the type "One choice" and two choices. Open it again: each choice has a value that you cannot edit.

                Archive one choice and save. Then archive the field, and restore it.
                TEXT)
            ->commit('Add the field library pages')
            ->docs('https://laravel.com/docs/validation#repopulating-forms', 'Laravel docs: Repopulating forms')
            ->check(Check::view('admin.fields.form')),

        Step::make('Seed the common fields and a contact form')
            ->run('php artisan make:seeder FormSeeder')
            ->explain(<<<'TEXT'
                Many forms ask the same questions. Seed them, so every contributor has the same library: school, grade, allergies, medical notes, emergency contact name and phone, pickup people, and photo consent for social media. Give each one the right scope and role. Mark allergies and medical notes as sensitive, with `review_after_months` of 12.

                Add a "Contact us" form with the slug `contact` and three sign-up fields: name, email and message. The next feature shows it at `/forms/contact`.

                A seeder can run more than once on the same database. Use `updateOrCreate()` with the key, so the second run updates the rows and does not make copies. Use `sync()` for the fields of the form, for the same reason. Then call the seeder from `DatabaseSeeder`.
                TEXT)
            ->think('The test runs the seeder twice and expects one "Allergies" field. What would `create()` do on the second run?')
            ->hint('`Field::updateOrCreate([\'key\' => \'school\'], [...])` finds the field with that key, or creates it.')
            ->hint('`sync()` takes `[$fieldId => [\'position\' => 0, \'required\' => true], ...]`. Build the array from a list of keys, so the position is the place in the list.')
            ->code(<<<'PHP'
                foreach ($fields as $field) {
                    Field::updateOrCreate(['key' => $field['key']], $field);
                }

                $contact = Form::updateOrCreate(['slug' => 'contact'], [
                    'name' => 'Contact us',
                    'notify_email' => 'info@example.com',
                ]);

                $contact->fields()->sync(
                    collect(['contact_name', 'contact_email', 'message'])
                        ->mapWithKeys(fn (string $key, int $position) => [
                            Field::where('key', $key)->value('id') => ['position' => $position, 'required' => true],
                        ])
                        ->all()
                );
                PHP)
            ->tryIt('Run `php artisan db:seed --class=FormSeeder` two times. In tinker, run `App\Models\Field::count()`. The number is the same after each run. Then run `App\Models\Form::firstWhere(\'slug\', \'contact\')->fields->pluck(\'label\')`. You see the three labels in order.')
            ->commit('Seed the common fields')
            ->docs('https://laravel.com/docs/seeding#writing-seeders', 'Laravel docs: Writing seeders')
            ->docs('https://laravel.com/docs/eloquent#upserts', 'Laravel docs: Upserts')
            ->docs('https://laravel.com/docs/eloquent-relationships#syncing-associations', 'Laravel docs: Syncing associations')
            ->check(Check::fileExists('database/seeders/FormSeeder.php')),

        Step::make('Run the checks and open your pull request')
            ->run('php artisan guide:verify forms-fields')
            ->explain(<<<'TEXT'
                Run the acceptance test with `guide:verify`. Then run `composer ci:check`. It runs Pint, PHPStan and every test, as GitHub does on your pull request.

                PHPStan reads the docblocks of your models. If it says that a property does not accept a value, check the type in the docblock first.

                In your pull request, tell reviewers to run `php artisan migrate` and `php artisan db:seed --class=FormSeeder`.
                TEXT)
            ->think('A reviewer runs the seeder on a database that already has a field with the key `school`. What happens?')
            ->tryIt('Run `composer ci:check`. Pint, PHPStan and all the tests pass.')
            ->commit('Fix the checks for forms and fields')
            ->check(Check::testPassed('FormsFieldsTest')),
    ]);
