<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('submissions')
    ->title('Submissions and versions')
    ->section(Section::Forms)
    ->level(3)
    ->needs(['form-display'])
    ->summary('Staff publish form versions. Each submission keeps the version that the person answered, so old answers never break.')
    ->teaches(['JSON columns as snapshots', 'hasOne of many', 'hasManyThrough', 'Service classes', 'Shallow nested resources', 'Changing an old test on purpose'])
    ->test('SubmissionsTest.php')
    ->intro(<<<'TEXT'
        The forms work, but the answers go nowhere. In this feature, you save them. That sounds easy: one table with the answers as JSON. The hard part is time.

        A parent registers a child for the summer camp in March. In May, staff change the question "School" to "Name of your school", archive the question about T-shirt sizes, and add a question about swimming. What must the March registration show? It must show the questions that the parent saw in March, with the answers that the parent gave.

        So staff do not change a live form. They change a **draft**, the fields in the `form_field` table. Then they **publish**. `FormService::publish()` saves a `FormVersion` with the next number and a JSON copy of the field definitions, as `Form::fieldDefinitions()` makes them. Archived fields are left out. The public page always shows the latest published version, and the draft can change in peace.

        A `Submission` belongs to one version. Its answers are JSON, keyed by the field key, such as `{"school": "Westsyde Elementary"}`. The staff page shows each submission with the labels of its own version.

        Both publishing and saving answers change data, so each one goes in a service. Remember the rule from "Forms and fields": **controllers do HTTP, services do the work**. `FormService` publishes versions. `FormSubmissionService` saves the answers. Applications, requests and registrations send answers too, later. They all call the same `FormSubmissionService::submit()`, so the rules for saving answers live in one place.

        Other code reads answers in two ways. `Submission::answer('school')` reads by key, and the key never changes. `Submission::answerForRole(FieldRole::EmergencyPhone)` finds the field with that role in the version, and reads its answer. The registration lists use roles, so they work after staff rename a field.
        TEXT)
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/16', 'Laravel From Scratch, episode 16: Eloquent Relationships')
    ->docs('https://laravel.com/docs/eloquent-relationships#has-one-of-many', 'Laravel docs: Has one of many')
    ->docs('https://laravel.com/docs/eloquent-mutators#array-and-json-casting', 'Laravel docs: Array and JSON casting')
    ->reflect([
        'Why copy the field definitions into each version, when the fields are already in the database?',
        'Staff fix a typo in a label and publish. Old submissions still show the typo. Is that a bug? When would you want the new label instead?',
        'The registration list needs the emergency phone. Why is `answerForRole()` safer than `answer(\'emergency_phone\')`?',
        'Which step was the hardest? What would help the next contributor? Open an issue or a pull request in blsc-guide to improve that step.',
    ])
    ->steps([
        Step::make('Claim the feature and copy in its test')
            ->run('php artisan guide:start submissions')
            ->explain(<<<'TEXT'
                Run the command below. It copies `tests/Feature/SubmissionsTest.php` into the app.

                Read the rule "an old submission keeps its old labels after staff change a label and publish". It tells the whole story of this feature: answer, rename, publish, answer again, and then each submission shows its own labels.

                The test calls `Field::factory()->type(FieldType::Email)`. If your `FieldFactory` from "Forms and fields" has no `type()` state, add it now.

                Look at the rule "a form shows its latest published version, not the draft", too. The form is not found until staff publish it. That changes how `/forms/{slug}` works, so one old test will fail. You fix it on purpose in a later step.
                TEXT)
            ->think('The test calls `app(FormService::class)->publish($form)` two times in a row with no change. What does the second version hold? Is that a problem?')
            ->tryIt('Run `php artisan test tests/Feature/SubmissionsTest.php`. All the rules fail, because `FormService` does not exist yet.')
            ->commit('Add the submissions acceptance test')
            ->docs('https://laravel.com/docs/http-tests', 'Laravel docs: HTTP tests')
            ->check(Check::fileExists('tests/Feature/SubmissionsTest.php')),

        Step::make('Create the FormVersion model and table')
            ->run('php artisan make:model FormVersion -m')
            ->explain(<<<'TEXT'
                A version has a `form_id`, a `number`, a JSON `fields` column and a `published_at` timestamp. Make the pair `form_id` and `number` unique, so a form cannot have two versions with the number 2.

                Use `restrictOnDelete()` on the foreign key. A version holds the questions of old answers. The database must refuse to delete it while its form exists.

                In the model, put `number`, `fields` and `published_at` in `#[Fillable]`. Cast `fields` to `array` and `published_at` to a datetime. Add `form()` and `submissions()`. In the docblock, write the type of `fields` as a list of definitions: `@property array<int, Definition> $fields`, with `@phpstan-import-type Definition from FormRules`. Then PHPStan knows that `$version->fields[0]['key']` is a string.
                TEXT)
            ->think('The `fields` column holds a copy of data that is also in the `fields` table. Usually, a copy is a bad idea. Why is it the right idea here?')
            ->code(<<<'PHP'
                $table->id();
                $table->foreignId('form_id')->constrained()->restrictOnDelete();
                $table->unsignedInteger('number');
                $table->json('fields');
                $table->timestamp('published_at');
                $table->timestamps();

                $table->unique(['form_id', 'number']);
                PHP)
            ->tryIt('Run `php artisan migrate`. Then, in tinker, run `Schema::getColumnListing(\'form_versions\')`.')
            ->commit('Add the FormVersion model')
            ->docs('https://laravel.com/docs/eloquent-mutators#array-and-json-casting', 'Laravel docs: Array and JSON casting')
            ->check(Check::table('form_versions', ['form_id', 'number', 'fields', 'published_at'])),

        Step::make('Create the Submission model and table')
            ->run('php artisan make:model Submission -mf')
            ->explain(<<<'TEXT'
                A submission has a `form_version_id`, a nullable `user_id`, a JSON `answers` column and a `submitted_at` timestamp. A guest can send a contact form, so `user_id` is nullable. If the user deletes their account, set it to null with `nullOnDelete()`.

                Laravel guesses the name of a relationship from the foreign key. The key is `form_version_id`, but the relationship reads better as `version()`. So pass the key: `belongsTo(FormVersion::class, 'form_version_id')`.

                Put `user_id`, `answers` and `submitted_at` in `#[Fillable]`. The version comes from the relationship, as `$version->submissions()->create()`. Cast `answers` to `array` and `submitted_at` to a datetime. Add `user()` too. In the factory, make a published form for the default version, so `Submission::factory()->create()` works alone.
                TEXT)
            ->think('Why does a submission point to a version, and not to the form?')
            ->hint('In the factory: `\'form_version_id\' => fn () => app(FormService::class)->publish(Form::factory()->create())->id`. The closure runs only when the test gives no version. You write `FormService` soon.')
            ->code(<<<'PHP'
                $table->id();
                $table->foreignId('form_version_id')->constrained()->restrictOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->json('answers');
                $table->timestamp('submitted_at');
                $table->timestamps();
                PHP)
            ->tryIt('Run `php artisan migrate`. Then, in tinker, run `Schema::getColumnListing(\'submissions\')`.')
            ->commit('Add the Submission model')
            ->docs('https://laravel.com/docs/eloquent-relationships#one-to-many-inverse', 'Laravel docs: One to many (inverse)')
            ->check(Check::table('submissions', ['form_version_id', 'user_id', 'answers', 'submitted_at'])),

        Step::make('Find the versions of a form, and the current one')
            ->explain(<<<'TEXT'
                Add three relationships to `Form`:

                - `versions(): HasMany`, sorted by `number`.
                - `currentVersion(): HasOne`. A form has many versions, but you often need only the newest. `latestOfMany('number')` makes a "has one" relationship from the version with the highest number. You can load it with `with('currentVersion')` for a list of forms, with no extra query for each form.
                - `submissions(): HasManyThrough`. A form reaches its submissions through its versions. Staff need all of them on one page.

                These methods only read. They belong in the model.
                TEXT)
            ->think('Why is `currentVersion()` a relationship, and not a method that returns `$this->versions()->latest(\'number\')->first()`? Think of a list of 30 forms.')
            ->code(<<<'PHP'
                /**
                 * The latest published version. Visitors answer this one.
                 *
                 * @return HasOne<FormVersion, $this>
                 */
                public function currentVersion(): HasOne
                {
                    return $this->hasOne(FormVersion::class)->latestOfMany('number');
                }

                /**
                 * @return HasManyThrough<Submission, FormVersion, $this>
                 */
                public function submissions(): HasManyThrough
                {
                    return $this->hasManyThrough(Submission::class, FormVersion::class);
                }
                PHP)
            ->tryIt('In tinker, run `App\Models\Form::with(\'currentVersion\')->get()->pluck(\'currentVersion\', \'name\')`. Each form has `null`, because nobody has published yet.')
            ->commit('Connect forms to their versions')
            ->docs('https://laravel.com/docs/eloquent-relationships#has-one-of-many', 'Laravel docs: Has one of many')
            ->docs('https://laravel.com/docs/eloquent-relationships#has-many-through', 'Laravel docs: Has many through')
            ->check(Check::fileContains('app/Models/Form.php', 'function currentVersion')),

        Step::make('Publish a version in the form service')
            ->run('php artisan make:class Services/FormService')
            ->explain(<<<'TEXT'
                Publishing writes a new row, so it goes in a service, not in the model or the controller. Run the command below. It makes `app/Services/FormService.php`. The form editor feature adds more methods to it later, such as `addField()`.

                Add `publish(Form $form): FormVersion`. It creates the next version with the number after the highest one, a copy of `fieldDefinitions()`, and `published_at` set to now.

                `fieldDefinitions()` already leaves out archived fields. So `publish()` does too, with no new code.

                The tests, the seeder and the staff pages all publish. They get the service from Laravel: a controller asks for it as a parameter, and other code calls `app(FormService::class)`.
                TEXT)
            ->think('Why put `publish()` in a service and not on the `Form` model? What else can happen at publish time next year?')
            ->hint('`$form->versions()->max(\'number\')` is `null` when there is no version. `null + 1` is `1` in PHP.')
            ->hint('After `publish()`, `$form->currentVersion` may still hold the old version that Eloquent loaded before. `$form->setRelation(\'currentVersion\', $version)` updates it.')
            ->code(<<<'PHP'
                class FormService
                {
                    /**
                     * Publish the fields as they are now. Save a copy of their definitions as the next version.
                     */
                    public function publish(Form $form): FormVersion
                    {
                        $version = $form->versions()->create([
                            'number' => $form->versions()->max('number') + 1,
                            'fields' => $form->fieldDefinitions(),
                            'published_at' => now(),
                        ]);

                        $form->setRelation('currentVersion', $version);

                        return $version;
                    }
                }
                PHP)
            ->tryIt('In tinker, run `$form = App\Models\Form::firstWhere(\'slug\', \'contact\'); $forms = app(App\Services\FormService::class); $forms->publish($form); $forms->publish($form)->number`. You see the number of the second version. Then run `$form->fresh()->currentVersion->number`.')
            ->commit('Publish form versions')
            ->docs('https://laravel.com/docs/container#zero-configuration-resolution', 'Laravel docs: Zero configuration resolution')
            ->check(Check::fileContains('app/Services/FormService.php', 'function publish')),

        Step::make('Read answers by key and by role')
            ->explain(<<<'TEXT'
                Add two methods to `Submission`. Later features call them, so keep the names.

                `answer(string $key): mixed` returns the answer under that key, or `null`. A key that the version did not ask gives `null` too. That is the "not asked" case.

                `answerForRole(FieldRole $role): mixed` looks in the fields of the submission's own version for the field with that role. Then it returns the answer to that field's key. It reads the role from the version, not from the library. If staff later move the role to a new field with a new key, old submissions still find their answer under the old key.
                TEXT)
            ->think('Why does `answerForRole()` look in `$this->version->fields` and not in the `fields` table?')
            ->hint('`collect($this->version->fields)->firstWhere(\'role\', $role->value)` finds the definition with that role, or `null`.')
            ->code(<<<'PHP'
                public function answer(string $key): mixed
                {
                    return $this->answers[$key] ?? null;
                }

                public function answerForRole(FieldRole $role): mixed
                {
                    $field = collect($this->version->fields)->firstWhere('role', $role->value);

                    return $field === null ? null : $this->answer($field['key']);
                }
                PHP)
            ->tryIt('Run `php artisan test tests/Feature/SubmissionsTest.php --filter=role`. The role rule still fails, because nobody can send answers yet. You fix that next.')
            ->commit('Read submission answers by key and role')
            ->check(Check::fileContains('app/Models/Submission.php', 'function answerForRole')),

        Step::make('Save the answers in a service')
            ->run('php artisan make:class Services/FormSubmissionService')
            ->explain(<<<'TEXT'
                Saving answers is the core of every form: the contact form now, and applications, requests and registrations later. Put it in one service, `FormSubmissionService`, with two methods. Later features call them, so keep the names and the parameters.

                `fields(FormVersion $version, ?User $user = null, ?Child $child = null): array` returns the field definitions to show and to validate. For now, it returns `$version->fields`.

                `submit(FormVersion $version, array $validated, ?User $user = null, ?Child $child = null): Submission` creates the submission through `$version->submissions()`. Build the answers from the fields of the version, not from the request: one entry for each key, with `null` for a question with no answer. A visitor cannot add extra keys, and every submission has the same keys as its version.

                Both methods take a user and a child that they do not use yet. The "Saved profile answers" feature fills in the answers of a family and a child, and saves new answers to their profile. Because every caller already passes them, that feature changes this one class and no controller.

                The service does not validate. Validation reads the request, and that is HTTP work. The controller validates and gives the service the clean answers.
                TEXT)
            ->think('Why build the answers from the version\'s fields and not save `$request->all()`?')
            ->hint('`collect($version->fields)->mapWithKeys(fn (array $field) => [$field[\'key\'] => $validated[$field[\'key\']] ?? null])->all()` gives one answer for each key.')
            ->code(<<<'PHP'
                class FormSubmissionService
                {
                    /**
                     * The fields to show and to validate.
                     *
                     * @return array<int, Definition>
                     */
                    public function fields(FormVersion $version, ?User $user = null, ?Child $child = null): array
                    {
                        return $version->fields;
                    }

                    /**
                     * Save validated answers as a new submission. A question with no answer is saved as null.
                     *
                     * @param  array<string, mixed>  $validated
                     */
                    public function submit(FormVersion $version, array $validated, ?User $user = null, ?Child $child = null): Submission
                    {
                        return $version->submissions()->create([
                            'user_id' => $user?->id,
                            'answers' => collect($version->fields)->mapWithKeys(fn (array $field) => [
                                $field['key'] => $validated[$field['key']] ?? null,
                            ])->all(),
                            'submitted_at' => now(),
                        ]);
                    }
                }
                PHP)
            ->tryIt('In tinker, run `$version = App\Models\Form::firstWhere(\'slug\', \'contact\')->currentVersion; app(App\Services\FormSubmissionService::class)->submit($version, [\'message\' => \'Hello\', \'extra\' => \'x\'])->answers`. You see every key of the version, with `null` for the empty ones, and no `extra`.')
            ->commit('Save submissions in a service')
            ->docs('https://laravel.com/docs/eloquent-relationships#the-create-method', 'Laravel docs: The create method')
            ->check(Check::fileContains('app/Services/FormSubmissionService.php', 'function submit')),

        Step::make('Answer the current version')
            ->explain(<<<'TEXT'
                Change the public `FormController`. Both `show()` and `store()` now use `$form->currentVersion`. If there is none, the form is not published, so stop with a 404 page. Both ask for `FormSubmissionService` as a parameter.

                `show()` passes `$submissions->fields($version)` to the view. The view and `<x-dynamic-form>` do not change, because a version holds the same definitions as before. That is the payoff of the array contract from the last feature.

                `store()` does the HTTP work: it gets the fields from the service, validates the request against them, calls `submit()`, and redirects.

                Now run all the tests. The tests in `FormDisplayTest.php` fail: their forms are not published, so the pages are 404. That is correct now. Add `app(FormService::class)->publish($form);` at the end of the helper `formForDisplay()`, before `return $form;`, and import `App\Services\FormService`. Change an old test only when the behaviour changed on purpose, as here.
                TEXT)
            ->think('The controller validates, and the service saves. What would go wrong if the service read `request()` itself? Think of a test, or a console command that imports old answers.')
            ->hint('`$form->currentVersion ?? abort(404)` gives the version or stops with a 404 page.')
            ->hint('`$request->user()` is the signed-in person, or `null` for a guest. Pass it to both methods.')
            ->code(<<<'PHP'
                public function store(Request $request, Form $form, FormSubmissionService $submissions): RedirectResponse
                {
                    $version = $form->currentVersion ?? abort(404);
                    $fields = $submissions->fields($version, $request->user());

                    $validated = $request->validate(FormRules::for($fields), [], array_column($fields, 'label', 'key'));

                    $submissions->submit($version, $validated, $request->user());

                    return to_route('forms.thanks', $form);
                }
                PHP)
            ->tryIt('Run `php artisan test`. All the tests in `FormDisplayTest.php` pass again after you publish the form in the helper.')
            ->commit('Save submissions to the current version')
            ->docs('https://laravel.com/docs/controllers#method-injection', 'Laravel docs: Method injection')
            ->docs('https://laravel.com/docs/errors#http-exceptions', 'Laravel docs: HTTP exceptions')
            ->check(Check::fileContains('app/Http/Controllers/FormController.php', 'FormSubmissionService')),

        Step::make('Turn an answer into text')
            ->explain(<<<'TEXT'
                The database stores `grade_1`. Staff want to read "Grade 1". A consent is `true`, but staff want "Yes". The staff pages need this now, and the export needs it in a later feature. So write it once.

                Create `app/Forms/AnswerText.php` with `for(array $field, mixed $value): string`. Use a `match` on the type:

                - Consent: "Yes" or "No".
                - One choice: the label of the choice. Archived choices are still in the definition, so old answers still find their label.
                - Several choices: the labels, joined with a comma.
                - Every other type: the value as text.
                TEXT)
            ->think('Staff renamed the choice "Grade 1" to "First grade" last month. Which label does an old submission show? Why?')
            ->hint('`array_column($field[\'options\'], \'label\', \'value\')` gives `[\'grade_1\' => \'Grade 1\', ...]`.')
            ->code(<<<'PHP'
                public static function for(array $field, mixed $value): string
                {
                    $labels = array_column($field['options'], 'label', 'value');
                    $label = fn (mixed $choice): string => $labels[self::text($choice)] ?? self::text($choice);

                    return match (FieldType::from($field['type'])) {
                        FieldType::Consent => $value ? 'Yes' : 'No',
                        FieldType::Choice => $label($value),
                        FieldType::Checkboxes => collect(is_array($value) ? $value : [])->map($label)->join(', '),
                        default => self::text($value),
                    };
                }

                private static function text(mixed $value): string
                {
                    return is_scalar($value) ? (string) $value : '';
                }
                PHP)
            ->tryIt('In tinker, run `App\Forms\AnswerText::for(App\Models\Field::firstWhere(\'key\', \'grade\')->definition(), \'grade_1\')`. You see "Grade 1".')
            ->commit('Show answers as text')
            ->check(Check::classExists('App\Forms\AnswerText')),

        Step::make('Show the submissions to staff')
            ->run("php artisan make:controller Admin/FormController\nphp artisan make:controller Admin/SubmissionController")
            ->explain(<<<'TEXT'
                Staff need three pages: a list of forms, the submissions of one form, and one submission.

                Add `Route::get('forms', ...)->name('forms.index')` in the staff group. `Admin\FormController::index()` lists the forms with `with('currentVersion')` and `withCount('submissions')`.

                For the submissions, use a nested resource: `Route::resource('forms.submissions', ...)->shallow()->only(['index', 'show'])`. The list is under the form (`admin/forms/{form}/submissions`). A submission has its own ID, so `shallow()` gives it a short URL (`admin/submissions/{submission}`).

                The list shows the newest first, with the date, the person ("Guest" when there is no user), the version number and the first answer. Paginate it: a busy form gets many submissions.

                The show page loops over `$submission->version->fields`, not over the fields of the form. So each submission shows the labels of its own version. Use `AnswerText::for()` for each answer. Give every link on these pages `wire:navigate`. Add a "Forms" link to the staff menu in `resources/views/layouts/app.blade.php`, after "Field library", with `wire:navigate` too.
                TEXT)
            ->think('The show page loops over the version\'s fields. A newer version added a question. What does an old submission show for it?')
            ->hint('`$form->submissions()->with([\'version\', \'user\'])->latest(\'submitted_at\')->paginate(50)` loads what the list needs in three queries.')
            ->code(<<<'PHP'
                Route::get('forms', [Admin\FormController::class, 'index'])->name('forms.index');
                Route::resource('forms.submissions', Admin\SubmissionController::class)->shallow()->only(['index', 'show']);

                // resources/views/admin/submissions/show.blade.php
                @foreach ($submission->version->fields as $field)
                    <dt class="font-medium">{{ $field['label'] }}</dt>
                    <dd>{{ App\Forms\AnswerText::for($field, $submission->answer($field['key'])) ?: '—' }}</dd>
                @endforeach
                PHP)
            ->tryIt('Run `php artisan route:list --name=submissions`. You see `admin.forms.submissions.index` and `admin.submissions.show`.')
            ->commit('Show submissions to staff')
            ->docs('https://laravel.com/docs/controllers#shallow-nesting', 'Laravel docs: Shallow nesting')
            ->docs('https://laravel.com/docs/pagination#paginating-eloquent-results', 'Laravel docs: Paginating Eloquent results')
            ->check(Check::route('admin.forms.index', 'admin.forms.submissions.index', 'admin.submissions.show')),

        Step::make('Publish the contact form in the seeder')
            ->explain(<<<'TEXT'
                The contact form from `FormSeeder` is a draft now, so `/forms/contact` is 404. At the end of the seeder, publish it if it has no version yet.

                Check first, so the seeder can run again without a new version each time. The form editor in the next feature gives staff a Publish button for every other change.
                TEXT)
            ->think('Why not publish in every run of the seeder?')
            ->code(<<<'PHP'
                if ($contact->currentVersion === null) {
                    app(FormService::class)->publish($contact);
                }
                PHP)
            ->tryIt(<<<'TEXT'
                Run `php artisan db:seed --class=FormSeeder` and `composer dev`. Open http://localhost:8000/forms/contact, fill it in and send it.

                Log in as staff and open Forms. The contact form has one submission. Open it: you see your answers with the labels of version 1.

                Now change the label "Your name" to "Full name" in the Field library. In tinker, run `app(App\Services\FormService::class)->publish(App\Models\Form::firstWhere('slug', 'contact'))`. Send the form again. The old submission still says "Your name". The new one says "Full name".
                TEXT)
            ->commit('Publish the seeded contact form')
            ->check(Check::fileContains('database/seeders/FormSeeder.php', 'FormService')),

        Step::make('Run the checks and open your pull request')
            ->run('php artisan guide:verify submissions')
            ->explain(<<<'TEXT'
                Run the acceptance test with `guide:verify`. Then run `composer ci:check`.

                In your pull request, explain the change to `FormDisplayTest.php`: a form is now public only after staff publish it. Reviewers must know that you changed an old test on purpose.
                TEXT)
            ->think('A reviewer pulls your branch and opens `/forms/contact`. They see 404. What did they forget to run?')
            ->tryIt('Run `composer ci:check`. Pint, PHPStan and all the tests pass.')
            ->commit('Fix the checks for submissions')
            ->check(Check::testPassed('SubmissionsTest')),
    ]);
