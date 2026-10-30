<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('profile-answers')
    ->title('Saved profile answers')
    ->section(Section::Forms)
    ->level(3)
    ->needs(['submissions', 'child-profiles'])
    ->summary('Answers to child and family fields are saved to the profile and filled in next time. Parents confirm sensitive answers each year.')
    ->teaches(['Polymorphic relationships', 'JSON casts', 'Service classes', 'Scoped route bindings', 'Model events', 'Overriding a package view'])
    ->test('ProfileAnswersTest.php')
    ->intro(<<<'TEXT'
        A family signs up for a summer camp, a Saturday club and a homeschool class. Each form asks the same questions: the school, the grade, the allergies, the emergency contact. Today the parent types every answer again on every form. In this feature, the parent types each answer once.

        The field library already tells you which answers to keep. Open `app/Enums/FieldScope.php`. A **child** field, such as "Allergies", describes one child. A **family** field, such as "Emergency contact phone", describes the whole family. A **sign-up** field, such as "Message", belongs only to one form, and the app never saves it to a profile.

        A new model, `ProfileValue`, holds one saved answer. It has an **owner**, the child or the parent account. One table holds both kinds, so the owner is a polymorphic relationship: the row stores the type of the owner and its ID. It also stores the `field_id`, the `value` as JSON, and `confirmed_at`, the date that the parent last said "this is correct".

        This is the path of a request. A parent opens `/forms/camp-health?child=3`. `FormController::show()` checks that child 3 belongs to the parent. Then `ProfileValues::fill()` adds a `value` to each child and family field, and sets `stale` on a sensitive answer that is too old. The form shows the values, and asks "Is this still correct?" next to each stale answer. On submit, the controller calls `fill()` again, because only the server decides what is stale. `FormRules` adds a rule: the parent must check the confirmation box. The submission keeps its own copy of the answers. Then `ProfileValues::save()` updates the profile.

        Keep `ProfileValues` out of the controller. The Registrations feature calls `fill()` and `save()` too, for the form of a program. One service means one set of rules for every form.

        Last, the parent controls the data. A "Saved answers" page for each child shows every saved answer. The parent changes or deletes each one. A policy stops other families, and deleting a child deletes its answers. Many of these answers describe children, so the app keeps them only while the family wants them.
        TEXT)
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/16', 'Laravel From Scratch, episode 16: Eloquent Relationships')
    ->docs('https://laravel.com/docs/eloquent-relationships#one-to-many-polymorphic-relations', 'Laravel docs: One to many polymorphic relations')
    ->docs('https://laravel.com/docs/eloquent-mutators#array-and-json-casting', 'Laravel docs: Array and JSON casting')
    ->reflect([
        'A parent updates the allergies in March. What does the January registration show? Why?',
        'On submit, the controller calls `fill()` again to find the stale answers. Why does it not read them from a hidden input in the form?',
        'A parent clears the "Medical notes" box and sends the form. The app deletes the saved answer. Would it be better to keep the old answer? Who could be hurt in each case?',
        'Which step was the hardest? What would help the next contributor? Open an issue or a pull request in blsc-guide to improve that step.',
    ])
    ->steps([
        Step::make('Claim the feature and copy in its test')
            ->run('php artisan guide:start profile-answers')
            ->explain(<<<'TEXT'
                Run the command below. It copies `tests/Feature/ProfileAnswersTest.php` into the app.

                Read the helper `profileFields()` first. It makes one field of each kind: two child fields, one family field and one sign-up field. "Allergies" is sensitive, and the factory state `sensitive()` sets `review_after_months` to 12.

                Then read the rule "a stale sensitive answer asks for confirmation". It uses `travelTo()` to move the clock. After six months, the form fills in the answer and asks nothing. After more than twelve months, the form asks "Is this still correct?", and the parent must check a box called `confirm[allergies]`.

                Look at how the tests choose a child: `route('forms.store', [$form, 'child' => $child->id])`. The child is a query parameter in the URL, such as `?child=3`.
                TEXT)
            ->think('The test "a parent cannot see or change another family\'s answers" expects 404 on the form, but 403 on the Saved answers page. Why is the status different?')
            ->tryIt('Run `php artisan test tests/Feature/ProfileAnswersTest.php`. All the rules fail, because the `ProfileValue` model does not exist yet.')
            ->commit('Add the profile answers acceptance test')
            ->docs('https://laravel.com/docs/mocking#interacting-with-time', 'Laravel docs: Interacting with time in tests')
            ->check(Check::fileExists('tests/Feature/ProfileAnswersTest.php')),

        Step::make('Create the ProfileValue model and table')
            ->run('php artisan make:model ProfileValue -mf')
            ->explain(<<<'TEXT'
                Run the command below. It makes the model, a migration and a factory.

                A saved answer belongs to a child or to a parent account. You could add two columns, `child_id` and `user_id`, and leave one empty. A polymorphic relationship is cleaner. `$table->morphs('owner')` adds two columns: `owner_type` holds the class of the owner, and `owner_id` holds its ID. It also adds an index on both.

                Add the other columns. `field_id` is a foreign key to `fields`. `value` is a `json` column, because an answer can be text, a date or a list of choices. `confirmed_at` is the date that the parent last confirmed the answer.

                A child has one answer for each field, not two. Add a unique index on `owner_type`, `owner_id` and `field_id`. Then the database refuses a second "Allergies" for the same child.
                TEXT)
            ->think('Staff never delete a field. They archive it. So which `onDelete` behaviour is right for `field_id`? Does it matter much?')
            ->hint('`$table->morphs(\'owner\')` must come before the unique index that uses its columns.')
            ->hint('`$table->unique([\'owner_type\', \'owner_id\', \'field_id\'])` makes one index from three columns.')
            ->code(<<<'PHP'
                Schema::create('profile_values', function (Blueprint $table) {
                    $table->id();
                    $table->morphs('owner');
                    $table->foreignId('field_id')->constrained()->cascadeOnDelete();
                    $table->json('value');
                    $table->timestamp('confirmed_at');
                    $table->timestamps();

                    $table->unique(['owner_type', 'owner_id', 'field_id']);
                });
                PHP)
            ->tryIt('Run `php artisan migrate`. Then, in `php artisan tinker`, run `Schema::getColumnListing(\'profile_values\')`. You see `owner_type` and `owner_id` next to your columns.')
            ->commit('Add the ProfileValue model and table')
            ->docs('https://laravel.com/docs/migrations#column-method-morphs', 'Laravel docs: The morphs column method')
            ->docs('https://laravel.com/docs/eloquent-relationships#polymorphic-relationships', 'Laravel docs: Polymorphic relationships')
            ->check(Check::table('profile_values', ['owner_type', 'owner_id', 'field_id', 'value', 'confirmed_at'])),

        Step::make('Fill in the model, and decide when an answer is stale')
            ->explain(<<<'TEXT'
                Open `app/Models/ProfileValue.php`. Add `field_id`, `value` and `confirmed_at` to `#[Fillable]`. Do not add `owner_type` or `owner_id`. The relationship sets them, as you see in the next step.

                Cast `value` to `json`. Laravel then turns the answer into JSON when it saves, and back when it reads. A text answer comes back as a string, and a list of choices comes back as an array. Cast `confirmed_at` to `datetime`.

                Add two relationships. `owner(): MorphTo` returns the child or the user. `field(): BelongsTo` returns the field in the library.

                Then add `needsReview()`. An answer needs review when its field is sensitive, the field has a `review_after_months`, and the parent confirmed the answer before that many months ago. Staff set the number of months on the field. The code never names a field, such as "Allergies".

                Last, fill in the factory. The default owner is a new child, and the default field is a new child field. The tests use `ProfileValue::factory()->for($child, 'owner')` to choose the owner.
                TEXT)
            ->think('A field is sensitive, but `review_after_months` is empty. Must the parent confirm the answer? What does your code do?')
            ->hint('A `MorphTo` method needs no arguments: `return $this->morphTo();`. Laravel finds the columns from the method name `owner`.')
            ->hint('`now()->subMonths($months)` is the date that many months ago. An answer is stale when `confirmed_at` is before that date: `$this->confirmed_at->lt(...)`.')
            ->hint('In the factory, set `owner_type` to `(new Child)->getMorphClass()` and `owner_id` to `Child::factory()`.')
            ->code(<<<'PHP'
                /**
                 * True when the field is sensitive and the parent confirmed the answer too long ago.
                 */
                public function needsReview(): bool
                {
                    $months = $this->field->review_after_months;

                    return $this->field->sensitive
                        && $months !== null
                        && $this->confirmed_at->lt(now()->subMonths($months));
                }
                PHP)
            ->tryIt('In `php artisan tinker`, run `$value = App\Models\ProfileValue::factory()->create([\'value\' => [\'k\', \'grade_1\']])`. Then run `$value->value` and `$value->owner->name`. You see the array again and the name of a new child.')
            ->commit('Fill in the ProfileValue model')
            ->docs('https://laravel.com/docs/eloquent-relationships#one-to-many-polymorphic-relations', 'Laravel docs: One to many polymorphic relations')
            ->docs('https://laravel.com/docs/eloquent-mutators#array-and-json-casting', 'Laravel docs: Array and JSON casting')
            ->docs('https://laravel.com/docs/eloquent-factories#polymorphic-relationships', 'Laravel docs: Polymorphic relationships in factories')
            ->check(Check::fileContains('app/Models/ProfileValue.php', 'function needsReview')),

        Step::make('Give children and families their saved answers')
            ->explain(<<<'TEXT'
                Now the other side of the relationship. Add `profileValues(): MorphMany` to `Child` and to `User`. Both methods are the same: `$this->morphMany(ProfileValue::class, 'owner')`. The second argument is the name of the relationship on `ProfileValue`.

                With these methods, `$child->profileValues()->create([...])` sets `owner_type` and `owner_id` for you. That is why they are not fillable.

                The `owner_id` column has no foreign key, because it can point to two tables. So the database does not delete the answers of a deleted child. Do it in the model. In `Child`, add a `booted()` method with a `deleting` event that deletes the profile values of the child.

                The parent can also delete the whole account. The database then deletes the children through their foreign key, and no model event runs. So in `User`, add a `deleting` event too. It deletes the family answers and then each child through the model, so the event of each child runs.
                TEXT)
            ->think('Why does `$user->children->each->delete()` delete the answers of the children, but `$user->children()->delete()` does not?')
            ->hint('Copy the `children()` method in `User`. Change `HasMany` to `MorphMany` and `hasMany` to `morphMany`.')
            ->hint('`static::deleting(function (Child $child) { ... })` runs before Laravel deletes the row.')
            ->code(<<<'PHP'
                /**
                 * Delete the saved answers of a child together with the child.
                 */
                protected static function booted(): void
                {
                    static::deleting(function (Child $child) {
                        $child->profileValues()->delete();
                    });
                }

                /**
                 * The saved answers to child fields.
                 *
                 * @return MorphMany<ProfileValue, $this>
                 */
                public function profileValues(): MorphMany
                {
                    return $this->morphMany(ProfileValue::class, 'owner');
                }
                PHP)
            ->tryIt('In `php artisan tinker`, run `$value = App\Models\ProfileValue::factory()->create()`. Then run `$value->owner->delete()` and `App\Models\ProfileValue::find($value->id)`. You see `null`: the answer went with the child.')
            ->commit('Connect saved answers to children and users')
            ->docs('https://laravel.com/docs/eloquent-relationships#one-to-many-polymorphic-relations', 'Laravel docs: One to many polymorphic relations')
            ->docs('https://laravel.com/docs/eloquent#events-using-closures', 'Laravel docs: Model events with closures')
            ->check(Check::all(
                Check::fileContains('app/Models/Child.php', 'function profileValues'),
                Check::fileContains('app/Models/User.php', 'function profileValues'),
            )),

        Step::make('Write the ProfileValues service')
            ->run('php artisan make:class Forms/ProfileValues')
            ->explain(<<<'TEXT'
                This class is the heart of the feature. Two pages use it now and later: the public form and the registration flow. So it does not read the request. It takes the field definitions, the user and the child, and nothing else.

                `fill(array $fields, User $user, ?Child $child): array` returns the same definitions with two new keys. `value` is the saved answer, or `null`. `stale` is `true` when the parent must confirm the answer. Load the answers of the child and of the family one time each, keyed by the field key. Then, for each field, its `scope` tells you where to look. A sign-up field never gets a value.

                `save(array $fields, array $answers, User $user, ?Child $child): void` goes the other way. For each child and family field, it saves the answer to the right owner with `updateOrCreate()`, and sets `confirmed_at` to now. Sending the form counts as confirming the answers. When the answer is empty, it deletes the saved answer: the newest answer of the parent wins. Without a child, it skips the child fields.

                Both methods need the owner of a field. Put that in one private method with a `match` on the scope. Then the rule "child fields belong to the child" lives in one place.

                Last, open `app/Forms/FormRules.php`. Add `value?: mixed` and `stale?: bool` to the `Definition` type, so PHPStan knows the new keys.
                TEXT)
            ->think('`save()` finds each field by its key, not by an ID in the definition. Why do the definitions in a form version have no `id`?')
            ->hint('`$child?->profileValues()->with(\'field\')->get()->keyBy(\'field.key\')` gives the answers of a child, keyed by the key of the field. `keyBy()` reads a dot path.')
            ->hint('`Field::whereIn(\'key\', array_column($fields, \'key\'))->pluck(\'id\', \'key\')` finds the ID of each field in one query.')
            ->hint('`$owner->profileValues()->updateOrCreate([\'field_id\' => $id], [\'value\' => $answer, \'confirmed_at\' => now()])` changes the old answer, or creates one.')
            ->code(<<<'PHP'
                public function fill(array $fields, User $user, ?Child $child): array
                {
                    $saved = [
                        FieldScope::Child->value => $this->answersOf($child),
                        FieldScope::Family->value => $this->answersOf($user),
                        FieldScope::Signup->value => new Collection,
                    ];

                    return array_map(function (array $field) use ($saved) {
                        $value = $saved[$field['scope'] ?? FieldScope::Signup->value]->get($field['key']);

                        return [...$field, 'value' => $value?->value, 'stale' => $value?->needsReview() ?? false];
                    }, $fields);
                }

                /**
                 * The profile that keeps the answer to this field, or null for a sign-up field.
                 */
                private function owner(array $field, User $user, ?Child $child): Child|User|null
                {
                    return match ($field['scope'] ?? null) {
                        FieldScope::Child->value => $child,
                        FieldScope::Family->value => $user,
                        default => null,
                    };
                }
                PHP)
            ->tryIt(<<<'TEXT'
                Run `php artisan migrate:fresh --seed`. In `php artisan tinker`, make a child and a list of seeded fields:

                `$user = App\Models\User::first(); $child = $user->children()->create(['name' => 'Sam', 'birthdate' => '2017-05-01']);`

                `$fields = App\Models\Field::whereIn('key', ['school', 'allergies', 'emergency_phone'])->get()->map->definition()->all(); $p = app(App\Forms\ProfileValues::class);`

                `$p->save($fields, ['school' => 'Westsyde Elementary', 'allergies' => 'Peanuts', 'emergency_phone' => '250-555-0100'], $user, $child);`

                Now run `collect($p->fill($fields, $user, $child))->pluck('value', 'key')`. You see all three answers. Run it again with `null` instead of `$child`. You see only the emergency phone, because it is a family field.
                TEXT)
            ->commit('Add the ProfileValues service')
            ->docs('https://laravel.com/docs/eloquent#upserts', 'Laravel docs: Upserts and updateOrCreate')
            ->docs('https://laravel.com/docs/eloquent-relationships#the-create-method', 'Laravel docs: Saving related models')
            ->docs('https://laravel.com/docs/container#automatic-injection', 'Laravel docs: Automatic injection')
            ->check(Check::all(
                Check::fileContains('app/Forms/ProfileValues.php', 'function fill'),
                Check::fileContains('app/Forms/ProfileValues.php', 'function save'),
            )),

        Step::make('Require a confirmation for a stale answer')
            ->explain(<<<'TEXT'
                A stale answer is not wrong. The parent only has to look at it again. So the form shows a checkbox next to it, `confirm[allergies]`, and the server refuses the form until the parent checks it.

                `FormRules::for()` already builds the rules from the definitions. Now a definition can carry `stale`. When it is `true`, add the rule `accepted` for `confirm.` plus the key. The dot is how Laravel names a value inside an array.

                The default message would say "The confirm.allergies field must be accepted." Parents do not know what that means. Add a static `messages()` method to `FormRules` with one wildcard message for every confirmation: `'confirm.*.accepted'`. The form controller passes it to `validate()`.
                TEXT)
            ->think('The parent changes the allergies from "Peanuts" to "Peanuts and eggs". Must they still check the box? Is that a good rule, or would you change it?')
            ->hint('Put the new rule inside the `foreach` in `for()`, after the checkbox rule: `if ($field[\'stale\'] ?? false) { ... }`.')
            ->code(<<<'PHP'
                if ($field['stale'] ?? false) {
                    $rules['confirm.'.$field['key']] = ['accepted'];
                }

                // ...

                /**
                 * The error messages that the generated rules need.
                 *
                 * @return array<string, string>
                 */
                public static function messages(): array
                {
                    return ['confirm.*.accepted' => 'Confirm that this answer is still correct.'];
                }
                PHP)
            ->tryIt('In `php artisan tinker`, run `App\Forms\FormRules::for([[...App\Models\Field::firstWhere(\'key\', \'allergies\')->definition(), \'stale\' => true]])`. You see a rule for `allergies` and a rule `accepted` for `confirm.allergies`.')
            ->commit('Require a confirmation for stale answers')
            ->docs('https://laravel.com/docs/validation#rule-accepted', 'Laravel docs: The accepted rule')
            ->docs('https://laravel.com/docs/validation#specifying-a-custom-message-for-a-given-attribute', 'Laravel docs: A custom message for an attribute')
            ->check(Check::fileContains('app/Forms/FormRules.php', "'stale'")),

        Step::make('Choose a child, fill in the form, and save the answers')
            ->explain(<<<'TEXT'
                Now connect the service to `FormController`. Type-hint `ProfileValues $profile` in `show()` and `store()`. Laravel makes the object for you.

                First, find the child. Add a private method `chosenChild()`. It reads the `child` query parameter, and looks for the child only in `$request->user()->children()`. Use `findOrFail()`: a child of another family is "not found" for this parent. A guest has no children, so the method returns `null`.

                In `show()`, call `fill()` for a signed-in user. A guest gets the plain definitions. Pass the children to the view too, but only when the form has a child field. A form with only family fields does not need to ask about a child.

                In `store()`, call `fill()` again before you validate. The server decides which answers are stale. A browser can leave out a checkbox or add a hidden input, but it cannot change the dates in your database. Validate with `FormRules::for($fields)` and `FormRules::messages()`. Create the submission as before: it keeps its own copy of the answers. Then call `save()` with the answers of the submission.
                TEXT)
            ->think('The submission and the profile both store "Peanuts". Later the parent changes the profile to "None". Which copy must the camp staff see for the January camp? Why are there two copies?')
            ->hint('`$request->filled(\'child\')` is false when the parameter is missing or empty. `$request->integer(\'child\')` reads it as a number.')
            ->hint('`collect($version->fields)->contains(\'scope\', FieldScope::Child->value)` tells you if the form has a child field.')
            ->hint('Call `save()` only when `$request->user()` is not `null`. A guest has no profile.')
            ->code(<<<'PHP'
                public function store(Request $request, Form $form, ProfileValues $profile): RedirectResponse
                {
                    $version = $form->currentVersion ?? abort(404);
                    $user = $request->user();
                    $child = $this->chosenChild($request);
                    $fields = $user ? $profile->fill($version->fields, $user, $child) : $version->fields;

                    $validated = $request->validate(FormRules::for($fields), FormRules::messages(), array_column($fields, 'label', 'key'));

                    $submission = $version->submissions()->create([/* as before */]);

                    if ($user) {
                        $profile->save($version->fields, $submission->answers, $user, $child);
                    }

                    // The notification, as before.

                    return to_route('forms.thanks', $form);
                }

                /**
                 * The child that the form is about, from the "child" query parameter.
                 * A parent can only choose one of their own children.
                 */
                private function chosenChild(Request $request): ?Child
                {
                    if ($request->user() === null || ! $request->filled('child')) {
                        return null;
                    }

                    return $request->user()->children()->findOrFail($request->integer('child'));
                }
                PHP)
            ->tryIt('Run `php artisan test tests/Feature/ProfileAnswersTest.php --filter="saved to the child"`. The rule "a parent\'s answers are saved to the child and to the family" passes. The rules that look at the page still fail, because the form does not show the values yet.')
            ->commit('Fill in and save profile answers on forms')
            ->docs('https://laravel.com/docs/requests#retrieving-input-from-the-query-string', 'Laravel docs: Input from the query string')
            ->docs('https://laravel.com/docs/controllers#method-injection', 'Laravel docs: Method injection')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/11', 'Laravel From Scratch, episode 11: Request Validation')
            ->check(Check::fileContains('app/Http/Controllers/FormController.php', 'ProfileValues')),

        Step::make('Show the saved answers in the fields')
            ->run('php artisan vendor:publish --tag=april-view-textarea')
            ->explain(<<<'TEXT'
                Each field component in `resources/views/components/forms` reads `old($field['key'])`. That is the input after a validation error. Give `old()` a second argument, the saved value: `old($field['key'], $field['value'] ?? null)`. The input after an error still wins, and the saved value comes next. Do it in all five components. For checkboxes, the default is `[]`. For consent, it is `false`.

                Now test the long text field. It stays empty. Open `vendor/yungifez/april-ui/resources/views/components/textarea.blade.php`. The `<textarea>` has no `{{ $slot }}`, so April UI drops the text between the tags. The same problem empties the help text on the staff edit pages.

                Never change files in `vendor`. Composer replaces them. Laravel lets an app override one view of a package instead. Run the command below. It copies the view to `resources/views/vendor/april/components/textarea.blade.php`, and Laravel uses your copy. Put `{{ $slot }}` between the tags.

                A copy does not get the fixes of new April UI versions. So also tell the April UI maintainers about the problem. When they fix it, delete your copy.
                TEXT)
            ->think('Why does `old()` come first and the saved value second? What would the parent see after a validation error if it were the other way?')
            ->hint('In `choice.blade.php`, change `@selected(old($field[\'key\']) === $option[\'value\'])` in the same way.')
            ->hint('The end of the published textarea becomes `])}}>{{ $slot }}</textarea>`.')
            ->code(<<<'PHP'
                {{-- resources/views/components/forms/long-text.blade.php --}}
                <april:textarea id="{{ $field['key'] }}" name="{{ $field['key'] }}" rows="4" class="w-full" :required="$field['required']">{{ old($field['key'], $field['value'] ?? null) }}</april:textarea>
                PHP)
            ->tryIt('Log in as staff and open Fields. Edit a field that has help text. Before the fix, the help text box is empty. After the fix, you see the text.')
            ->commit('Show saved answers in the form fields')
            ->docs('https://laravel.com/docs/requests#retrieving-old-input', 'Laravel docs: Retrieving old input')
            ->docs('https://laravel.com/docs/packages#overriding-package-views', 'Laravel docs: Overriding package views')
            ->check(Check::all(
                Check::fileContains('resources/views/components/forms/long-text.blade.php', "\$field['value']"),
                Check::fileContains('resources/views/vendor/april/components/textarea.blade.php', '{{ $slot }}'),
            )),

        Step::make('Ask which child, and ask to confirm')
            ->explain(<<<'TEXT'
                Two small changes to the views finish the public form.

                In `resources/views/forms/show.blade.php`, show a child picker above the form when `$children` is not empty. Keep it simple: a `GET` form with a select called `child` and a button. When the parent sends it, the page opens again with `?child=3` and the answers of that child. Then give the child to the address of the real form: `route('forms.store', ['form' => $form, 'child' => $child?->id])`. When there is no child, Laravel leaves the parameter out.

                In `resources/views/components/dynamic-form.blade.php`, show a box under each field that has `stale`. It has the question "Is this still correct?" and a checkbox called `confirm[key]` with the value `1`. Show the error for `confirm.` plus the key under it. The registration form uses the same component later, so it gets the confirmation for free.
                TEXT)
            ->think('The picker reloads the whole page. What does the parent lose if they already typed answers before they chose the child? How could you prevent that?')
            ->hint('`@selected($child?->is($option))` marks the chosen child in the select.')
            ->hint('Use `old(\'confirm.\'.$field[\'key\'])` for the checked state, and `@error(\'confirm.\'.$field[\'key\'])` for the message.')
            ->code(<<<'PHP'
                @if ($field['stale'] ?? false)
                    <div class="-mt-3 space-y-2 rounded-md border border-amber-500/40 bg-amber-500/5 p-3">
                        <div class="flex items-start gap-2">
                            <april:checkbox id="confirm-{{ $field['key'] }}" name="confirm[{{ $field['key'] }}]" value="1" :checked="(bool) old('confirm.'.$field['key'])" required />
                            <april:label for="confirm-{{ $field['key'] }}" class="font-normal">Is this still correct? Check the box to confirm it, or change the answer first.</april:label>
                        </div>
                        @error('confirm.'.$field['key'])
                            <p class="text-sm text-destructive">{{ $message }}</p>
                        @enderror
                    </div>
                @endif
                PHP)
            ->tryIt(<<<'TEXT'
                Log in as `staff@example.com` and open Forms. Create a form "Camp health" with the fields School, Allergies and Emergency contact phone. Publish it.

                Log in as `test@example.com` and add two children. Open `/forms/camp-health`, choose the first child, fill in the form and send it. Open the form again and choose the same child: the answers are there. Choose the other child: only the emergency phone is there.

                Now make the answers old. In tinker, run `App\Models\ProfileValue::query()->update(['confirmed_at' => now()->subMonths(13)])`. Open the form for the first child. Allergies asks "Is this still correct?". School does not, because it is not sensitive.
                TEXT)
            ->commit('Ask for the child and for confirmations')
            ->docs('https://laravel.com/docs/validation#validating-arrays', 'Laravel docs: Validating arrays')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/6', 'Laravel From Scratch, episode 6: Blade Directives')
            ->check(Check::all(
                Check::fileContains('resources/views/components/dynamic-form.blade.php', 'confirm['),
                Check::fileContains('resources/views/forms/show.blade.php', 'Which child'),
            )),

        Step::make('Add the routes and controller for saved answers')
            ->run('php artisan make:controller ProfileValueController')
            ->explain(<<<'TEXT'
                The parent needs a page to see and change the saved answers of a child. The answers belong to a child, so nest the routes under it: `Route::resource('children.answers', ProfileValueController::class)`. You need only `index`, `update` and `destroy`. Call the parameter `profileValue`, so it matches the model.

                Add `->scoped()` to the resource. Then Laravel finds the answer through the child: `$child->profileValues()`. An answer of another child is "not found", even when the parent changes the IDs in the URL.

                Scoping checks that the answer belongs to the child. It does not check that the child belongs to the parent. The `ChildPolicy` already answers that question. Call `Gate::authorize('update', $child)` in each method. The parent may change the child, so the parent may change its answers.

                In `update()`, validate the new answer with the rules of its field. `FormRules::for()` already builds them from a definition: `$profileValue->field->definition(required: true)`. Then save the value and set `confirmed_at` to now. To remove an answer, the parent uses Delete.
                TEXT)
            ->think('Without `->scoped()`, what could a parent do with the URL `/children/3/answers/99`, when child 3 is their own but answer 99 belongs to another family?')
            ->hint('`->parameters([\'answers\' => \'profileValue\'])` renames the parameter. Laravel then guesses the relationship `profileValues()` from it.')
            ->hint('Put the route in the group with the `auth` middleware, next to the `children` resource.')
            ->code(<<<'PHP'
                Route::resource('children.answers', ProfileValueController::class)
                    ->only(['index', 'update', 'destroy'])
                    ->parameters(['answers' => 'profileValue'])
                    ->scoped();

                // In ProfileValueController:
                public function update(Request $request, Child $child, ProfileValue $profileValue): RedirectResponse
                {
                    Gate::authorize('update', $child);

                    $field = $profileValue->field->definition(required: true);
                    $validated = $request->validate(FormRules::for([$field]), [], [$field['key'] => $field['label']]);

                    $profileValue->update([
                        'value' => $validated[$field['key']],
                        'confirmed_at' => now(),
                    ]);

                    return to_route('children.answers.index', $child)->with('status', 'Answer saved.');
                }
                PHP)
            ->tryIt('Run `php artisan route:list --name=answers`. You see three routes. The parameters look like `{child:}` and `{profileValue:}`. The colon shows a scoped binding: Laravel finds the second model through the first.')
            ->commit('Add routes and controller for saved answers')
            ->docs('https://laravel.com/docs/controllers#restful-nested-resources', 'Laravel docs: Nested resources')
            ->docs('https://laravel.com/docs/controllers#restful-scoping-resource-routes', 'Laravel docs: Scoping resource routes')
            ->docs('https://laravel.com/docs/routing#implicit-model-binding-scoping', 'Laravel docs: Implicit model binding scoping')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/18', 'Laravel From Scratch, episode 18: Authorization Using Policies')
            ->check(Check::route('children.answers.index', 'children.answers.update', 'children.answers.destroy')),

        Step::make('Build the Saved answers page')
            ->explain(<<<'TEXT'
                Create `resources/views/children/answers.blade.php` with `<x-layouts::app>`. Explain the page in one sentence: forms fill in these answers, and a form that was already sent keeps its own answers.

                For each saved answer, show a small form with `PUT` to `children.answers.update`. Do not write a new input for each type of field. The field components from the public form already do it. Use `<x-dynamic-component>` with `$value->field->type->component()`, and give it the definition with the saved value: `[...$value->field->definition(required: true), 'value' => $value->value]`. Show the date of the last confirmation, a Save button, and `<x-admin.delete-button>` for Delete.

                When there are no answers, say so, and say when answers appear.

                Last, add a "Saved answers" link to each row of `resources/views/children/index.blade.php`. The sidebar item "My children" already uses `routeIs('children.*')`, so it stays active on the new page.
                TEXT)
            ->think('The page shows the answer to a field that staff archived. Should the parent see it? Should the parent be able to delete it?')
            ->hint('Sort the answers by label in the controller: `$child->profileValues()->with(\'field\')->get()->sortBy(\'field.label\')`.')
            ->hint('Each form on the page uses the key of its field as the input name. The keys are unique for one child, so the IDs of the inputs are unique too.')
            ->tryIt('Log in as `test@example.com`, open My children, and click "Saved answers" next to the child from the last step. Change the school and save. Delete the allergies. Open the camp form for that child again: it shows the new school and an empty allergies box. Then open the old submission as staff: it still shows the old answers.')
            ->commit('Add the Saved answers page')
            ->docs('https://laravel.com/docs/blade#dynamic-components', 'Laravel docs: Dynamic components')
            ->check(Check::all(
                Check::view('children.answers'),
                Check::fileContains('resources/views/children/index.blade.php', "route('children.answers.index'"),
            )),

        Step::make('Run the checks and open your pull request')
            ->run('php artisan guide:verify profile-answers')
            ->explain(<<<'TEXT'
                Run the acceptance test with `guide:verify`. Then run `composer ci:check`. It runs Pint, PHPStan and every test.

                The old form tests still pass. A guest gets the same form as before, and a form without child or family fields saves nothing to a profile. That is how you know that the change is safe for the contact form.

                In your pull request, tell reviewers two things. The pull request has a migration. And it overrides one April UI view, with a link to the problem that you reported to April UI.
                TEXT)
            ->think('Which rule protects the privacy of children the most? Remove the `findOrFail()` scope from `chosenChild()` for a moment, and run the test. Which rule fails?')
            ->tryIt('Run `composer ci:check`. Pint, PHPStan and all the tests pass.')
            ->commit('Fix the checks for profile answers')
            ->docs('https://laravel.com/docs/http-tests#assert-forbidden', 'Laravel docs: assertForbidden')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/22', 'Laravel From Scratch, episode 22: How to Get Started Testing Your Code')
            ->check(Check::testPassed('ProfileAnswersTest')),
    ]);
