<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

$component = 'resources/views/components/admin/⚡check-in.blade.php';

return Feature::make('ticket-check-in')
    ->title('Ticket check-in')
    ->section(Section::StaffTools)
    ->level(2)
    ->needs(['tickets', 'program-search'])
    ->summary('Staff type the code of a ticket at the door and mark it as used. The same ticket cannot get in twice. The page works with a normal form and, with Livewire, without a page load. Both call the same service.')
    ->teaches(['Service classes', 'Named exceptions', 'Conditional updates', 'Race conditions', 'Form requests', 'Livewire components', 'One backend, two front doors'])
    ->test('TicketCheckInTest.php')
    ->intro(<<<'TEXT'
        On the night of BIG Night Out, a visitor shows their ticket code at the door. A staff member types the code on a staff page. The page says "Checked in" with the name of the ticket, or it says why the visitor cannot get in.

        A ticket from the Tickets feature has a `code` when it is paid and a `used_at` date when it is used. Check-in sets `used_at`. An unpaid ticket has no code, so staff cannot find it.

        Two things can go wrong at a door. A visitor shows a screenshot of a ticket that a friend already used. Or two staff members at two doors type the same code at the same moment. Both cases must give "already used" to one of them. So the code never checks first and changes later. One database query does both: it sets `used_at` only where `used_at` is still empty, and it tells you how many rows it changed. This is the same pattern as `PaymentService::complete()` and, if you built it, `GiftCertificateService::redeem()`.

        The rule lives in one method, `TicketService::checkIn()`. You give it two front doors. First a normal form and a controller, so the page works everywhere and an HTTP test can check it. Then a small Livewire component, so staff check in one visitor after the other without a page load. Both doors call the same service. Read "One backend, two front doors" in `/guide/reactive` before you start: its example is this feature.
        TEXT)
    ->docs('/guide/reactive', 'BLSC guide: The reactive way')
    ->docs('/guide/services', 'BLSC guide: Controllers and services')
    ->docs('https://laravel.com/docs/queries#update-statements', 'Laravel docs: Update statements')
    ->reflect([
        'Two staff members type the same code at the same moment. Explain, step by step, why only one of them sees "Checked in".',
        'The controller and the component both call `TicketService::checkIn()`. What would go wrong if each one had its own copy of the rule?',
        'A visitor shows a BIG Night Out ticket on the wrong night. What does the page do today? Which method would you change?',
        'Which step was the hardest? What would help the next contributor? Open an issue or a pull request in blsc-guide to improve that step.',
    ])
    ->steps([
        Step::make('Claim the feature and copy in its test')
            ->run('php artisan guide:start ticket-check-in')
            ->explain(<<<'TEXT'
                Run the command below. It copies `tests/Feature/TicketCheckInTest.php` into the app. Read the whole test first.

                Look at the second check-in rule. It travels five minutes forward and checks in the same ticket again. Then it expects the time of the first use in the error, and `used_at` must not change. The second scan must not move the time forward.

                The last two rules use `Livewire::test()`, as the program search test does. They check the second front door: the component checks in a ticket, empties the field for the next visitor, and refuses a person who is not staff.
                TEXT)
            ->think('Why does the test move the clock forward between the two check-ins?')
            ->tryIt('Run `php artisan test tests/Feature/TicketCheckInTest.php`. All the rules fail, because the `admin.check-in.show` route and the `admin.check-in` component do not exist yet.')
            ->commit('Add the ticket check-in acceptance test')
            ->docs('https://laravel.com/docs/mocking#interacting-with-time', 'Laravel docs: Interacting with time in tests')
            ->check(Check::fileExists('tests/Feature/TicketCheckInTest.php')),

        Step::make('Check in a ticket once in the ticket service')
            ->run("php artisan make:exception TicketNotFound\nphp artisan make:exception TicketAlreadyUsed")
            ->explain(<<<'TEXT'
                Add `checkIn(string $code): Ticket` to `TicketService`. It holds every rule of the door:

                1. Clean the code: trim the spaces and make it upper case. People type codes in many ways, and every front door gets this for free.
                2. Find the ticket by its code, with its type. If there is none, throw `TicketNotFound` with the message "Ticket not found. Check the code with the visitor."
                3. Update `used_at` with one query: `Ticket::whereKey($ticket->id)->whereNull('used_at')->update([...])`. The database checks and changes the row in the same moment, so two scans cannot both pass. The query returns the number of changed rows.
                4. Refresh the ticket. If the query changed `0` rows, another check-in was first: throw `TicketAlreadyUsed`. Its message shows the time of that check-in: "This ticket was already used at 7:42 PM."
                5. Otherwise, return the ticket.

                The service never builds a response. Each front door turns the exceptions into a message on the `code` field.
                TEXT)
            ->think('The controller and a Livewire component both check in tickets. Why must this rule live in the service, and not in one of them?')
            ->hint('`Ticket::with(\'ticketType\')->firstWhere(\'code\', Str::upper(trim($code)))` returns the ticket or `null`.')
            ->hint('Give `TicketAlreadyUsed` a constructor that takes the ticket: `public function __construct(Ticket $ticket)`. Build the message with `$ticket->used_at?->format(\'g:i A\')`.')
            ->hint('Why `refresh()`? The query changed the database, not the model in memory. After `refresh()`, `$ticket->used_at` holds the time from the database: yours, or the time of the other check-in.')
            ->code(<<<'PHP'
                /**
                 * Mark a paid ticket as used, once.
                 *
                 * @throws TicketNotFound when no paid ticket has this code
                 * @throws TicketAlreadyUsed when another check-in was first
                 */
                public function checkIn(string $code): Ticket
                {
                    $ticket = Ticket::with('ticketType')->firstWhere('code', Str::upper(trim($code)));

                    if ($ticket === null) {
                        throw new TicketNotFound;
                    }

                    $checkedIn = Ticket::whereKey($ticket->id)
                        ->whereNull('used_at')
                        ->update(['used_at' => now()]);

                    $ticket->refresh();

                    if ($checkedIn === 0) {
                        throw new TicketAlreadyUsed($ticket);
                    }

                    return $ticket;
                }
                PHP)
            ->tryIt(<<<'TEXT'
                In `php artisan tinker`, run `$code = App\Models\Ticket::factory()->paid()->create()->code; $s = app(App\Services\TicketService::class);`.

                Then run `$s->checkIn(strtolower($code))->used_at`. You see the time. Run it again. You see `TicketAlreadyUsed` with "This ticket was already used at" and the time. Run `$s->checkIn('NOPE1234')`. You see `TicketNotFound`.
                TEXT)
            ->commit('Check in tickets once in the ticket service')
            ->docs('/guide/services', 'BLSC guide: Controllers and services')
            ->docs('https://laravel.com/docs/queries#update-statements', 'Laravel docs: Update statements')
            ->check(Check::all(
                Check::fileContains('app/Services/TicketService.php', "whereNull('used_at')"),
                Check::fileExists('app/Exceptions/TicketNotFound.php'),
                Check::fileExists('app/Exceptions/TicketAlreadyUsed.php'),
            )),

        Step::make('Add the check-in page with a form')
            ->run("php artisan make:controller Admin/CheckInController\nphp artisan make:request CheckInRequest")
            ->explain(<<<'TEXT'
                This is the first front door: a normal form. Add two routes in the staff group: `GET check-in` named `check-in.show` and `POST check-in` named `check-in.store`. With the staff group, the names start with `admin.`, and only staff can open them.

                `CheckInRequest` checks the user with the `access-admin` gate and validates that `code` is a short string. Keep the rules in `rules()`: the component in the next step uses the same rules.

                `show()` returns `resources/views/admin/check-in/show.blade.php`. The page shows the status message with `<x-admin.status />` and one form: a `code` field with `autofocus` and a "Check in" button. `<x-form-field>` already shows the error for `code`.

                `store()` calls `$tickets->checkIn($request->validated('code'))`. When it throws `TicketNotFound` or `TicketAlreadyUsed`, redirect to the page with the message on `code`. Otherwise, redirect with the status "Checked in:" and the ticket name from `$ticket->ticketType->displayName()`. Redirect to `admin.check-in.show` each time, so the field is empty for the next visitor.

                Add a "Check-in" link to the staff menu in `resources/views/layouts/app.blade.php`.
                TEXT)
            ->think('Staff check in many visitors in a row. Why does the field need `autofocus`? What does a page load cost them each time?')
            ->hint('One `catch` can take two exceptions: `catch (TicketNotFound|TicketAlreadyUsed $exception)`.')
            ->hint('Look at `ProgramSessionRequest::authorize()`. It uses the same gate.')
            ->code(<<<'PHP'
                Route::get('check-in', [Admin\CheckInController::class, 'show'])->name('check-in.show');
                Route::post('check-in', [Admin\CheckInController::class, 'store'])->name('check-in.store');

                // In CheckInController:
                public function store(CheckInRequest $request, TicketService $tickets): RedirectResponse
                {
                    $back = to_route('admin.check-in.show');

                    try {
                        $ticket = $tickets->checkIn($request->validated('code'));
                    } catch (TicketNotFound|TicketAlreadyUsed $exception) {
                        return $back->withErrors(['code' => $exception->getMessage()]);
                    }

                    return $back->with('status', 'Checked in: '.$ticket->ticketType->displayName().'.');
                }
                PHP)
            ->tryIt(<<<'TEXT'
                In `php artisan tinker`, run `App\Models\Ticket::factory()->paid()->create()->code`. Note the code.

                Log in as `staff@example.com` and click "Check-in". Type the code in small letters. You see "Checked in: General admission." Type it again. You see "This ticket was already used at" and the time. Type `NOPE1234`. You see "Ticket not found." Each time, the whole page loads again.

                Run `php artisan test tests/Feature/TicketCheckInTest.php`. All the rules pass, except the two Livewire rules.
                TEXT)
            ->commit('Add the ticket check-in page')
            ->docs('https://laravel.com/docs/routing#route-groups', 'Laravel docs: Route groups')
            ->docs('https://laravel.com/docs/validation#form-request-validation', 'Laravel docs: Form request validation')
            ->check(Check::all(
                Check::route('admin.check-in.show', 'admin.check-in.store'),
                Check::view('admin.check-in.show'),
                Check::fileContains('app/Http/Controllers/Admin/CheckInController.php', 'checkIn('),
            )),

        Step::make('Check in without a page load')
            ->run('php artisan make:livewire admin.check-in')
            ->explain(<<<'TEXT'
                The form works. Now ask the question from `/guide/reactive`: must the user see a change from the server without leaving the page? At a busy door, yes. Staff scan one visitor after the other. A full page load for each visitor costs time, and the cursor must come back to the field each time. "A status that changes in place" is one of the cases in "When to choose which".

                Run the command below. It makes `resources/views/components/admin/⚡check-in.blade.php`, as `admin.program-list` in the program search feature. Give the class:

                1. `public string $code = '';`, connected to the field with `wire:model="code"`.
                2. `boot()` with `Gate::authorize('access-admin')`, so every request checks the user, not only the first one.
                3. A `check(TicketService $tickets)` method. Livewire fills the parameter from the container, as a controller method does. Validate with `$this->validate((new CheckInRequest)->rules())`. Call `$tickets->checkIn($this->code)`. On an exception, `$this->addError('code', $exception->getMessage())`. On success, keep the message "Checked in: ..." in a public property and empty the field with `$this->reset('code')`.

                Move the form from the page into the component. Keep `method="POST"` and the address `route('admin.check-in.store')`, and add `wire:submit="check"`. With Livewire, `wire:submit` stops the normal post and calls `check()`. Without JavaScript, the form still posts to the controller. Put `<livewire:admin.check-in />` on the page in place of the form.

                Compare `check()` with `CheckInController::store()`. The same three parts: validate, call the service, show the result. Only the way back to the browser is different.
                TEXT)
            ->think('The component calls `TicketService`, and so does the controller. A new rule says "a BIG Night Out ticket works only on the night of the event". How many files change?')
            ->hint('Livewire shares its errors with the view, so `<x-form-field name="code" ... wire:model="code" />` shows the error of `addError()`.')
            ->hint('One `catch` can take both exceptions, as in the controller.')
            ->code(<<<'PHP'
                new class extends Component
                {
                    public string $code = '';

                    public string $checkedIn = '';

                    /**
                     * Run on every request, not only the first one.
                     */
                    public function boot(): void
                    {
                        Gate::authorize('access-admin');
                    }

                    public function check(TicketService $tickets): void
                    {
                        $this->checkedIn = '';
                        $this->validate((new CheckInRequest)->rules());

                        try {
                            $ticket = $tickets->checkIn($this->code);
                        } catch (TicketNotFound|TicketAlreadyUsed $exception) {
                            $this->addError('code', $exception->getMessage());

                            return;
                        }

                        $this->checkedIn = 'Checked in: '.$ticket->ticketType->displayName().'.';
                        $this->reset('code');
                    }
                };
                PHP)
            ->tryIt('Open the check-in page and check in three tickets in a row. The message changes in place, and the cursor stays in the field. In the Network tab, each check-in is one POST to `/livewire-.../update`, not a page load. Run `php artisan test tests/Feature/TicketCheckInTest.php`. All the rules pass.')
            ->commit('Check in tickets without a page load')
            ->docs('/guide/reactive', 'BLSC guide: The reactive way')
            ->docs('https://livewire.laravel.com/docs/4.x/forms#submitting-a-form', 'Livewire docs: Submitting a form')
            ->docs('https://livewire.laravel.com/docs/4.x/actions#dependency-injection', 'Livewire docs: Dependency injection in methods')
            ->docs('https://livewire.laravel.com/docs/4.x/lifecycle-hooks#boot', 'Livewire docs: The boot hook')
            ->check(Check::all(
                Check::fileContains($component, 'checkIn('),
                Check::fileContains($component, 'function boot'),
                Check::fileContains('resources/views/admin/check-in/show.blade.php', '<livewire:admin.check-in'),
            )),

        Step::make('Run the checks and open your pull request')
            ->run('php artisan guide:verify ticket-check-in')
            ->explain(<<<'TEXT'
                Run the acceptance test with `guide:verify`. Then run `composer ci:check`. It runs Pint, PHPStan and every test.

                Look at the time in the "already used" message on your machine. The app stores times in UTC, the time zone in `config/app.php`. Note in your pull request what staff in Kamloops see, and suggest a fix if the time is wrong for them.
                TEXT)
            ->think('Staff want a scanner instead of typing. Which part of your code changes? Which part stays the same?')
            ->tryIt('Run `composer ci:check`. Pint, PHPStan and all the tests pass.')
            ->commit('Fix the checks for ticket check-in')
            ->check(Check::testPassed('TicketCheckInTest')),
    ]);
