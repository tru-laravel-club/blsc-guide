<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('requests')
    ->title('Requests')
    ->section(Section::Signups)
    ->level(2)
    ->needs(['signup-modes', 'submissions'])
    ->summary('Programs with the request mode take requests, such as parties and school bookings. Staff reply to each one.')
    ->teaches(['Comparing two designs', 'Guest forms', 'Blade slots', 'Conditional validation rules', 'Counting with a condition', 'mailto links'])
    ->test('RequestsTest.php')
    ->intro(<<<'TEXT'
        Some programs start with a conversation. A family wants a birthday party at the centre. A teacher wants to book a class visit. They do not register or pay yet. They send a request, and staff reply: "Saturday the 5th is free. Here is the price."

        A program with the `Request` mode says "Requests open soon" today. In this feature, it shows a **Send a request** button. The visitor answers the program's form and sends it. Staff see the requests of the program, reply by email, and mark each one as replied or closed.

        This looks like the Applications feature. Both take the answers of a program's form, save a `Submission`, and link it to a new record. So why not reuse the `Application` model with one more column? Look at what is different:

        - **Who sends it.** An application needs an account, because staff decide about a person. A request does not. A parent who plans one party must not create an account first. So a request stores a `name` and an `email`, and `user_id` is nullable.
        - **What happens next.** An application waits for one decision: accepted or declined. A request is a conversation: open, replied, closed, and maybe open again. The states are different, and so is what staff do with them.

        One model for both would need a nullable user and a `name` that applications never use, two sets of states in one column, and an `if` on the type in every controller and view. Two small models are easier to read and to change. They share the parts that are the same: the form, the version, the submission and `FormVersion::submit()`.

        The model is called `Inquiry`, not `Request`. Laravel already has `Illuminate\Http\Request` in every controller. Two classes with the same name in one file mean an alias and confusion.
        TEXT)
    ->docs('https://laravel.com/docs/blade#slots', 'Laravel docs: Slots')
    ->docs('https://laravel.com/docs/controllers#restful-nested-resources', 'Laravel docs: Nested resources')
    ->reflect([
        'Applications and requests look similar. Why are they two models? What would one model cost? Name one thing that would be harder to change.',
        'Staff reply with their own email program, and the app only records the status. What do staff gain from that? What do they lose?',
        'A guest can send a request without an account. What stops a robot from sending hundreds of fake requests? Look at the `throttle` middleware.',
        'Which step was the hardest? What would help the next contributor? Open an issue or a pull request in blsc-guide to improve that step.',
    ])
    ->steps([
        Step::make('Claim the feature and copy in its test')
            ->run('php artisan guide:start requests')
            ->explain(<<<'TEXT'
                You start with the test. Run the command below. It copies `tests/Feature/RequestsTest.php` into the app.

                Read the test next to `ApplicationsTest.php`. Many rules match: the button, the refused modes, the staff list. Now find the differences. A guest sends a request with no login. A guest gives a name and an email. A logged-in visitor does not type them again. Staff change the status in any direction, and no email goes to the visitor.

                Look at the rule "staff get the notice of the request form". You write no code for it. The notice is in `FormVersion::submit()` since the Applications feature, so every sign-up that uses `submit()` gets it.
                TEXT)
            ->think('Make a list of what applications and requests share, and what they do not. Which list is longer?')
            ->tryIt('Run `php artisan test tests/Feature/RequestsTest.php`. All the rules fail, because the `Inquiry` model does not exist yet.')
            ->commit('Add the requests acceptance test')
            ->docs('https://laravel.com/docs/http-tests', 'Laravel docs: HTTP tests')
            ->check(Check::fileExists('tests/Feature/RequestsTest.php')),

        Step::make('Create the InquiryStatus enum')
            ->run('php artisan make:enum App/Enums/InquiryStatus --string')
            ->explain(<<<'TEXT'
                A request has three states: `Open`, `Replied` and `Closed`. Compare them with `ApplicationStatus`. An application moves one way, from `Submitted` to a decision. A request can move in any direction. A family writes back after staff reply, and staff open the request again.

                That difference is one reason for a separate enum. With one shared enum, every page would need to know which states belong to which kind of record.

                Add `label()` and `badge()`, as in `ApplicationStatus`. Make `Open` the strongest badge, because open requests wait for staff.
                TEXT)
            ->think('Could `Replied` and `Accepted` be one state? What would the email to the visitor say?')
            ->hint('Copy `app/Enums/ApplicationStatus.php` and change the cases.')
            ->code(<<<'PHP'
                enum InquiryStatus: string
                {
                    case Open = 'open';
                    case Replied = 'replied';
                    case Closed = 'closed';

                    public function label(): string
                    {
                        return match ($this) {
                            self::Open => 'Open',
                            self::Replied => 'Replied',
                            self::Closed => 'Closed',
                        };
                    }

                    public function badge(): string
                    {
                        return match ($this) {
                            self::Open => 'default',
                            self::Replied => 'secondary',
                            self::Closed => 'outline',
                        };
                    }
                }
                PHP)
            ->tryIt('In `php artisan tinker`, run `App\Enums\InquiryStatus::cases()`. You see the three cases.')
            ->commit('Add the InquiryStatus enum')
            ->docs('https://www.php.net/manual/en/language.enumerations.backed.php', 'PHP manual: Backed enumerations')
            ->check(Check::fileContains('app/Enums/InquiryStatus.php', 'Replied')),

        Step::make('Create the Inquiry model and table')
            ->run('php artisan make:model Inquiry -mf')
            ->explain(<<<'TEXT'
                Add the columns: `program_id`, a nullable `user_id`, `name`, `email`, `submission_id`, and `status` with the default `open`.

                Why are `name` and `email` columns, when the form could ask for them? The code reads them: staff reply to the email, and the list shows the name. A rule of the design says that a value is a column only if the code reads it. The questions about the party go into the form, because the code only stores and shows them.

                A logged-in visitor also gets `name` and `email`, copied from the account. Then the staff pages read the same two columns for every request.

                For the keys, copy the choices from the `applications` table, with one change. A guest has no user, so `user_id` is nullable. When a person deletes their account, keep the request and set `user_id` to null. The request still has the name and the email.
                TEXT)
            ->think('Why does `user_id` use `nullOnDelete()` here, but `cascadeOnDelete()` on `applications`?')
            ->hint('Look at your `applications` migration. Change `user_id` to `->nullable()->constrained()->nullOnDelete()`.')
            ->code(<<<'PHP'
                $table->id();
                $table->foreignId('program_id')->constrained()->restrictOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('name');
                $table->string('email');
                $table->foreignId('submission_id')->constrained()->restrictOnDelete();
                $table->string('status')->default('open')->index();
                $table->timestamps();
                PHP)
            ->tryIt('Run `php artisan migrate`. Then, in `php artisan tinker`, run `Schema::getColumnListing(\'inquiries\')`.')
            ->commit('Add the Inquiry model and table')
            ->docs('https://laravel.com/docs/migrations#foreign-key-constraints', 'Laravel docs: Foreign key constraints')
            ->check(Check::table('inquiries', ['program_id', 'user_id', 'name', 'email', 'submission_id', 'status'])),

        Step::make('Fill in the model and the factory')
            ->explain(<<<'TEXT'
                Open `app/Models/Inquiry.php`. Cast `status` to `InquiryStatus`, and give it the default `open` in `$attributes`. Add `program()`, `user()` and `submission()`. Add `inquiries(): HasMany` to `Program`.

                This time, `status` can be in `#[Fillable]`. A request has no decision to record, so staff change the status with a plain `update()`. Compare that with `Application::decide()`, which also saves who decided and when.

                In the factory, make a program with the `request` mode, a fake name and a safe email, and a submission. Leave `user_id` as `null`: the usual request comes from a guest.
                TEXT)
            ->think('The `user()` relationship can return `null`. Where in the views must you remember that?')
            ->hint('`fake()->safeEmail()` gives an address at `example.com`, `example.org` or `example.net`. Mail to it never reaches a real person.')
            ->code(<<<'PHP'
                #[Fillable(['user_id', 'name', 'email', 'submission_id', 'status'])]
                class Inquiry extends Model
                {
                    protected $attributes = [
                        'status' => 'open',
                    ];

                    protected function casts(): array
                    {
                        return [
                            'status' => InquiryStatus::class,
                        ];
                    }

                    /**
                     * The account that sent the request. Null for a guest.
                     *
                     * @return BelongsTo<User, $this>
                     */
                    public function user(): BelongsTo
                    {
                        return $this->belongsTo(User::class);
                    }
                }
                PHP)
            ->tryIt('In `php artisan tinker`, run `App\Models\Inquiry::factory()->create()->only([\'name\', \'email\', \'status\'])`.')
            ->commit('Connect requests to programs')
            ->docs('https://laravel.com/docs/eloquent-factories#defining-model-factories', 'Laravel docs: Defining model factories')
            ->check(Check::all(
                Check::fileContains('app/Models/Inquiry.php', 'function submission'),
                Check::fileContains('app/Models/Program.php', 'function inquiries'),
            )),

        Step::make('Let the dynamic form take extra inputs')
            ->explain(<<<'TEXT'
                A guest types a name and an email. Those inputs are not fields of the form, but they must be inside the same `<form>` tag, so the browser sends them together.

                Open `resources/views/components/dynamic-form.blade.php`. Add `{{ $slot }}` after `@csrf`, before the fields. A slot is the content between the opening and closing tags of a component. Every page that uses `<x-dynamic-form ... />` with no content gets an empty slot, so nothing changes for them.

                A slot keeps the component general. The component does not know about guests or requests. The page that uses it decides what goes in.
                TEXT)
            ->think('The other choice is a new prop, such as `:ask-contact="true"`. Why is the slot better?')
            ->code(<<<'PHP'
                <form method="POST" action="{{ $action }}" {{ $attributes->twMerge(['space-y-6']) }}>
                    @csrf

                    {{ $slot }}

                    @foreach ($fields as $field)
                        <x-dynamic-component :component="App\Enums\FieldType::from($field['type'])->component()" :field="$field" />
                    @endforeach

                    <april:button type="submit">{{ $submit }}</april:button>
                </form>
                PHP)
            ->tryIt('Run `php artisan test tests/Feature/FormDisplayTest.php tests/Feature/ApplicationsTest.php`. They pass: an empty slot changes nothing.')
            ->commit('Add a slot to the dynamic form')
            ->docs('https://laravel.com/docs/blade#slots', 'Laravel docs: Slots')
            ->check(Check::fileContains('resources/views/components/dynamic-form.blade.php', '$slot')),

        Step::make('Let anyone send a request')
            ->run('php artisan make:controller InquiryController')
            ->explain(<<<'TEXT'
                Add `GET` and `POST` routes on `/programs/{category:slug}/{program:slug}/request`, named `inquiries.create` and `inquiries.store`. Put them in a `Route::scopeBindings()` group, but outside the `auth` group. A guest must reach them.

                Both methods start with `$program->signupForm(SignupMode::Request) ?? abort(404)`. You wrote `signupForm()` for applications. Here it works for a new mode with no change.

                In `create()`, show `resources/views/inquiries/create.blade.php`. Inside `<x-dynamic-form>`, use `@auth` and `@else`. A guest sees two `<x-form-field>` inputs: `name` ("Your name") and `email` ("Your email address"). A logged-in visitor sees "We will reply to" and the email of the account.

                In `store()`, build one list of rules: the form rules from `$version->rules()`, plus `name` and `email` for a guest. Validate them in one call, so the visitor sees every error at once. Then create the inquiry. For a logged-in visitor, take the name and email from the account. Last, send the visitor back to the program page with a status message.
                TEXT)
            ->think('The form fields and the contact inputs share one request. What happens if staff add a field with the key `email` to the party form? How do the keys in the seeded library avoid that?')
            ->hint('`$user ? [] : [\'name\' => [...], \'email\' => [...]]` gives the extra rules only to a guest. Spread both lists into one array: `[...$version->rules(), ...$contact]`.')
            ->hint('`$user->name ?? $validated[\'name\']` takes the name from the account, or from the form for a guest. `??` does not fail when `$user` is `null`.')
            ->code(<<<'PHP'
                public function store(Request $request, Category $category, Program $program): RedirectResponse
                {
                    $version = $program->signupForm(SignupMode::Request) ?? abort(404);
                    $user = $request->user();

                    // A guest tells us who they are. A logged-in person already did.
                    $contact = $user ? [] : [
                        'name' => ['required', 'string', 'max:255'],
                        'email' => ['required', 'email', 'max:255'],
                    ];

                    $validated = $request->validate([...$version->rules(), ...$contact], [], $version->labels());

                    $program->inquiries()->create([
                        'user_id' => $user?->id,
                        'name' => $user->name ?? $validated['name'],
                        'email' => $user->email ?? $validated['email'],
                        'submission_id' => $version->submit($validated, $user)->id,
                    ]);

                    return to_route('programs.show', [$category, $program])
                        ->with('status', 'Thank you. We received your request and will reply by email.');
                }
                PHP)
            ->tryIt('Run `php artisan route:list --name=inquiries`. You see `inquiries.create` and `inquiries.store` without the `auth` middleware.')
            ->commit('Let visitors send requests')
            ->docs('https://laravel.com/docs/blade#authentication-directives', 'Laravel docs: Authentication directives')
            ->docs('https://laravel.com/docs/routing#rate-limiting', 'Laravel docs: Rate limiting')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/11', 'Laravel From Scratch, episode 11: Request Validation')
            ->check(Check::route('inquiries.create', 'inquiries.store')),

        Step::make('Add the request button to the program page')
            ->explain(<<<'TEXT'
                Open `resources/views/programs/show.blade.php`. After the Apply case, add `@elseif ($program->signupForm(App\Enums\SignupMode::Request))`. Show a box with the title "Send a request", one sentence, and a **Send a request** button to `inquiries.create`. Tell the visitor that no account is necessary.

                The other modes still fall through to `callToAction()`. A request program without a form still says "Requests open soon".

                `store()` sends the visitor back to this page with a status message. The public layout does not show status messages, so add an `<april:alert>` at the top of the page when `session('status')` has a value.
                TEXT)
            ->think('The view now has `@if`, `@elseif` and `@else`. The Registrations feature adds one more case. When would you move this choice out of the view?')
            ->hint('Copy the Apply box. Change the mode, the texts and the route.')
            ->code(<<<'PHP'
                @elseif ($program->signupForm(App\Enums\SignupMode::Request))
                    <april:alert>
                        <slot:icon><x-lucide-mail class="size-4" /></slot:icon>
                        <slot:title>Send a request</slot:title>
                        <slot:description class="space-y-3">
                            <p>Tell us what you need. You do not need an account. Staff reply by email.</p>
                            <april:button-link href="{{ route('inquiries.create', [$program->category, $program]) }}">Send a request</april:button-link>
                        </slot:description>
                    </april:alert>
                @else
                PHP)
            ->tryIt('Run `php artisan test tests/Feature/SignupModesTest.php`. The old rules still pass.')
            ->commit('Show a request button on the program page')
            ->docs('https://laravel.com/docs/session#retrieving-data', 'Laravel docs: Retrieving session data')
            ->check(Check::fileContains('resources/views/programs/show.blade.php', 'inquiries.create')),

        Step::make('Seed a party program and its form')
            ->explain(<<<'TEXT'
                No seeded program uses the `request` mode yet. In `ProgramSeeder`, add a "Parties" category and a "Birthday Parties" program with `'signup_mode' => 'request'`. A party has no fixed dates, so give it no sessions.

                In `SignupFormSeeder`, add two fields to the library: `party_date` (a date, "Preferred party date") and `guest_count` (a number, "Number of guests"). Then call `programForm()` again for `birthday-parties`, with a "Party request" form. You wrote that method for QUARKS. Now it pays off.

                Give the form a `notify_email`, so staff get a notice for each new request.
                TEXT)
            ->think('Why does the party form not ask for the name and the email of the visitor?')
            ->code(<<<'PHP'
                $this->programForm('birthday-parties', [
                    'name' => 'Party request',
                    'description' => 'Tell us about the party. We reply within two business days.',
                    'notify_email' => 'parties@example.com',
                ], ['party_date', 'guest_count']);
                PHP)
            ->tryIt(<<<'TEXT'
                Run `php artisan migrate:fresh --seed` and `composer dev`. Log out, and open http://localhost:8000/programs/parties/birthday-parties. Click "Send a request".

                Send the form empty. You see errors for your name, your email and the party date, all at once. Fill it in and send. The program page says "Thank you. We received your request".
                TEXT)
            ->commit('Seed a party program and request form')
            ->docs('https://laravel.com/docs/seeding#writing-seeders', 'Laravel docs: Writing seeders')
            ->check(Check::fileContains('database/seeders/ProgramSeeder.php', "'signup_mode' => 'request'")),

        Step::make('Show the requests to staff')
            ->run('php artisan make:controller Admin/InquiryController')
            ->explain(<<<'TEXT'
                Copy the shape of the staff pages for applications. Add `Route::resource('programs.inquiries', Admin\InquiryController::class)->shallow()->only(['index', 'show', 'update'])` to the staff group.

                `index()` lists the requests of one program, newest first, with the name, the email and a status badge. `show()` shows who sent it, if they have an account, and the answers. Use `<x-admin.answers>`, the component from the Applications feature.

                Staff reply with a **Reply by email** button: a `mailto:` link with the visitor's email and a subject. The staff member's email program opens, and the conversation stays in the centre's mailbox, with the history of every message. The app does not need to be an email program.

                In the program list of the admin, add the requests to the "Sign-ups" column. Staff care about the requests that wait for them, so count only the open ones. `withCount()` takes a condition: `'inquiries as open_inquiries_count' => fn ($query) => $query->where('status', InquiryStatus::Open)`.
                TEXT)
            ->think('The subject in the `mailto:` link holds the program name. Why must you encode it with `rawurlencode()`?')
            ->hint('`as open_inquiries_count` names the count. The view reads `$program->open_inquiries_count`.')
            ->hint('`$inquiry->user ? \'with an account\' : \'as a guest\'` tells staff where the request came from.')
            ->code(<<<'PHP'
                <april:button-link href="mailto:{{ $inquiry->email }}?subject={{ rawurlencode('Your request: '.$inquiry->program->name) }}">Reply by email</april:button-link>

                // Admin\ProgramController::index()
                Program::with('category')->withCount([
                    'sessions',
                    'applications',
                    'inquiries as open_inquiries_count' => fn ($query) => $query->where('status', InquiryStatus::Open),
                ])
                PHP)
            ->tryIt('Log in as `staff@example.com` and open Programs. The Birthday Parties row shows "Requests (1 open)". Open the request. You see the answers. Click "Reply by email": your email program opens with the address and the subject.')
            ->commit('Show requests to staff')
            ->docs('https://laravel.com/docs/eloquent-relationships#counting-related-models', 'Laravel docs: Counting related models')
            ->docs('https://laravel.com/docs/controllers#shallow-nesting', 'Laravel docs: Shallow nesting')
            ->check(Check::route('admin.programs.inquiries.index', 'admin.inquiries.show')),

        Step::make('Mark a request as replied or closed')
            ->explain(<<<'TEXT'
                After staff reply, they mark the request as replied. When the conversation ends, they close it. If the family writes back, staff open it again.

                On the show page, add one form with `@method('PATCH')`. Loop over `InquiryStatus::cases()`, and show a submit button for each status except the current one, such as "Mark as replied". Each button has `name="status"` and its value.

                In `update()`, validate with `Rule::enum(InquiryStatus::class)`. Here every state is allowed, so you do not need `only()`. Then `$inquiry->update($validated)`.

                No email goes to the visitor. The reply itself is the email.
                TEXT)
            ->think('In applications, staff could not go back to `submitted`. Here, staff can go back to `open`. Why is that right for each one?')
            ->hint('`@unless ($inquiry->status === $status)` hides the button for the current status.')
            ->code(<<<'PHP'
                public function update(Request $request, Inquiry $inquiry): RedirectResponse
                {
                    $validated = $request->validate([
                        'status' => ['required', Rule::enum(InquiryStatus::class)],
                    ]);

                    $inquiry->update($validated);

                    return to_route('admin.inquiries.show', $inquiry)->with('status', 'Status saved.');
                }
                PHP)
            ->tryIt('As staff, open the request and click "Mark as replied". The badge changes to "Replied". Go back to Programs: the row shows "Requests (0 open)".')
            ->commit('Change the status of a request')
            ->docs('https://laravel.com/docs/validation#rule-enum', 'Laravel docs: The enum rule')
            ->check(Check::all(
                Check::route('admin.inquiries.update'),
                Check::fileContains('app/Http/Controllers/Admin/InquiryController.php', 'Rule::enum(InquiryStatus::class)'),
            )),

        Step::make('Run the checks and open your pull request')
            ->run('php artisan guide:verify requests')
            ->explain(<<<'TEXT'
                Run the acceptance test with `guide:verify`. Then run `composer ci:check`.

                In your pull request, explain the choice of two models in two or three sentences. Reviewers ask about it, because the code looks like the Applications feature. Tell them to run `php artisan migrate`, and `php artisan migrate:fresh --seed` to see the party program.
                TEXT)
            ->think('Count the lines that you copied from the Applications feature, and the lines that you reused without a copy. Which shared parts would you extract next, if a third kind of sign-up came?')
            ->tryIt('Run `composer ci:check`. Pint, PHPStan and all the tests pass.')
            ->commit('Fix the checks for requests')
            ->check(Check::testPassed('RequestsTest')),
    ]);
