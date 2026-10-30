<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('applications')
    ->title('Applications')
    ->section(Section::Signups)
    ->level(2)
    ->needs(['signup-modes', 'submissions'])
    ->summary('Programs with the apply mode take applications, such as QUARKS. Staff accept or decline each one.')
    ->teaches(['Status enums', 'Reusing validation', 'Scoped route bindings', 'The auth middleware', 'Nested resources', 'Queued notifications', 'Blade components'])
    ->test('ApplicationsTest.php')
    ->intro(<<<'TEXT'
        Open a program with the `Apply` mode, such as QUARKS. The page says "Applications open soon", and nothing more. In this feature, you make that true: a student applies, staff decide, and the student learns the decision.

        Follow one application from start to end. A student opens the QUARKS page and clicks **Apply**. The app asks them to log in, because staff must know who applied and must be able to email them. Then the student answers the questions of the program's form. The app saves the answers as a `Submission`, exactly as a stand-alone form does. Then it saves an `Application` that links three things: the program, the user and the submission. The application starts with the status `Submitted`.

        Staff open the list of applications for QUARKS. They read the answers of one application and click **Accept** or **Decline**. The app saves the decision, the time and the staff member. It queues an email to the student. The student also sees the status on the dashboard.

        Notice what the application does not hold: the answers. The form holds the questions, and the submission holds the answers. You already built both. If QUARKS asks a new question next year, staff add it in the form editor. No code changes. So the main work here is to connect parts that exist, and to reuse them without a copy. The validation rules live in one place, `FormRules`. The application flow must use them, not write its own.

        The code never checks for "QUARKS". It checks the mode, `SignupMode::Apply`. Any program that staff set to `Apply`, with a form, takes applications.
        TEXT)
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/16', 'Laravel From Scratch, episode 16: Eloquent Relationships')
    ->docs('https://laravel.com/docs/notifications#queueing-notifications', 'Laravel docs: Queueing notifications')
    ->docs('https://laravel.com/docs/controllers#restful-nested-resources', 'Laravel docs: Nested resources')
    ->reflect([
        'QUARKS needs a reference letter next year. What changes? Which parts need a developer, and which parts can staff do alone?',
        'The application stores a `submission_id`, but no answers. Why is that better than columns such as `school` and `grade` on the `applications` table?',
        'The `submit()` method on `FormVersion` saves answers for stand-alone forms and for applications. Name one bug that a second copy of that code could cause.',
        'Which step was the hardest? What would help the next contributor? Open an issue or a pull request in blsc-guide to improve that step.',
    ])
    ->steps([
        Step::make('Claim the feature and copy in its test')
            ->run('php artisan guide:start applications')
            ->explain(<<<'TEXT'
                You start with the test. Run the command below. It copies `tests/Feature/ApplicationsTest.php` into the app.

                Read the whole test before you write code. It tells you the names that later features use: the `Application` model, the `ApplicationStatus` enum, the `ApplicationDecided` notification, and the routes `applications.create`, `applications.store`, `admin.programs.applications.index`, `admin.applications.show` and `admin.applications.update`.

                Look at the helper `programTakingApplications()` at the top. A program takes applications only when three things are true: it is published, its mode is `apply`, and its form has a published version. Then look at the rule "only apply programs take applications". Its dataset tries each case that must be refused.
                TEXT)
            ->think('The helper names the program "QUARKS", and the refused cases use the same helper. So what makes a program take applications, if not its name?')
            ->tryIt('Run `php artisan test tests/Feature/ApplicationsTest.php`. All the rules fail, because the `Application` model does not exist yet.')
            ->commit('Add the applications acceptance test')
            ->docs('https://laravel.com/docs/http-tests#session-and-authentication', 'Laravel docs: Session and authentication in tests')
            ->check(Check::fileExists('tests/Feature/ApplicationsTest.php')),

        Step::make('Create the ApplicationStatus enum')
            ->run('php artisan make:enum App/Enums/ApplicationStatus --string')
            ->explain(<<<'TEXT'
                An application is in one of three states: `Submitted`, `Accepted` or `Declined`. The code branches on the state: staff decide only to accept or decline, and the email text depends on the decision. So the state is an enum, like `SignupMode`.

                Give the cases the values `submitted`, `accepted` and `declined`. Add a `label()` method for the text that people see. Add a `badge()` method that returns the April UI badge variant for each state. The staff list and the dashboard both show a badge, so the choice of colour lives in one place.
                TEXT)
            ->think('Staff want a fourth state next year, "Waitlisted". Which files change? Which `match` does PHP warn you about?')
            ->hint('Copy the shape of `app/Enums/SignupMode.php`: the cases, then one method with a `match` for each text.')
            ->code(<<<'PHP'
                enum ApplicationStatus: string
                {
                    case Submitted = 'submitted';
                    case Accepted = 'accepted';
                    case Declined = 'declined';

                    public function label(): string
                    {
                        return match ($this) {
                            self::Submitted => 'Submitted',
                            self::Accepted => 'Accepted',
                            self::Declined => 'Declined',
                        };
                    }

                    public function badge(): string
                    {
                        return match ($this) {
                            self::Submitted => 'outline',
                            self::Accepted => 'default',
                            self::Declined => 'secondary',
                        };
                    }
                }
                PHP)
            ->tryIt('In `php artisan tinker`, run `App\Enums\ApplicationStatus::from(\'accepted\')->label()`. You see `"Accepted"`.')
            ->commit('Add the ApplicationStatus enum')
            ->docs('https://www.php.net/manual/en/language.enumerations.backed.php', 'PHP manual: Backed enumerations')
            ->check(Check::fileContains('app/Enums/ApplicationStatus.php', 'Declined')),

        Step::make('Create the Application model and table')
            ->run('php artisan make:model Application -mf')
            ->explain(<<<'TEXT'
                An application links a program, a user and a submission. It also records the decision. Add these columns to the migration:

                - `program_id`, `user_id` and `submission_id`: foreign keys.
                - `status`: a string with the default `submitted`. Add an index, because staff filter by status.
                - `decided_at`: a nullable timestamp.
                - `decided_by`: a nullable foreign key to `users`. The column name is not `user_id`, so tell `constrained()` the table: `constrained('users')`.

                Choose what happens on delete for each key. A program with applications must not disappear, so restrict it. The submission holds the answers, so restrict it too. When a person deletes their account, delete their applications with it (`cascadeOnDelete()`). When a staff member leaves, keep the decision but forget the person (`nullOnDelete()`).
                TEXT)
            ->think('Why does `decided_by` use `nullOnDelete()`, but `user_id` uses `cascadeOnDelete()`? Whose data is each one?')
            ->hint('Look at the `submissions` migration. It has a restricted key and a nullable key with `nullOnDelete()`.')
            ->code(<<<'PHP'
                $table->id();
                $table->foreignId('program_id')->constrained()->restrictOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('submission_id')->constrained()->restrictOnDelete();
                $table->string('status')->default('submitted')->index();
                $table->timestamp('decided_at')->nullable();
                $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                PHP)
            ->tryIt('Run `php artisan migrate`. Then, in `php artisan tinker`, run `Schema::getColumnListing(\'applications\')`. You see your columns.')
            ->commit('Add the Application model and table')
            ->docs('https://laravel.com/docs/migrations#foreign-key-constraints', 'Laravel docs: Foreign key constraints')
            ->check(Check::table('applications', ['program_id', 'user_id', 'submission_id', 'status', 'decided_at', 'decided_by'])),

        Step::make('Fill in the model, its relationships and the factory')
            ->explain(<<<'TEXT'
                Open `app/Models/Application.php`. Cast `status` to `ApplicationStatus` and `decided_at` to a datetime. Give `status` the default `submitted` in `$attributes`, as `Program` does for `signup_mode`.

                Add four `BelongsTo` relationships: `program()`, `user()`, `submission()` and `decider()`. The last one uses the `decided_by` column, so pass the column name as the second argument.

                Put only `user_id` and `submission_id` in `#[Fillable]`. The status changes in one way only: through a `decide()` method that sets the status, the time and the staff member together. `decide()` uses `forceFill()`, because those columns are not fillable. Then no form can send `status` and skip the record of who decided.

                Add `applications(): HasMany` to `Program` and to `User`. Update the docblocks, so PHPStan knows the types.

                In the factory, make a program with the `apply` mode, a user and a submission.
                TEXT)
            ->think('Why is `program_id` not in `#[Fillable]`? How does `$program->applications()->create([...])` still set it?')
            ->hint('`$this->belongsTo(User::class, \'decided_by\')` reads the `decided_by` column instead of `decider_id`.')
            ->hint('In the factory, `Program::factory()->state([\'signup_mode\' => \'apply\'])` makes a program with the right mode.')
            ->code(<<<'PHP'
                /**
                 * The staff member who made the decision.
                 *
                 * @return BelongsTo<User, $this>
                 */
                public function decider(): BelongsTo
                {
                    return $this->belongsTo(User::class, 'decided_by');
                }

                /**
                 * Accept or decline the application, and remember who decided and when.
                 */
                public function decide(ApplicationStatus $status, User $staff): void
                {
                    $this->forceFill([
                        'status' => $status,
                        'decided_at' => now(),
                        'decided_by' => $staff->id,
                    ])->save();
                }
                PHP)
            ->tryIt('In `php artisan tinker`, run `App\Models\Application::factory()->create()->program->signup_mode`. You see the `Apply` case.')
            ->commit('Connect applications to programs and users')
            ->docs('https://laravel.com/docs/eloquent-relationships#one-to-many-inverse', 'Laravel docs: One to many (inverse)')
            ->docs('https://laravel.com/docs/eloquent#mass-assignment', 'Laravel docs: Mass assignment')
            ->check(Check::all(
                Check::fileContains('app/Models/Application.php', 'function decide'),
                Check::fileContains('app/Models/Program.php', 'function applications'),
            )),

        Step::make('Move the answer code into FormVersion')
            ->explain(<<<'TEXT'
                Open `app/Http/Controllers/FormController.php`. Its `store()` method does three things: it validates the answers against the version, saves a submission, and emails staff. The application flow needs all three. Do not copy them. A copy drifts: one day someone fixes a bug in one copy and not in the other.

                Move the code into `app/Models/FormVersion.php`, because each part needs the fields of a version:

                - `rules()` returns `FormRules::for($this->fields)`.
                - `labels()` returns the label of each field, keyed by field key. Laravel uses them in the error messages, such as "The School field is required."
                - `submit(array $validated, ?User $user): Submission` saves the answers and sends the notice to `notify_email`.

                Why not validate inside `submit()`? A later feature validates more than the form. The Requests feature also asks a guest for a name and an email. With `rules()` as a separate method, a controller adds its own rules to the form rules, and the visitor sees all the errors at once.

                Then change `FormController::store()` to use the three methods. Run the tests in `SubmissionsTest.php` and `SubmissionExportsTest.php`. They must still pass, because the behaviour did not change.
                TEXT)
            ->think('Before, the notice to staff was in the controller. Now it is in `submit()`. Which new sign-ups get the notice with no extra code?')
            ->hint('Cut the body of `store()` from `$validated` to the notification. Paste it into `submit()` and change `$version` to `$this`.')
            ->hint('`array_column($this->fields, \'label\', \'key\')` gives `[\'school\' => \'School\', ...]`.')
            ->code(<<<'PHP'
                public function submit(array $validated, ?User $user): Submission
                {
                    $submission = $this->submissions()->create([
                        'user_id' => $user?->id,
                        'answers' => collect($this->fields)->mapWithKeys(fn (array $field) => [
                            $field['key'] => $validated[$field['key']] ?? null,
                        ])->all(),
                        'submitted_at' => now(),
                    ]);

                    if ($this->form->notify_email) {
                        Notification::route('mail', $this->form->notify_email)->notify(new SubmissionReceived($submission));
                    }

                    return $submission;
                }

                // FormController::store()
                $validated = $request->validate($version->rules(), [], $version->labels());

                $version->submit($validated, $request->user());
                PHP)
            ->tryIt('Run `php artisan test tests/Feature/SubmissionsTest.php tests/Feature/SubmissionExportsTest.php`. All the rules pass, as before your change.')
            ->commit('Move submission code into FormVersion')
            ->docs('https://laravel.com/docs/validation#customizing-the-validation-attributes', 'Laravel docs: Customizing the validation attributes')
            ->check(Check::fileContains('app/Models/FormVersion.php', 'function submit')),

        Step::make('Find the form that a program takes sign-ups with')
            ->explain(<<<'TEXT'
                The program page, the apply page and the save method all ask the same question: "Does this program take applications now?" The answer is yes when the program is published, its mode is `Apply`, and its form has a published version.

                Write the question once, on `Program`. Make it general, because the Requests feature asks the same question for the `Request` mode. `signupForm(SignupMode $mode): ?FormVersion` returns the published version that people answer, or `null`.

                A method that returns the version is more useful than one that returns `true` or `false`. The controller needs the version anyway, to show and to check its fields.
                TEXT)
            ->think('Staff set QUARKS to `Apply` but forget to choose a form. What does `signupForm()` return? What do you want the page to show?')
            ->hint('Check the mode first. Then a program with another mode never loads its form from the database.')
            ->hint('`$this->form?->currentVersion` is `null` when there is no form, and when the form has no published version.')
            ->code(<<<'PHP'
                /**
                 * The published form version that people answer to sign up in this mode.
                 * Null when the program does not take this kind of sign-up yet.
                 */
                public function signupForm(SignupMode $mode): ?FormVersion
                {
                    if (! $this->is_published || $this->signup_mode !== $mode) {
                        return null;
                    }

                    return $this->form?->currentVersion;
                }
                PHP)
            ->tryIt('Run `php artisan migrate:fresh --seed`. In tinker, run `App\Models\Program::firstWhere(\'slug\', \'quarks\')->signupForm(App\Enums\SignupMode::Apply)`. You see `null`: QUARKS has no form yet. You give it one in a later step.')
            ->commit('Find the sign-up form of a program')
            ->check(Check::fileContains('app/Models/Program.php', 'function signupForm')),

        Step::make('Let a logged-in person apply')
            ->run('php artisan make:controller ApplicationController')
            ->explain(<<<'TEXT'
                Add two routes for the apply page: `GET` and `POST` on `/programs/{category:slug}/{program:slug}/apply`. Name them `applications.create` and `applications.store`. Put them in the group with the `auth` middleware in `routes/web.php`. A guest who opens the page goes to the login page, and comes back after login.

                Wrap the two routes in `Route::scopeBindings()->group(...)`, as the program page uses `scopeBindings()`. Then Laravel finds the program only inside its category. `/programs/camps/quarks/apply` is 404.

                In the controller, both methods start with the same line: `$program->signupForm(SignupMode::Apply) ?? abort(404)`. A program with another mode has no apply page.

                `create()` shows `resources/views/applications/create.blade.php`. It uses `<x-dynamic-form>` with the fields of the version. Set the `submit` attribute to "Send my application".

                `store()` validates with `$version->rules()`, calls `$version->submit()`, and creates the application through `$program->applications()`. Then it sends the person to the dashboard with a status message.
                TEXT)
            ->think('The program page already checks the mode before it shows the button. Why must `store()` check it again?')
            ->hint('Look at the `programs.show` route. Its parameters are `{category:slug}` and `{program:slug}`. Your controller methods take `Category $category, Program $program` in the same order.')
            ->hint('`$version->submit($validated, $request->user())->id` saves the submission and gives you its ID for the application.')
            ->code(<<<'PHP'
                public function store(Request $request, Category $category, Program $program): RedirectResponse
                {
                    $version = $program->signupForm(SignupMode::Apply) ?? abort(404);

                    $validated = $request->validate($version->rules(), [], $version->labels());

                    $program->applications()->create([
                        'user_id' => $request->user()->id,
                        'submission_id' => $version->submit($validated, $request->user())->id,
                    ]);

                    return to_route('dashboard')->with('status', "Your application to {$program->name} was sent. We will email you when staff decide.");
                }
                PHP)
            ->tryIt('Run `php artisan route:list --name=applications`. You see `applications.create` and `applications.store` with the `auth` middleware.')
            ->commit('Let people apply to a program')
            ->docs('https://laravel.com/docs/authentication#protecting-routes', 'Laravel docs: Protecting routes')
            ->docs('https://laravel.com/docs/routing#implicit-model-binding-scoping', 'Laravel docs: Scoped bindings')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/10', 'Laravel From Scratch, episode 10: Controllers')
            ->check(Check::route('applications.create', 'applications.store')),

        Step::make('Turn the box into an Apply button')
            ->explain(<<<'TEXT'
                Open `resources/views/programs/show.blade.php`. The box shows `callToAction()`, such as "Applications open soon". Keep that text for a program that is not ready. When `signupForm(SignupMode::Apply)` returns a version, show a different box: the title "Applications are open", one sentence about what happens next, and an **Apply** button to `applications.create`.

                The view asks about the mode, not the category. The Requests feature adds one more case with `@elseif`, and the Registrations feature adds another.

                Tell the visitor about the login before they click. A surprise login page makes people leave.
                TEXT)
            ->think('A guest clicks Apply. Where do they go? Where do they land after they log in?')
            ->hint('An `<april:alert>` has a `<slot:description>`. Put the sentence and an `<april:button-link>` in it.')
            ->code(<<<'PHP'
                @if ($program->signupForm(App\Enums\SignupMode::Apply))
                    <april:alert>
                        <slot:icon><x-lucide-send class="size-4" /></slot:icon>
                        <slot:title>Applications are open</slot:title>
                        <slot:description class="space-y-3">
                            <p>Log in and answer a few questions. Staff read each application and email you their decision.</p>
                            <april:button-link href="{{ route('applications.create', [$program->category, $program]) }}">Apply</april:button-link>
                        </slot:description>
                    </april:alert>
                @else
                    <april:alert>
                        <slot:icon><x-lucide-info class="size-4" /></slot:icon>
                        <slot:title>{{ $program->signup_mode->callToAction() }}</slot:title>
                    </april:alert>
                @endif
                PHP)
            ->tryIt('Run `php artisan test tests/Feature/SignupModesTest.php`. The old rules still pass: a program without a form still says "Applications open soon".')
            ->commit('Show an Apply button on the program page')
            ->docs('https://laravel.com/docs/blade#if-statements', 'Laravel docs: If statements')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/6', 'Laravel From Scratch, episode 6: Blade Directives')
            ->check(Check::fileContains('resources/views/programs/show.blade.php', 'applications.create')),

        Step::make('Seed a form for QUARKS')
            ->run('php artisan make:seeder SignupFormSeeder')
            ->explain(<<<'TEXT'
                On your machine, QUARKS has the `apply` mode but no form. So the Apply button never shows. Staff fix that on the live site with the form editor. On a fresh development database, a seeder does it, so every contributor sees the feature at once.

                In `SignupFormSeeder`, add a field `why_quarks` ("Why do you want to join QUARKS?") to the library. Create a form "QUARKS application" with the fields `school`, `grade` and `why_quarks`. Publish it if it has no version yet. Then set `form_id` on the program with the slug `quarks`.

                Write the steps in a private method, such as `programForm(string $programSlug, array $details, array $keys)`. The Requests feature seeds a second form with the same method.

                Call the new seeder at the end of `DatabaseSeeder`, after `FormSeeder`, because it uses the fields from the library.
                TEXT)
            ->think('The seeder finds QUARKS by its slug. Is that the same as code that branches on a category name? Why or why not?')
            ->hint('Copy the end of `FormSeeder`: `updateOrCreate()`, `fields()->sync()`, and `publish()` when `currentVersion` is `null`.')
            ->code(<<<'PHP'
                private function programForm(string $programSlug, array $details, array $keys): void
                {
                    $form = Form::updateOrCreate(['slug' => str($details['name'])->slug()->toString()], $details);

                    $form->fields()->sync(
                        collect($keys)
                            ->mapWithKeys(fn (string $key, int $position) => [
                                Field::where('key', $key)->value('id') => ['position' => $position, 'required' => true],
                            ])
                            ->all()
                    );

                    if ($form->currentVersion === null) {
                        $form->publish();
                    }

                    Program::where('slug', $programSlug)->update(['form_id' => $form->id]);
                }
                PHP)
            ->tryIt(<<<'TEXT'
                Run `php artisan migrate:fresh --seed` and `composer dev`. Open http://localhost:8000/programs/quarks/quarks. The box says "Applications are open". Click Apply. The login page opens.

                Log in as `test@example.com` with the password `password`. You land on the apply page with three questions. Send it with an empty answer: the page shows an error with the label of the field. Fill in all three and send. The dashboard shows "Your application to QUARKS was sent".
                TEXT)
            ->commit('Seed the QUARKS application form')
            ->docs('https://laravel.com/docs/seeding#writing-seeders', 'Laravel docs: Writing seeders')
            ->check(Check::fileContains('database/seeders/DatabaseSeeder.php', 'SignupFormSeeder')),

        Step::make('Show the applications to staff')
            ->run('php artisan make:controller Admin/ApplicationController')
            ->explain(<<<'TEXT'
                Staff look at applications one program at a time. Use a nested resource, as for the submissions of a form: `Route::resource('programs.applications', Admin\ApplicationController::class)->shallow()->only(['index', 'show', 'update'])`. The list is under the program, and one application has a short URL.

                `index()` lists the applications of the program, newest first, with the user, the email and a status badge. Paginate it.

                `show()` shows who applied, when, the status, and the answers. The answers look exactly like the staff page of a submission. So move that `<dl>` from `resources/views/admin/submissions/show.blade.php` into a component, `resources/views/components/admin/answers.blade.php`, with one prop: `submission`. Then both pages use `<x-admin.answers :submission="..." />`. The Requests feature uses it too.

                Last, help staff find the list. In the program list of the admin, add a "Sign-ups" column. For an `Apply` program, link to its applications with a count.
                TEXT)
            ->think('The answers component reads `$submission->version->fields`. Why must it not read the fields of the program\'s form?')
            ->hint('An anonymous component starts with `@props([\'submission\'])`. Copy the `<dl>` below it.')
            ->hint('In `Admin\ProgramController::index()`, change `withCount(\'sessions\')` to `withCount([\'sessions\', \'applications\'])`. The view reads `$program->applications_count`.')
            ->code(<<<'PHP'
                Route::resource('programs.applications', Admin\ApplicationController::class)->shallow()->only(['index', 'show', 'update']);

                public function index(Program $program): View
                {
                    return view('admin.applications.index', [
                        'program' => $program,
                        'applications' => $program->applications()->with('user')->latest()->paginate(50),
                    ]);
                }

                public function show(Application $application): View
                {
                    return view('admin.applications.show', [
                        'application' => $application->load(['program', 'user', 'decider', 'submission.version']),
                    ]);
                }
                PHP)
            ->tryIt('Log in as `staff@example.com`. Open Programs. The QUARKS row shows "Applications (1)". Click it, then open the application. You see the three questions and your answers.')
            ->commit('Show applications to staff')
            ->docs('https://laravel.com/docs/controllers#shallow-nesting', 'Laravel docs: Shallow nesting')
            ->docs('https://laravel.com/docs/blade#anonymous-components', 'Laravel docs: Anonymous components')
            ->check(Check::all(
                Check::route('admin.programs.applications.index', 'admin.applications.show'),
                Check::view('components.admin.answers'),
            )),

        Step::make('Accept or decline, and email the applicant')
            ->run('php artisan make:notification ApplicationDecided')
            ->explain(<<<'TEXT'
                On the show page, add one form with two submit buttons: **Accept** with `name="status" value="accepted"`, and **Decline** with `value="declined"`. The browser sends the value of the button that staff click. Use `@method('PATCH')`, because the form changes the application.

                In `update()`, validate `status`. `Rule::enum(ApplicationStatus::class)` accepts every case, but staff must not set an application back to `submitted`. Add `->only([...])` with the two decisions. Then call `decide()` and notify the applicant.

                `ApplicationDecided` implements `ShouldQueue`, as `SubmissionReceived` does. The decision saves at once. The email goes out a moment later, from the queue worker. Its text uses `status->label()`, so one notification serves both decisions. Send it with `$application->user->notify(...)`: the `User` model has the `Notifiable` trait, so the email goes to the user's address.
                TEXT)
            ->think('The email is queued. Staff click Accept, and the mail server is slow. What does the staff member see? What would they see without the queue?')
            ->hint('`Rule::enum(ApplicationStatus::class)->only([ApplicationStatus::Accepted, ApplicationStatus::Declined])` refuses `submitted` and every unknown value.')
            ->hint('In `toMail()`, `strtolower($this->application->status->label())` gives "accepted" for the sentence "Your application to QUARKS was accepted."')
            ->code(<<<'PHP'
                public function update(Request $request, Application $application): RedirectResponse
                {
                    $validated = $request->validate([
                        'status' => ['required', Rule::enum(ApplicationStatus::class)->only([ApplicationStatus::Accepted, ApplicationStatus::Declined])],
                    ]);

                    $application->decide(ApplicationStatus::from($validated['status']), $request->user());

                    $application->user->notify(new ApplicationDecided($application));

                    return to_route('admin.applications.show', $application)->with('status', 'Decision saved. The applicant gets an email.');
                }
                PHP)
            ->tryIt(<<<'TEXT'
                As staff, open your application and click Accept. The badge says "Accepted", and the page shows who decided.

                `composer dev` runs a queue worker, so the email goes out a moment later. Its output shows `ApplicationDecided ... DONE`. Without `composer dev`, run `php artisan queue:work --stop-when-empty`. The app writes email to the log on your machine. Open `storage/logs/laravel.log` and search for "Your application to QUARKS was accepted".
                TEXT)
            ->commit('Accept or decline applications')
            ->docs('https://laravel.com/docs/validation#rule-enum', 'Laravel docs: The enum rule')
            ->docs('https://laravel.com/docs/notifications#queueing-notifications', 'Laravel docs: Queueing notifications')
            ->docs('https://laravel.com/docs/notifications#formatting-mail-messages', 'Laravel docs: Formatting mail messages')
            ->check(Check::all(
                Check::route('admin.applications.update'),
                Check::classExists('App\Notifications\ApplicationDecided'),
            )),

        Step::make('Show the status on the dashboard')
            ->run('php artisan make:controller DashboardController --invokable')
            ->explain(<<<'TEXT'
                The dashboard is a `Route::view()` today, so it gets no data. Change the route to an invokable controller: `Route::get('dashboard', DashboardController::class)->name('dashboard')`. A controller with one job needs only `__invoke()`.

                Load the applications of the person who is logged in: `$request->user()->applications()`. Never load them by an ID from the URL. Then nobody can see another person's applications, and you need no policy.

                In `dashboard.blade.php`, add a card "My applications" above the placeholders. Show it only when there are applications. Each row shows the program, the date, and the status badge.
                TEXT)
            ->think('The dashboard loads `with(\'program.category\')`. Which link needs the category? How many queries does the page make without it?')
            ->hint('`@if ($applications->isNotEmpty())` hides the card for a person with no applications.')
            ->code(<<<'PHP'
                public function __invoke(Request $request): View
                {
                    return view('dashboard', [
                        'applications' => $request->user()->applications()->with('program.category')->latest()->get(),
                    ]);
                }
                PHP)
            ->tryIt('Log in as `test@example.com` and open the dashboard. The card "My applications" shows QUARKS with the badge "Accepted".')
            ->commit('Show applications on the dashboard')
            ->docs('https://laravel.com/docs/controllers#single-action-controllers', 'Laravel docs: Invokable controllers')
            ->docs('https://laravel.com/docs/eloquent-relationships#nested-eager-loading', 'Laravel docs: Nested eager loading')
            ->check(Check::fileContains('routes/web.php', 'DashboardController')),

        Step::make('Run the checks and open your pull request')
            ->run('php artisan guide:verify applications')
            ->explain(<<<'TEXT'
                Run the acceptance test with `guide:verify`. Then run `composer ci:check`. It runs Pint, PHPStan and every test, as GitHub does on your pull request.

                The old tests matter here. You moved code out of `FormController` and changed the dashboard route. `SubmissionsTest`, `SubmissionExportsTest` and `DashboardTest` prove that nothing broke.

                In your pull request, tell reviewers to run `php artisan migrate`, and `php artisan db:seed --class=SignupFormSeeder` to get the QUARKS form.
                TEXT)
            ->think('PHPStan complains that `$application->status->label()` is called on mixed. Which line in the docblock of `Application` is missing?')
            ->tryIt('Run `composer ci:check`. Pint, PHPStan and all the tests pass.')
            ->commit('Fix the checks for applications')
            ->check(Check::testPassed('ApplicationsTest')),
    ]);
