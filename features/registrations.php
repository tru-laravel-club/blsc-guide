<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

/**
 * True when Program::hasSignups() names the relationship, so it counts that kind of sign-up.
 */
$hasSignupsAsks = static function (string $relationship): bool {
    $path = base_path('app/Models/Program.php');
    $code = is_file($path) ? (string) file_get_contents($path) : '';
    $start = strpos($code, 'function hasSignups');

    if ($start === false) {
        return false;
    }

    $end = strpos($code, "\n    }", $start);

    return str_contains(substr($code, $start, $end === false ? null : $end - $start), $relationship);
};

return Feature::make('registrations')
    ->title('Registrations')
    ->section(Section::Signups)
    ->level(3)
    ->needs(['applications', 'cart-orders', 'profile-answers'])
    ->summary('A parent chooses a child and a session, answers the form of the program, and pays. Age limits and registration dates apply.')
    ->teaches(['Purchasable models', 'Service classes', 'Named exceptions', 'Scoped route bindings', 'Has many through', 'Business rules in one place', 'firstOrNew'])
    ->test('RegistrationsTest.php')
    ->intro(<<<'TEXT'
        Open a club with the `Register` mode, such as the Junior Robotics & Coding Club. The page says "Registration opens soon", and nothing more. Clubs, camps and homeschool classes are the main business of the centre. In this feature, a parent registers a child and pays.

        Follow one registration from start to end. A parent opens the club page. Each session with open registration has a **Register** button. The parent clicks it and logs in, because a registration is for one of their children. The parent chooses a child. The app checks the rules for that child and that session, and then shows the program's form. The child and family answers from the last form are already filled in. The parent sends the form. The app saves the answers as a `Submission`, saves new answers to the profile, and creates a `Registration` with the status `Pending`. The registration goes into the cart.

        The parent pays through the cart, exactly as for a ticket or a membership. When the payment passes, `OrderService` asks the fulfiller of each item to fulfil it. For a registration, the fulfiller is `RegistrationService`, and it sets the status to `Confirmed`. So a confirmed registration is always a paid registration.

        You build very little new machinery. The cart, the order, the payment and the form already exist. A `Registration` only links three records: a session, a child and a submission. It implements `Purchasable`, so the cart can sell it without knowing what it is.

        The new work is the rules. A child can register only when the registration dates are open, when the child has the right age on the first day of the session, when the session has a free spot, and when the child is not registered already. The page and the save both need these rules. So the rules live in one service, `RegistrationService`, and both places ask it (see `/guide/services`). The controller only reads the request, calls the service and responds.

        One more rule protects the families. A session that families registered for must not disappear, and neither must its program. `ProgramSessionService` refuses to delete the session. For the program, a registration is one more kind of sign-up, so `Program::hasSignups()` counts it, and `ProgramService` refuses as it does for applications.
        TEXT)
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/16', 'Laravel From Scratch, episode 16: Eloquent Relationships')
    ->docs('https://laravel.com/docs/eloquent-relationships#has-many-through', 'Laravel docs: Has many through')
    ->docs('https://laravel.com/docs/routing#implicit-model-binding-scoping', 'Laravel docs: Scoped bindings')
    ->reflect([
        'Homeschool classes need a grade instead of an age next year. Where does that check go? Which files change?',
        'The cart, the order and the payment did not change in this feature. Why not? What did the `Purchasable` interface do for you?',
        'The page and `register()` both call `reasonToRefuse()` in the service. What could go wrong if the controller had its own copy of the rules?',
        'Which step was the hardest? What would help the next contributor? Open an issue or a pull request in blsc-guide to improve that step.',
    ])
    ->steps([
        Step::make('Claim the feature and copy in its test')
            ->run('php artisan guide:start registrations')
            ->explain(<<<'TEXT'
                You start with the test. Run the command below. It copies `tests/Feature/RegistrationsTest.php` into the app.

                Read the whole test before you write code. It tells you the names that later features use: the `Registration` model, the `RegistrationStatus` enum, the `confirmed()` factory state, and the routes `registrations.create` and `registrations.store`. Member pricing, Capacity and waitlist, and Registration lists all build on these names.

                Look at the helpers at the top. `programTakingRegistrations()` makes a program for ages 6 to 9 with a published form. `registrationSession()` makes a session that starts on December 1, 2026. `beforeEach()` sets the date to November 15, 2026 with `travelTo()`, so the ages and the registration dates in the test never change.

                Then look at the rule "a full session never puts a child in the cart". It does not say what the parent sees. That is on purpose: the Capacity and waitlist feature puts the child on a waitlist instead, and this rule must still pass then.

                The last two rules are for staff. A session or a program with registrations cannot be deleted. Staff go back to the page with an error, and nothing is deleted.
                TEXT)
            ->think('The test chooses the child with a `child` query parameter, not with a form field. Where else in the app do you see this pattern?')
            ->tryIt('Run `php artisan test tests/Feature/RegistrationsTest.php`. All the rules fail, because the `Registration` model does not exist yet.')
            ->commit('Add the registrations acceptance test')
            ->docs('https://laravel.com/docs/mocking#interacting-with-time', 'Laravel docs: Interacting with time')
            ->check(Check::fileExists('tests/Feature/RegistrationsTest.php')),

        Step::make('Create the RegistrationStatus enum')
            ->run('php artisan make:enum App/Enums/RegistrationStatus --string')
            ->explain(<<<'TEXT'
                A registration is in one of three states. `Pending` means that it is in the cart and nobody has paid. `Confirmed` means that the order is paid and the child has a spot. `Cancelled` means that the child does not come.

                Copy the shape of `ApplicationStatus`: the cases, a `label()` method for people and a `badge()` method for the April UI badge. The label of `Pending` can be "Not paid". That is what a parent understands.
                TEXT)
            ->think('The Capacity and waitlist feature adds two states later: `Waitlisted` and `Offered`. Which methods must change then? How does PHP tell you?')
            ->hint('Open `app/Enums/ApplicationStatus.php`. Your enum has the same two methods.')
            ->code(<<<'PHP'
                enum RegistrationStatus: string
                {
                    case Pending = 'pending';
                    case Confirmed = 'confirmed';
                    case Cancelled = 'cancelled';

                    public function label(): string
                    {
                        return match ($this) {
                            self::Pending => 'Not paid',
                            self::Confirmed => 'Confirmed',
                            self::Cancelled => 'Cancelled',
                        };
                    }

                    public function badge(): string
                    {
                        return match ($this) {
                            self::Pending => 'outline',
                            self::Confirmed => 'default',
                            self::Cancelled => 'secondary',
                        };
                    }
                }
                PHP)
            ->tryIt('In `php artisan tinker`, run `App\Enums\RegistrationStatus::Pending->label()`. You see `"Not paid"`.')
            ->commit('Add the RegistrationStatus enum')
            ->docs('https://laravel.com/docs/eloquent-mutators#enum-casting', 'Laravel docs: Enum casting')
            ->check(Check::fileContains('app/Enums/RegistrationStatus.php', 'Confirmed')),

        Step::make('Create the Registration model and table')
            ->run('php artisan make:model Registration -mf')
            ->explain(<<<'TEXT'
                A registration links a session, a child and a submission. Add these columns to the migration:

                - `program_session_id`: a foreign key to `program_sessions`.
                - `child_id`: a foreign key to `children`. Laravel guesses the table `childs` from the column name, so give the table name: `constrained('children')`.
                - `submission_id`: a foreign key to the answers.
                - `status`: a string with the default `pending`.

                Choose what happens on delete. A session with registrations must not disappear, so restrict it. The submission holds the answers, so restrict it too. A child belongs to the family: when the parent deletes the child, delete the registrations with it.

                The app often asks "how many confirmed registrations does this session have?". Add one index on `program_session_id` and `status` together, so the database answers fast.
                TEXT)
            ->think('Why is there no `user_id` column? How does the app find the parent of a registration?')
            ->hint('Look at the `applications` migration. It has the same kinds of keys.')
            ->code(<<<'PHP'
                $table->id();
                $table->foreignId('program_session_id')->constrained()->restrictOnDelete();
                $table->foreignId('child_id')->constrained('children')->cascadeOnDelete();
                $table->foreignId('submission_id')->constrained()->restrictOnDelete();
                $table->string('status')->default('pending');
                $table->timestamps();

                $table->index(['program_session_id', 'status']);
                PHP)
            ->tryIt('Run `php artisan migrate`. Then, in `php artisan tinker`, run `Schema::getColumnListing(\'registrations\')`. You see your columns.')
            ->commit('Add the Registration model and table')
            ->docs('https://laravel.com/docs/migrations#foreign-key-constraints', 'Laravel docs: Foreign key constraints')
            ->check(Check::table('registrations', ['program_session_id', 'child_id', 'submission_id', 'status'])),

        Step::make('Fill in the model, its relationships and the factory')
            ->explain(<<<'TEXT'
                Open `app/Models/Registration.php`. Cast `status` to `RegistrationStatus`, and give it the default `pending` in `$attributes`.

                Add three `BelongsTo` relationships: `session()`, `child()` and `submission()`. The column is `program_session_id`, not `session_id`, so give the column name to `session()`.

                Put only `child_id` and `submission_id` in `#[Fillable]`. The status changes only in `RegistrationService`, which you write soon. No form must send a status.

                Add a local scope `holdingASpot()`. Today it keeps the confirmed registrations. Every count of spots uses this scope. When the Capacity and waitlist feature adds a state that also holds a spot, it changes one line.

                Then add the other sides of the relationships:

                - `ProgramSession::registrations(): HasMany`. Also add `@property-read Program $program` to the docblock of `ProgramSession`. PHPStan needs it soon.
                - `Child::registrations(): HasMany`.
                - `User::registrations()`. A parent has no registrations directly. A parent has children, and each child has registrations. `hasManyThrough()` follows both steps in one query.

                In the factory, make a session of a program with the `register` mode, a child and a submission. Add a `confirmed()` state for a paid registration. Other tests use it.
                TEXT)
            ->think('`$parent->registrations()` goes through the `children` table. Which SQL join do you expect? Check your guess with `->toRawSql()` in tinker.')
            ->hint('`$this->belongsTo(ProgramSession::class, \'program_session_id\')` reads the right column.')
            ->hint('`$this->hasManyThrough(Registration::class, Child::class)` finds the registrations of all the children of the user.')
            ->code(<<<'PHP'
                /**
                 * @return BelongsTo<ProgramSession, $this>
                 */
                public function session(): BelongsTo
                {
                    return $this->belongsTo(ProgramSession::class, 'program_session_id');
                }

                /**
                 * Only include registrations that take a spot in the session.
                 *
                 * @param  Builder<Registration>  $query
                 */
                #[Scope]
                protected function holdingASpot(Builder $query): void
                {
                    $query->where('status', RegistrationStatus::Confirmed);
                }

                // app/Models/User.php
                /**
                 * @return HasManyThrough<Registration, Child, $this>
                 */
                public function registrations(): HasManyThrough
                {
                    return $this->hasManyThrough(Registration::class, Child::class);
                }
                PHP)
            ->tryIt('In `php artisan tinker`, run `App\Models\Registration::factory()->confirmed()->create()->child->user->registrations()->count()`. You see `1`.')
            ->commit('Connect registrations to sessions and children')
            ->docs('https://laravel.com/docs/eloquent-relationships#has-many-through', 'Laravel docs: Has many through')
            ->docs('https://laravel.com/docs/eloquent#local-scopes', 'Laravel docs: Local scopes')
            ->docs('https://laravel.com/docs/eloquent-factories#factory-states', 'Laravel docs: Factory states')
            ->check(Check::all(
                Check::fileContains('app/Models/Registration.php', 'function session'),
                Check::fileContains('app/Models/ProgramSession.php', 'function registrations'),
                Check::fileContains('app/Models/User.php', 'function registrations'),
                Check::fileContains('database/factories/RegistrationFactory.php', 'function confirmed'),
            )),

        Step::make('Make a registration something the cart can sell')
            ->explain(<<<'TEXT'
                The cart sells anything that implements `App\Contracts\Purchasable`. Open the interface and `app/Models/Ticket.php`. A ticket implements the four methods. A registration does the same. Each method only reads:

                - `orderDescription()`: the line on the order. Name the program, the session and the child, such as "Junior Robotics Club, Fall 2026: Sam". A parent with two children must see which line is for which child.
                - `priceCents(?User $user)`: the price of the session, from the database. The cart asks for it each time, so a form can never change the price.
                - `confirmationLine()`: one sentence for the order page and the email, such as "Sam is registered for Junior Robotics Club, Fall 2026. The first day is Dec 1, 2026."
                - `fulfiller()`: returns `RegistrationService::class`. You write that service in the next step. PHP does not load a class for `::class`, so the line works before the file exists.
                TEXT)
            ->think('The Member pricing feature gives members a lower price. Which of the four methods changes then? Which code does not change?')
            ->hint('Copy `implements Purchasable` and the four method names from `Ticket`. Then change each body.')
            ->hint('Import `App\Services\RegistrationService` at the top of the model, so `RegistrationService::class` names the right class.')
            ->code(<<<'PHP'
                public function orderDescription(): string
                {
                    return $this->session->program->name.', '.$this->session->displayName().': '.$this->child->name;
                }

                public function priceCents(?User $user): int
                {
                    return $this->session->price_cents;
                }

                public function confirmationLine(): string
                {
                    return "{$this->child->name} is registered for {$this->session->program->name}, {$this->session->displayName()}. The first day is {$this->session->starts_on->format('M j, Y')}.";
                }

                /**
                 * @return class-string<RegistrationService>
                 */
                public function fulfiller(): string
                {
                    return RegistrationService::class;
                }
                PHP)
            ->tryIt('In `php artisan tinker`, run `App\Models\Registration::factory()->create()->orderDescription()`. You see the program, the session and the child.')
            ->commit('Make registrations purchasable')
            ->docs('https://laravel.com/docs/eloquent#mass-assignment', 'Laravel docs: Mass assignment')
            ->check(Check::fileContains('app/Models/Registration.php', 'implements Purchasable')),

        Step::make('Confirm a paid registration in the registration service')
            ->run('php artisan make:class Services/RegistrationService')
            ->explain(<<<'TEXT'
                Now write the work that happens when the money arrives. Run the command below. It makes `app/Services/RegistrationService.php`. Add `implements Fulfiller` to the class, as `TicketService` does.

                `fulfil(Purchasable $item): void` sets the status to `Confirmed`. It holds three rules:

                1. Check that the item is a `Registration`. If it is not, throw an `InvalidArgumentException`: a wrong fulfiller is a bug.
                2. Change only a `Pending` registration. A registration that is confirmed or cancelled stays as it is.
                3. Be safe when it runs twice. `OrderService::markAsPaid()` already fulfils an order once. But a fulfiller must not trust that alone: a retry or a bug can call it again.

                Rules 2 and 3 fit in one query: update the row only where the status is still `Pending`. The database checks the status and changes it in one step, so two calls can never both confirm. Then `refresh()` the model, so the caller sees the new status.

                This service grows in this feature and in the next ones: it registers a child, and later it cancels registrations and runs the waitlist. All the work on registrations lives in one class, and controllers, commands and tests call it (see `/guide/services`).
                TEXT)
            ->think('Why does the status change in a service, and not in a controller or in a method on the model? Which other places will change a status later?')
            ->hint('The parameter type must stay `Purchasable`, because the interface says so. Check the type inside the method with `instanceof`.')
            ->hint('`Registration::whereKey($item->id)->where(\'status\', RegistrationStatus::Pending)->update([...])` changes the row only if it is still pending.')
            ->code(<<<'PHP'
                class RegistrationService implements Fulfiller
                {
                    /**
                     * Confirm a paid registration. A registration that is not pending stays as it is, so a second call changes nothing.
                     */
                    public function fulfil(Purchasable $item): void
                    {
                        if (! $item instanceof Registration) {
                            throw new InvalidArgumentException('RegistrationService fulfils registrations only.');
                        }

                        Registration::whereKey($item->id)
                            ->where('status', RegistrationStatus::Pending)
                            ->update(['status' => RegistrationStatus::Confirmed]);

                        $item->refresh();
                    }
                }
                PHP)
            ->tryIt('In `php artisan tinker`, run `$r = App\Models\Registration::factory()->create(); app(App\Services\RegistrationService::class)->fulfil($r); $r->status`. You see the `Confirmed` case. Run the `fulfil()` line again. Nothing changes.')
            ->commit('Confirm paid registrations in the registration service')
            ->docs('/guide/services', 'BLSC guide: Controllers and services')
            ->docs('https://laravel.com/docs/eloquent#mass-updates', 'Laravel docs: Mass updates')
            ->check(Check::fileContains('app/Services/RegistrationService.php', 'implements Fulfiller')),

        Step::make('Write every registration rule in one place')
            ->explain(<<<'TEXT'
                The registration page must tell a parent at once that Sam is too young. The save must refuse Sam too, because a person can send a request without the page. Both places ask the same question. So write the rules once, in `RegistrationService`: `reasonToRefuse(ProgramSession $session, Child $child): ?string`. It returns a sentence for the parent, or `null` when the child can register.

                Check the rules in this order:

                1. The registration dates. `isRegistrationOpen()` already exists on the session.
                2. The age on the first day of the session. Use `$child->ageOn($session->starts_on)` and compare it with `age_min` and `age_max` of the program. Either limit can be empty.
                3. The child is already registered: a registration of this child in this session that holds a spot.
                4. A free spot.

                For rule 4, add two read-only helpers to `ProgramSession`: `spotsLeft(): ?int` (the capacity minus the registrations that hold a spot, or `null` when there is no limit) and `isFull(): bool`. They only read, so they can live on the model. The program page uses them too.

                Why the first day and not today? A camp in July is for children who are 6 in July. A parent registers in March, when the child is still 5.

                This is a simple count. Two parents can still take the last spot at the same moment. The Capacity and waitlist feature closes that gap with a lock.
                TEXT)
            ->think('Homeschool classes need a grade instead of an age next year. With your method, how many places in the code change?')
            ->hint('`match (true) { ... }` checks each condition in order and returns the first sentence that matches.')
            ->hint('`$session->registrations()->whereBelongsTo($child)->holdingASpot()->exists()` asks the database one yes-or-no question.')
            ->hint('In `spotsLeft()`, `max(0, $this->capacity - $this->registrations()->holdingASpot()->count())` never goes below 0.')
            ->code(<<<'PHP'
                /**
                 * Why this child cannot register for the session, or null when the child can.
                 * Every rule for a registration lives here, so the page and register() ask the same questions.
                 */
                public function reasonToRefuse(ProgramSession $session, Child $child): ?string
                {
                    $program = $session->program;
                    $age = $child->ageOn($session->starts_on);

                    return match (true) {
                        ! $session->isRegistrationOpen() => 'Registration for this session is not open.',
                        $program->age_min !== null && $age < $program->age_min => "{$child->name} is {$age} on the first day. The youngest age for this program is {$program->age_min}.",
                        $program->age_max !== null && $age > $program->age_max => "{$child->name} is {$age} on the first day. The oldest age for this program is {$program->age_max}.",
                        $session->registrations()->whereBelongsTo($child)->holdingASpot()->exists() => "{$child->name} is already registered for this session.",
                        $session->isFull() => 'This session is full.',
                        default => null,
                    };
                }
                PHP)
            ->tryIt('In `php artisan tinker`, make a session and a child with factories. Run `app(App\Services\RegistrationService::class)->reasonToRefuse($session, $child)`. Then change the birthdate of the child to last year, and run it again. You see the sentence about the youngest age.')
            ->commit('Check the rules for a registration')
            ->docs('https://laravel.com/docs/eloquent-relationships#querying-belongs-to-relationships', 'Laravel docs: Querying belongs to relationships')
            ->check(Check::all(
                Check::fileContains('app/Services/RegistrationService.php', 'function reasonToRefuse'),
                Check::fileContains('app/Models/ProgramSession.php', 'function isFull'),
            )),

        Step::make('Register a child in the service')
            ->run('php artisan make:exception RegistrationRefused')
            ->explain(<<<'TEXT'
                Now add the method that saves a registration: `register(ProgramSession $session, Child $child, FormVersion $version, array $validated): Registration`. The answers arrive already validated. The service does not know about the request.

                It does three things:

                1. Ask `reasonToRefuse()`. If it gives a reason, throw `RegistrationRefused` with the reason as its message. The controller turns it into a message for the parent.
                2. Save the answers with `FormSubmissionService::submit()`. It saves the submission and the new answers to the profile of the parent and the child. Ask for the service in the constructor.
                3. Find or make the pending registration of this child in this session with `firstOrNew()`. Set its submission and save it. If the parent tries twice before they pay, they update the same registration. The cart key stays the same, so the cart holds it once.

                Put steps 2 and 3 in `DB::transaction()`, as `ApplicationService::apply()` does. Answers without a registration are of no use to staff.

                The service does not add the registration to the cart. The cart belongs to the visitor's session, and that is HTTP work. The controller does it in the next step.
                TEXT)
            ->think('`RegistrationRefused` carries a different message for each reason. Why is one exception with a message enough here, when `TicketService` has two exceptions?')
            ->hint('`throw new RegistrationRefused($reason)` works with the constructor of `Exception`: the first argument is the message.')
            ->hint('`$this->submissions->submit($version, $validated, $child->user, $child)` saves the answers for the parent of the child.')
            ->hint('`$session->registrations()->firstOrNew([\'child_id\' => $child->id, \'status\' => RegistrationStatus::Pending])` finds the pending registration or makes a new one. A new one gets the session ID from the relationship.')
            ->code(<<<'PHP'
                public function __construct(private FormSubmissionService $submissions) {}

                /**
                 * Save the answers and a pending registration for the cart. A second try before payment updates the same registration.
                 *
                 * @param  array<string, mixed>  $validated  the answers, checked against this version
                 *
                 * @throws RegistrationRefused when a rule refuses the child, with the reason as the message
                 */
                public function register(ProgramSession $session, Child $child, FormVersion $version, array $validated): Registration
                {
                    $reason = $this->reasonToRefuse($session, $child);

                    if ($reason !== null) {
                        throw new RegistrationRefused($reason);
                    }

                    return DB::transaction(function () use ($session, $child, $version, $validated): Registration {
                        $submission = $this->submissions->submit($version, $validated, $child->user, $child);

                        $registration = $session->registrations()->firstOrNew([
                            'child_id' => $child->id,
                            'status' => RegistrationStatus::Pending,
                        ]);
                        $registration->submission()->associate($submission)->save();

                        return $registration;
                    });
                }
                PHP)
            ->tryIt(<<<'TEXT'
                In `php artisan tinker`, make a session, a child of 3 and a published form version:

                `$session = App\Models\ProgramSession::factory()->create(); $child = App\Models\Child::factory()->create(['birthdate' => now()->subYears(3)]); $version = app(App\Services\FormService::class)->publish(App\Models\Form::factory()->create());`

                Then run `app(App\Services\RegistrationService::class)->register($session, $child, $version, [])`. You see `RegistrationRefused` with the sentence about the youngest age.
                TEXT)
            ->commit('Register a child in the registration service')
            ->docs('/guide/services', 'BLSC guide: Controllers and services')
            ->docs('https://laravel.com/docs/eloquent#retrieving-or-creating-models', 'Laravel docs: Retrieving or creating models')
            ->docs('https://laravel.com/docs/database#database-transactions', 'Laravel docs: Database transactions')
            ->check(Check::all(
                Check::fileContains('app/Services/RegistrationService.php', 'function register'),
                Check::fileExists('app/Exceptions/RegistrationRefused.php'),
            )),

        Step::make('Add the routes and the controller')
            ->run('php artisan make:controller RegistrationController')
            ->explain(<<<'TEXT'
                A registration is for one session of one program. Put the session in the URL: `/programs/{category:slug}/{program:slug}/sessions/{session}/register`. Add a `GET` route named `registrations.create` and a `POST` route named `registrations.store`. Put both in the group with the `auth` middleware, inside `Route::scopeBindings()`, next to the apply routes.

                With scoped bindings, Laravel finds the session through `$program->sessions()`. A session of another program is 404. You write no check for it.

                Both methods start with `$program->signupForm(SignupMode::Register) ?? abort(404)`. Then they find the child from the `child` query parameter, through `$request->user()->children()`. Another family's child is 404.

                `create()` shows the page. It passes the children of the parent, the chosen child, the reason to refuse from `RegistrationService`, and the fields of the form with the saved answers from `FormSubmissionService::fields()`. Show the fields only when the child can register.

                `store()` does only HTTP work, as `ApplicationController::store()` does:

                1. Get the fields with the saved answers, and validate the request with `FormRules`.
                2. Call `$registrations->register($session, $child, $version, $validated)`. When it throws `RegistrationRefused`, go back with a `child` error and the message of the exception.
                3. Add the registration to the cart with `CartService`, and go to the cart.
                TEXT)
            ->think('The test sends `price_cents` with the answers. Where does that value go? Where does the price come from?')
            ->hint('Look at `ApplicationController`. Your methods take `Category $category, Program $program, ProgramSession $session` in the order of the URL. Services come after them.')
            ->hint('`back()->withErrors([\'child\' => $exception->getMessage()])` sends the parent back to the page with the reason.')
            ->hint('On the first visit, no child is chosen. In `create()`, `$request->filled(\'child\') ? $this->chosenChild($request) : null` gives `null` then.')
            ->code(<<<'PHP'
                public function store(Request $request, Category $category, Program $program, ProgramSession $session, FormSubmissionService $submissions, RegistrationService $registrations, CartService $cart): RedirectResponse
                {
                    $version = $program->signupForm(SignupMode::Register) ?? abort(404);
                    $child = $this->chosenChild($request);
                    $fields = $submissions->fields($version, $request->user(), $child);

                    $validated = $request->validate(FormRules::for($fields), FormRules::messages(), array_column($fields, 'label', 'key'));

                    try {
                        $registration = $registrations->register($session, $child, $version, $validated);
                    } catch (RegistrationRefused $exception) {
                        return back()->withErrors(['child' => $exception->getMessage()]);
                    }

                    $cart->add($registration);

                    return to_route('cart.show')->with('status', "{$child->name} is in your cart. Pay to confirm the spot.");
                }

                private function chosenChild(Request $request): Child
                {
                    return $request->user()->children()->findOrFail($request->integer('child'));
                }
                PHP)
            ->tryIt('Run `php artisan route:list --name=registrations -v`. You see `registrations.create` and `registrations.store`. Under each one, the list of middleware has `Authenticate`.')
            ->commit('Add the registration routes and controller')
            ->docs('https://laravel.com/docs/routing#implicit-model-binding-scoping', 'Laravel docs: Scoped bindings')
            ->docs('https://laravel.com/docs/validation#quick-writing-the-validation-logic', 'Laravel docs: Writing the validation logic')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/10', 'Laravel From Scratch, episode 10: Controllers')
            ->check(Check::all(
                Check::route('registrations.create', 'registrations.store'),
                Check::fileContains('app/Http/Controllers/RegistrationController.php', 'RegistrationService'),
            )),

        Step::make('Build the registration page')
            ->explain(<<<'TEXT'
                Create `resources/views/registrations/create.blade.php`. Use `applications/create.blade.php` and `forms/show.blade.php` as examples. The page has four parts:

                1. A header with the program, the session name, the dates and the age range.
                2. If the parent has no children, an alert "Add a child first" with a link to `children.create`. Then nothing else.
                3. A small `GET` form with a select of the children and a **Continue** button. It sends the `child` query parameter back to the same page, as the child picker on `forms/show.blade.php` does.
                4. When a child is chosen: the reason to refuse in a red alert, or the `<x-dynamic-form>` with its `action` prop set to `registrations.store` and the child. Set `submit` to "Add to cart".

                Show the `child` error with `@error('child')` at the top. `store()` uses it when the rules refuse the child.
                TEXT)
            ->think('The page hides the form when `reasonToRefuse()` gives a reason. Is the check in `register()` still necessary? Why?')
            ->hint('`route(\'registrations.store\', [$program->category, $program, $session, \'child\' => $child->id])` adds `?child=5` to the URL, because `child` is not a route parameter.')
            ->tryIt(<<<'TEXT'
                Log in as `test@example.com` with the password `password`. Add a child of 7 on the "My children" page. Then open http://localhost:8000/programs/clubs/junior-robotics-coding-club/sessions/2/register. Choose the child and click Continue. You see the form.

                Now choose a child of 12. You see the red alert with the oldest age.
                TEXT)
            ->commit('Add the registration page')
            ->docs('https://laravel.com/docs/blade#if-statements', 'Laravel docs: If statements')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/7', 'Laravel From Scratch, episode 7: Forms')
            ->check(Check::view('registrations.create')),

        Step::make('Add a Register button to each open session')
            ->explain(<<<'TEXT'
                Open `resources/views/programs/show.blade.php`. The mode box shows "Registration opens soon" for every register program. Keep that text for a program without a published form.

                When `signupForm(SignupMode::Register)` returns a version, show the box "Registration is open". Tell the parent what happens next: choose a date, log in, choose a child, answer the questions, pay in the cart.

                Then, in the card of each session, add a **Register** button to `registrations.create`. Show it only when registration is open for that session. When the session is full, show "This session is full." instead. `isFull()` only reads, so the view can call it on the model.

                The view calls `signupForm()` in the box and in each card. Save the answer once at the top with `@php($takesRegistrations = ...)`.
                TEXT)
            ->think('The page shows "This session is full", and `register()` checks again. Between the two, another parent pays for the last spot. What does the second parent see?')
            ->hint('Put the new box first in the `@if`, before the Apply case: `@if ($takesRegistrations) ... @elseif ($program->signupForm(App\Enums\SignupMode::Apply))`.')
            ->code(<<<'PHP'
                @if ($takesRegistrations && $session->isRegistrationOpen())
                    @if ($session->isFull())
                        <p class="font-medium">This session is full.</p>
                    @else
                        <div class="pt-2">
                            <april:button-link href="{{ route('registrations.create', [$program->category, $program, $session]) }}" wire:navigate>Register</april:button-link>
                        </div>
                    @endif
                @endif
                PHP)
            ->tryIt('Run `php artisan test tests/Feature/SignupModesTest.php`. The old rules still pass: a register program without a form still says "Registration opens soon".')
            ->commit('Show Register buttons on the program page')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/6', 'Laravel From Scratch, episode 6: Blade Directives')
            ->check(Check::fileContains('resources/views/programs/show.blade.php', 'registrations.create')),

        Step::make('Show the registrations on the dashboard')
            ->explain(<<<'TEXT'
                In `DashboardController`, load the registrations of the parent with `$request->user()->registrations()`. Leave out the pending ones: they are only in a cart. Load the child, the session, the program and the category with `with()`.

                The query joins `registrations` and `children`. Both tables have a `created_at` column. So write the table name in front: `latest('registrations.created_at')`. Without it, the database does not know which column you mean. Do the same for `registrations.status`, because a later change can add a `status` column to `children`.

                In `dashboard.blade.php`, add a card "My registrations" above "My applications". Each row shows the child, the program, the session, the dates and the status badge.
                TEXT)
            ->think('Why is it safe to load the registrations without a policy? Who can reach another family\'s registrations through `$request->user()`?')
            ->hint('`->whereNot(\'registrations.status\', RegistrationStatus::Pending)` leaves out the registrations in a cart.')
            ->code(<<<'PHP'
                'registrations' => $request->user()->registrations()
                    ->whereNot('registrations.status', RegistrationStatus::Pending)
                    ->with(['child', 'session.program.category'])
                    ->latest('registrations.created_at')
                    ->get(),
                PHP)
            ->tryIt('Register your child for a club, check out, and click Approve on the fake payment page. Open the dashboard. The card "My registrations" shows your child with the badge "Confirmed".')
            ->commit('Show registrations on the dashboard')
            ->docs('https://laravel.com/docs/eloquent-relationships#nested-eager-loading', 'Laravel docs: Nested eager loading')
            ->check(Check::fileContains('app/Http/Controllers/DashboardController.php', 'registrations()')),

        Step::make('Keep sessions and programs that families registered for')
            ->run('php artisan make:exception SessionHasRegistrations')
            ->explain(<<<'TEXT'
                Staff can delete a session on its edit page. Now try it with a session that has a registration. The migration restricts the delete, so the database refuses, and staff see an error page. You fixed the same problem for programs in Applications: the code must refuse first, and say why.

                Open `app/Services/ProgramSessionService.php`. In `delete()`, throw `SessionHasRegistrations` when the session has any registration, even a pending or a cancelled one. Each one points to the answers of a family. Give the exception the message "Families registered for this session. Keep it, so their registrations stay complete." Catch it in `Admin\ProgramSessionController::destroy()`, as `Admin\ProgramController::destroy()` catches `ProgramHasSignups`. Go back with a `session` error, and show it with `@error('session')` on `admin/sessions/edit.blade.php`.

                A program has the same problem. `ProgramService::delete()` deletes the program, and the database deletes its sessions. A session with registrations stops that delete too. A registration is one more kind of sign-up, so add one line to `Program::hasSignups()`. `ProgramService` and `Admin\ProgramController` already refuse with a message, so they do not change.
                TEXT)
            ->think('A session gets its own exception, but a program does not get a new one. Why? Which question does each exception answer?')
            ->hint('`$session->registrations()->exists()` asks the database one yes-or-no question.')
            ->hint('A program has no `registrations()` relationship. `$this->sessions()->whereHas(\'registrations\')->exists()` is `true` when any session of the program has a registration.')
            ->code(<<<'PHP'
                // app/Services/ProgramSessionService.php
                /**
                 * Delete a session that nobody registered for.
                 *
                 * @throws SessionHasRegistrations when the session has registrations
                 */
                public function delete(ProgramSession $session): void
                {
                    if ($session->registrations()->exists()) {
                        throw new SessionHasRegistrations;
                    }

                    $session->delete();
                }

                // app/Http/Controllers/Admin/ProgramSessionController.php
                try {
                    $sessions->delete($session);
                } catch (SessionHasRegistrations $exception) {
                    return back()->withErrors(['session' => $exception->getMessage()]);
                }

                // app/Models/Program.php
                public function hasSignups(): bool
                {
                    return $this->applications()->exists()
                        || $this->inquiries()->exists()
                        || $this->sessions()->whereHas('registrations')->exists();
                }
                PHP)
            ->tryIt('Run `php artisan test --filter="cannot delete"`. The rules for the session and the program pass. Then run `php artisan test tests/Feature/Admin/ProgramManagementTest.php`. Staff can still delete a session and a program that nobody registered for.')
            ->commit('Keep sessions and programs with registrations')
            ->docs('/guide/services', 'BLSC guide: Controllers and services')
            ->docs('https://laravel.com/docs/eloquent-relationships#querying-relationship-existence', 'Laravel docs: Querying relationship existence')
            ->check(Check::all(
                Check::fileContains('app/Services/ProgramSessionService.php', 'SessionHasRegistrations'),
                fn (): bool => $hasSignupsAsks('registrations'),
            )),

        Step::make('Seed a registration form for the clubs and camps')
            ->explain(<<<'TEXT'
                On a fresh database, the register programs have no form, so they show "Registration opens soon". On the live site, staff choose a form in the program admin. On your machine, the seeder does it.

                In `SignupFormSeeder`, loop over the programs with the `register` mode. Give each one a form "Club and camp registration" with the library fields `school`, `grade`, `allergies`, `medical_notes`, `emergency_name`, `emergency_phone` and `pickup_people`. Reuse `programForm()`: it creates the form once and connects it to each program.

                Do not add the photo consent field here. `programForm()` makes every field required, and a required consent means "you must say yes". A parent must be free to say no.
                TEXT)
            ->think('The seeder finds the programs by their mode, not by their category. Why is that better?')
            ->hint('`Program::where(\'signup_mode\', \'register\')->pluck(\'slug\')` gives the slug of each register program.')
            ->code(<<<'PHP'
                foreach (Program::where('signup_mode', 'register')->pluck('slug') as $slug) {
                    $this->programForm($slug, [
                        'name' => 'Club and camp registration',
                        'description' => 'Tell us what staff need to know to care for your child.',
                    ], ['school', 'grade', 'allergies', 'medical_notes', 'emergency_name', 'emergency_phone', 'pickup_people']);
                }
                PHP)
            ->tryIt(<<<'TEXT'
                Run `php artisan migrate:fresh --seed` and `composer dev`. Open http://localhost:8000/programs/clubs/junior-robotics-coding-club. The box says "Registration is open", and the session has a Register button.

                Register a child, then register the same child again before you pay. The cart still has one line. Pay, and try once more: the page says that the child is already registered.
                TEXT)
            ->commit('Seed the club and camp registration form')
            ->docs('https://laravel.com/docs/seeding#writing-seeders', 'Laravel docs: Writing seeders')
            ->check(Check::fileContains('database/seeders/SignupFormSeeder.php', "'signup_mode', 'register'")),

        Step::make('Run the checks and open your pull request')
            ->run('php artisan guide:verify registrations')
            ->explain(<<<'TEXT'
                Run the acceptance test with `guide:verify`. Then run `composer ci:check`. It runs Pint, PHPStan and every test, as GitHub does on your pull request.

                The old tests matter here. You changed the program page, the dashboard, `ProgramSessionService::delete()` and `Program::hasSignups()`. `SignupModesTest`, `ApplicationsTest`, `DashboardTest` and `Admin/ProgramManagementTest` prove that nothing broke.

                In your pull request, tell reviewers to run `php artisan migrate` and `php artisan db:seed --class=SignupFormSeeder`.
                TEXT)
            ->think('PHPStan says that `$this->program` is an undefined property in `ProgramSession`. Which docblock line fixes it?')
            ->tryIt('Run `composer ci:check`. Pint, PHPStan and all the tests pass.')
            ->commit('Fix the checks for registrations')
            ->check(Check::testPassed('RegistrationsTest')),
    ]);
