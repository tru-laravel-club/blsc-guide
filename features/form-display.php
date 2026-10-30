<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('form-display')
    ->title('Show and validate a form')
    ->section(Section::Forms)
    ->level(2)
    ->needs(['forms-fields'])
    ->summary('One component shows any form. One class builds the validation rules from the fields.')
    ->teaches(['Arrays as a contract', 'Building validation rules in code', 'Rule::in', 'Dynamic Blade components', 'Anonymous components', 'Custom pivot models'])
    ->test('FormDisplayTest.php')
    ->intro(<<<'TEXT'
        Staff can now build a form from the field library. But nobody can fill it in. In this feature, you show any form at `/forms/{slug}` and check the answers.

        You do not write one Blade view and one form request for each form. Staff make new forms without a developer, so the code cannot know their fields. Instead, the code reads the fields and builds the page and the rules from them.

        The parts talk to each other with a **field definition**: a plain array with `key`, `label`, `type`, `options`, `required` and `help`. `Field::definition()` makes one. `Form::fieldDefinitions()` makes the list for a form. Two parts read the list:

        - `App\Forms\FormRules::for(array $fields)` turns the list into Laravel validation rules, keyed by the field key.
        - `<x-dynamic-form :fields="...">` shows each field with a small Blade component for its type.

        Why a plain array, and not the `Field` model? The next feature saves a copy of the definitions with each version of a form, as JSON. An old version must still show and validate after staff change the library. JSON gives back arrays, not models. So the rules and the component take arrays from the start.

        The path of a request: the visitor opens `/forms/contact`. The controller finds the form by its slug and gets the definitions. The view gives them to `<x-dynamic-form>`. On submit, the controller builds the rules with `FormRules::for()`, validates, and shows a thank-you page. This feature does not save the answers yet. The next feature does.
        TEXT)
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/7', 'Laravel From Scratch, episode 7: Forms')
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/11', 'Laravel From Scratch, episode 11: Request Validation')
    ->docs('https://laravel.com/docs/blade#dynamic-components', 'Laravel docs: Dynamic components')
    ->docs('https://laravel.com/docs/validation#validating-arrays', 'Laravel docs: Validating arrays')
    ->reflect([
        'Staff want a new field type, "time". How many places change? Can you make it one?',
        'Why do `FormRules` and the component take arrays, and not `Field` models?',
        'The browser has a `required` attribute on the inputs. Why must the server check "required" again?',
        'Which step was the hardest? What would help the next contributor? Open an issue or a pull request in blsc-guide to improve that step.',
    ])
    ->steps([
        Step::make('Claim the feature and copy in its test')
            ->run('php artisan guide:start form-display')
            ->explain(<<<'TEXT'
                Run the command below. It copies `tests/Feature/FormDisplayTest.php` into the app.

                Read the test. The helper `formForDisplay()` at the top makes a form with some fields. Most rules then use routes, as a visitor does: open the form, send answers, see errors or the thank-you page.

                Look at the rule with `->with([...])`. It is a **dataset**: Pest runs the same rule once for each row. Each row is a field type, a wrong value and a right value. So one short rule tests all nine types.

                The last rule calls `FormRules::for()` directly. It fixes the shape of the field definition, because later features depend on it.
                TEXT)
            ->think('The rule "a required field is required" checks the error text "The Your name field is required." The key is `your_name`. Where do the words "Your name" come from?')
            ->tryIt('Run `php artisan test tests/Feature/FormDisplayTest.php`. All the rules fail, because the routes and `FormRules` do not exist yet.')
            ->commit('Add the form display acceptance test')
            ->docs('https://pestphp.com/docs/datasets', 'Pest docs: Datasets')
            ->check(Check::fileExists('tests/Feature/FormDisplayTest.php')),

        Step::make('Turn fields into field definitions')
            ->run('php artisan make:model FormField --pivot')
            ->explain(<<<'TEXT'
                Add `definition(bool $required = false): array` to `Field`. It returns the field as a plain array: `key`, `label`, `type` (the enum value, a string), `options` (an empty array when there are none), `required` and `help`.

                Add three more keys: `scope`, `role` and `sensitive`. The display does not need them. But the next features save a copy of this array, and later code reads it: the registration list asks for a role, and the export leaves out sensitive fields. The copy must hold every fact that later code reads.

                Then add `fieldDefinitions()` to `Form`. It takes the active fields of the form, in order, and calls `definition()` with the `required` flag from the pivot row.

                PHPStan does not know that a `Field` from `fields()` has a `pivot` property. Give the pivot table its own model. Run the command below. In `FormField`, cast `required` to a boolean and `position` to an integer, and write the docblock. Add `->using(FormField::class)` to `fields()` and `forms()`, and change the return type to `BelongsToMany<Field, $this, FormField>`. In the docblock of `Field`, add `@property-read FormField $pivot`.
                TEXT)
            ->think('`fieldDefinitions()` leaves out archived fields. Where else could you filter them? Why is this the best place?')
            ->hint('`$this->fields()->active()->get()` uses the `active` scope through the relationship.')
            ->hint('Write the shape of the array once as a PHPStan type in `FormRules` (next step): `@phpstan-type Definition array{key: string, label: string, ...}`. Other classes import it with `@phpstan-import-type Definition from FormRules`.')
            ->code(<<<'PHP'
                // app/Models/Field.php
                public function definition(bool $required = false): array
                {
                    return [
                        'key' => $this->key,
                        'label' => $this->label,
                        'type' => $this->type->value,
                        'options' => $this->options ?? [],
                        'required' => $required,
                        'help' => $this->help,
                        'scope' => $this->scope->value,
                        'role' => $this->role?->value,
                        'sensitive' => $this->sensitive,
                    ];
                }

                // app/Models/Form.php
                public function fieldDefinitions(): array
                {
                    return $this->fields()
                        ->active()
                        ->get()
                        ->map(fn (Field $field) => $field->definition($field->pivot->required))
                        ->values()
                        ->all();
                }
                PHP)
            ->tryIt('Run `php artisan db:seed --class=FormSeeder`. In tinker, run `App\Models\Form::firstWhere(\'slug\', \'contact\')->fieldDefinitions()`. You see three arrays with the same keys.')
            ->commit('Add field definitions')
            ->docs('https://laravel.com/docs/eloquent-relationships#defining-custom-intermediate-table-models', 'Laravel docs: Custom intermediate table models')
            ->check(Check::all(
                Check::fileContains('app/Models/Field.php', 'function definition'),
                Check::fileContains('app/Models/Form.php', 'function fieldDefinitions'),
            )),

        Step::make('Build the rules from the definitions')
            ->explain(<<<'TEXT'
                Create `app/Forms/FormRules.php` with one static method: `for(array $fields): array`. For each definition, it adds an entry keyed by the field key. The entry starts with `required` or `nullable`, and then adds the rules for the type.

                Write the rules for the types in one `match` on `FieldType`, with one arm for each type:

                - Short text: a string of at most 255 characters. Long text: at most 5000.
                - Email: `email`. Phone: digits, spaces and `+ ( ) - .` only, with a `regex` rule.
                - Date: `date`. Number: `numeric`.
                - One choice: `Rule::in()` with the values of the choices that are not archived.
                - Several choices: `array`, and a second entry `key.*` that checks each value with `Rule::in()`.
                - Consent: `accepted` when it is required, so the box must be ticked. Otherwise `boolean`.

                A `match` on an enum has one more benefit. If someone adds a type and forgets an arm, PHP throws an error, and PHPStan warns before the code runs.
                TEXT)
            ->think('Staff archive the choice "Tiny". A visitor changes the HTML of the page and sends `tiny` anyway. Which line refuses it?')
            ->hint('`collect($field[\'options\'])->reject(fn (array $option) => $option[\'archived\'])->pluck(\'value\')->all()` gives the values that are still allowed.')
            ->hint('For several choices, `$rules[$field[\'key\'].\'.*\']` is the rule for each ticked box.')
            ->code(<<<'PHP'
                public static function for(array $fields): array
                {
                    $rules = [];

                    foreach ($fields as $field) {
                        $type = FieldType::from($field['type']);

                        $rules[$field['key']] = [$field['required'] ? 'required' : 'nullable', ...self::rulesFor($type, $field)];

                        if ($type === FieldType::Checkboxes) {
                            $rules[$field['key'].'.*'] = [self::oneOfTheChoices($field)];
                        }
                    }

                    return $rules;
                }

                private static function rulesFor(FieldType $type, array $field): array
                {
                    return match ($type) {
                        FieldType::Text => ['string', 'max:255'],
                        FieldType::LongText => ['string', 'max:5000'],
                        FieldType::Email => ['email', 'max:255'],
                        FieldType::Phone => ['string', 'max:30', 'regex:/^[0-9+().\s-]+$/'],
                        FieldType::Date => ['date'],
                        FieldType::Number => ['numeric'],
                        FieldType::Choice => [self::oneOfTheChoices($field)],
                        FieldType::Checkboxes => ['array'],
                        FieldType::Consent => [$field['required'] ? 'accepted' : 'boolean'],
                    };
                }
                PHP)
            ->tryIt('In tinker, run `App\Forms\FormRules::for(App\Models\Form::firstWhere(\'slug\', \'contact\')->fieldDefinitions())`. You see one entry for each key, such as `contact_email => [\'required\', \'email\', \'max:255\']`.')
            ->commit('Build validation rules from field definitions')
            ->docs('https://laravel.com/docs/validation#rule-in', 'Laravel docs: The in rule')
            ->docs('https://laravel.com/docs/validation#rule-accepted', 'Laravel docs: The accepted rule')
            ->docs('https://laravel.com/docs/validation#validating-nested-array-input', 'Laravel docs: Validating nested array input')
            ->check(Check::classExists('App\Forms\FormRules')),

        Step::make('Choose a component for each type')
            ->explain(<<<'TEXT'
                The view must choose how to show each field. Put that choice in the enum, next to the type. Add two methods to `FieldType`:

                - `component()` returns the name of the Blade component for the type. Short text, email, phone, date and number all use one component, `forms.input`. Long text, one choice, several choices and consent each have their own.
                - `inputType()` returns the HTML input type: `email`, `tel`, `date`, `number` or `text`.

                Now the knowledge about a type is in two places: the enum and `FormRules`. Keep that in mind for the reflect question.
                TEXT)
            ->think('Why does phone use `type="tel"` and not `type="number"`? Think of `+1 (250) 555-0100`.')
            ->code(<<<'PHP'
                public function component(): string
                {
                    return match ($this) {
                        self::Text, self::Email, self::Phone, self::Date, self::Number => 'forms.input',
                        self::LongText => 'forms.long-text',
                        self::Choice => 'forms.choice',
                        self::Checkboxes => 'forms.checkboxes',
                        self::Consent => 'forms.consent',
                    };
                }
                PHP)
            ->tryIt('In tinker, run `App\Enums\FieldType::Phone->inputType()`. You see `tel`.')
            ->commit('Choose a component for each field type')
            ->check(Check::fileContains('app/Enums/FieldType.php', 'function component')),

        Step::make('Build the dynamic form and the field components')
            ->explain(<<<'TEXT'
                Create `resources/views/components/dynamic-form.blade.php`. It takes `fields`, `action` and an optional `submit` label. It prints the form tag and `@csrf`, then loops over the fields. For each one, `<x-dynamic-component>` shows the component that `component()` names, and passes the definition as `field`.

                Create the components in `resources/views/components/forms`. Start with `field.blade.php`: it shows the label, "(optional)" when the field is not required, the help text, the slot, and the errors. Every other component wraps its input in `<x-forms.field>`, so the label and the errors look the same everywhere.

                - `input`: an `<april:input>` with the type from `inputType()`.
                - `long-text`: an `<april:textarea>`.
                - `choice`: an `<april:native-select>` with a first empty option, "Choose one".
                - `checkboxes`: one `<april:checkbox>` for each choice, with the name `key[]`, so the browser sends an array.
                - `consent`: one checkbox, with the label of the field next to it.

                Show only the choices that are not archived. Use `old($field['key'])` for the value, so a visitor does not lose their answers after an error.
                TEXT)
            ->think('The name of each input is the field key, not the label. What would break if the input names came from the labels?')
            ->hint('`<x-dynamic-component :component="App\Enums\FieldType::from($field[\'type\'])->component()" :field="$field" />` shows the right component.')
            ->hint('For the errors of several choices, add `@error($field[\'key\'].\'.*\')` too. An error on one ticked box has the name `sizes.1`.')
            ->code(<<<'PHP'
                {{-- resources/views/components/dynamic-form.blade.php --}}
                @props([
                    'fields',
                    'action',
                    'submit' => 'Send',
                ])

                <form method="POST" action="{{ $action }}" {{ $attributes->twMerge(['space-y-6']) }}>
                    @csrf

                    @foreach ($fields as $field)
                        <x-dynamic-component :component="App\Enums\FieldType::from($field['type'])->component()" :field="$field" />
                    @endforeach

                    <april:button type="submit">{{ $submit }}</april:button>
                </form>

                {{-- resources/views/components/forms/input.blade.php --}}
                @props(['field'])

                <x-forms.field :field="$field">
                    <april:input
                        id="{{ $field['key'] }}"
                        name="{{ $field['key'] }}"
                        type="{{ App\Enums\FieldType::from($field['type'])->inputType() }}"
                        :value="old($field['key'])"
                        :required="$field['required']"
                    />
                </x-forms.field>
                PHP)
            ->tryIt('The page is not on a route yet. Go to the next step, then come back and look at each type.')
            ->commit('Add the dynamic form component')
            ->docs('https://laravel.com/docs/blade#dynamic-components', 'Laravel docs: Dynamic components')
            ->docs('https://laravel.com/docs/blade#anonymous-components', 'Laravel docs: Anonymous components')
            ->docs('https://laravel.com/docs/blade#data-properties-attributes', 'Laravel docs: Data properties and attributes')
            ->check(Check::all(
                Check::view('components.dynamic-form'),
                Check::view('components.forms.field'),
            )),

        Step::make('Show the form and check the answers')
            ->run('php artisan make:controller FormController')
            ->explain(<<<'TEXT'
                Add three public routes: `GET /forms/{form:slug}` named `forms.show`, `POST /forms/{form:slug}` named `forms.store`, and `GET /forms/{form:slug}/thanks` named `forms.thanks`. `{form:slug}` finds the form by its slug. An unknown slug gives a 404 page.

                In `FormController`, `show()` passes the form and its definitions to `forms/show.blade.php`. The view prints the name, the description and `<x-dynamic-form>`.

                `store()` gets the definitions again, builds the rules and validates. The third argument of `validate()` gives a name for each key. Pass `array_column($fields, 'label', 'key')`, so an error says "The Your name field is required." and not "The your name field...".

                After a valid post, redirect to `forms.thanks`. Do not show the thank-you page from the post itself. If the visitor reloads a page that came from a post, the browser sends the answers again.
                TEXT)
            ->think('`store()` reads the definitions from the database again. Why not send them from the page in hidden inputs?')
            ->hint('`array_column($fields, \'label\', \'key\')` turns the list into `[\'your_name\' => \'Your name\', ...]`.')
            ->code(<<<'PHP'
                public function store(Request $request, Form $form): RedirectResponse
                {
                    $fields = $form->fieldDefinitions();

                    $request->validate(FormRules::for($fields), [], array_column($fields, 'label', 'key'));

                    return to_route('forms.thanks', $form);
                }
                PHP)
            ->tryIt(<<<'TEXT'
                Run `composer dev` and open http://localhost:8000/forms/contact. Click Send with empty fields: the browser stops you, because the inputs have the `required` attribute. Now open the developer tools of the browser, delete `required` from the "Your name" input, and send again. The server shows "The Your name field is required." The server checks every rule again, because anyone can change the HTML.

                Then, as staff, open the Field library and change the label "Your name" to "Full name". Reload the form. The new label shows, with no code change.
                TEXT)
            ->commit('Show and validate stand-alone forms')
            ->docs('https://laravel.com/docs/routing#customizing-the-default-key-name', 'Laravel docs: Customizing the key of route model binding')
            ->docs('https://laravel.com/docs/validation#specifying-custom-attribute-values', 'Laravel docs: Custom attribute names')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/3', 'Laravel From Scratch, episode 3: Routing 101')
            ->check(Check::route('forms.show', 'forms.store', 'forms.thanks')),

        Step::make('Run the checks and open your pull request')
            ->run('php artisan guide:verify form-display')
            ->explain(<<<'TEXT'
                Run the acceptance test with `guide:verify`. Then run `composer ci:check`.

                If PHPStan says that an offset does not exist on an array, check your `Definition` type. Every class that reads a definition imports the same type, so you fix it in one place.

                In your pull request, add a screenshot of `/forms/contact` with an error.
                TEXT)
            ->think('A field definition in a test has no `scope` key. Does `FormRules` care? Why did we mark those keys as optional in the type?')
            ->tryIt('Run `composer ci:check`. Pint, PHPStan and all the tests pass.')
            ->commit('Fix the checks for form display')
            ->check(Check::testPassed('FormDisplayTest')),
    ]);
