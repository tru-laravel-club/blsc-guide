<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('capacity-waitlist')
    ->title('Capacity and waitlist')
    ->section(Section::Signups)
    ->level(3)
    ->needs(['registrations'])
    ->summary('A full session puts new registrations on a waitlist. The scheduler offers free spots in order.')
    ->teaches(['Database transactions', 'Pessimistic locking', 'Race conditions', 'Artisan commands', 'Task scheduling', 'Queued notifications', 'Testing with time'])
    ->test('CapacityWaitlistTest.php')
    ->intro(<<<'TEXT'
        A club has 12 spots. Today the app checks for a free spot when a parent puts a child in the cart. That is too early. Two parents can put a child in the cart for the last spot, and both can pay. Then 13 children come to a room for 12. In this feature, you make the last spot go to one child only, and you give the other families a fair queue.

        First, find the moment when a spot is taken. A child in a cart has no spot: the parent can leave and never pay. A spot is taken when the money arrives, so the count happens in `Registration::fulfil()`. But a count alone is not enough. Two payments can arrive in the same second. Each one counts 11 confirmed children, and each one confirms its child. This bug is a race condition. The fix is a lock: inside a database transaction, `lockForUpdate()` locks the row of the session. The second payment waits at the lock until the first one is finished, and then it counts 12. That family's registration is cancelled, and staff refund the payment. This is rare, and the order page tells the family.

        Second, the waitlist. When a session is full, a parent can still answer the form. The registration gets the status `Waitlisted`, and the parent pays nothing. When a spot opens, because staff cancel a registration, the app gives the first child on the waitlist the status `Offered` and a deadline, `offer_expires_at`, 48 hours later. An offered spot is held: it counts as taken, so nobody else gets it. The parent gets an email, pays through the cart, and the registration is confirmed.

        Third, time. If the parent does not pay in 48 hours, the spot must move on. No request comes in at that moment, so a controller cannot do it. A scheduled Artisan command runs every 15 minutes. It cancels each expired offer and offers the spot to the next child.

        Follow the states of one registration: `Pending` (in a cart), or `Waitlisted` (in the queue). Then `Offered` (a spot is held). Then `Confirmed` (paid) or `Cancelled`. Two of these states hold a spot: `Confirmed` and `Offered`. That rule lives in one place, and every count uses it.
        TEXT)
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/22', 'Laravel From Scratch, episode 22: How to Get Started Testing Your Code')
    ->docs('https://laravel.com/docs/queries#pessimistic-locking', 'Laravel docs: Pessimistic locking')
    ->docs('https://laravel.com/docs/database#database-transactions', 'Laravel docs: Database transactions')
    ->docs('https://laravel.com/docs/scheduling#scheduling-artisan-commands', 'Laravel docs: Scheduling Artisan commands')
    ->reflect([
        'Why must the count happen inside a transaction? Describe what two payments in the same second do without the lock.',
        'The plan said "checkout locks the session row". This feature locks it when the payment arrives instead. Which moment really takes a spot? What would a lock at checkout need, to be correct?',
        'The centre wants 72 hours for an offer next summer. What do you change? Should staff be able to change it without a developer?',
        'Which step was the hardest? What would help the next contributor? Open an issue or a pull request in blsc-guide to improve that step.',
    ])
    ->steps([
        Step::make('Claim the feature and copy in its test')
            ->run('php artisan guide:start capacity-waitlist')
            ->explain(<<<'TEXT'
                You start with the test. Run the command below. It copies `tests/Feature/CapacityWaitlistTest.php` into the app.

                Read the first rule with care: "the last spot goes to one parent only". Two parents put a child in the cart while one spot is free. Both check out, and both pay. Only one child is confirmed, and the other order page says "refund". A test runs one request at a time, so it cannot start two payments at the same moment. It proves the logic. The lock in this feature protects the real site, where requests do run at the same time.

                Look at how the test moves time. `travelTo()` in `beforeEach()` fixes today. `$this->travel(49)->hours()` jumps ahead, so the rule can see an offer expire without a wait of two days.

                The test also fixes names that the app must use: the `SpotOffered` notification, the `registrations:expire-offers` command, and the routes `admin.registrations.cancel` and `registrations.accept`.
                TEXT)
            ->think('The rule "a full session never puts a child in the cart" in `RegistrationsTest` does not say what the parent sees. Why will it still pass after this feature?')
            ->tryIt('Run `php artisan test tests/Feature/CapacityWaitlistTest.php`. The rules fail. The first rule fails because both children are confirmed.')
            ->commit('Add the capacity and waitlist acceptance test')
            ->docs('https://laravel.com/docs/mocking#interacting-with-time', 'Laravel docs: Interacting with time')
            ->check(Check::fileExists('tests/Feature/CapacityWaitlistTest.php')),

        Step::make('Add the waitlist states and the offer deadline')
            ->run('php artisan make:migration add_offer_expires_at_to_registrations_table')
            ->explain(<<<'TEXT'
                Add two cases to `RegistrationStatus`: `Waitlisted` and `Offered`. PHP now tells you that the `match` in `label()` and in `badge()` does not cover every case. Add a label and a badge for each new case.

                Then add the rule that the whole feature uses: `holdsASpot(): bool`. It is true for `Confirmed` and `Offered`. An offered spot is held for one child, so nobody else may take it.

                An offer has a deadline. In the new migration, add a nullable `offer_expires_at` timestamp to `registrations`. Only an offered registration has a value. Cast it to `datetime` in the model, and add it to the docblock.
                TEXT)
            ->think('Why is the deadline a column with a date, and not "48 hours after `updated_at`"? What else changes `updated_at`?')
            ->hint('`return $this === self::Confirmed || $this === self::Offered;`')
            ->code(<<<'PHP'
                case Pending = 'pending';
                case Confirmed = 'confirmed';
                case Waitlisted = 'waitlisted';
                case Offered = 'offered';
                case Cancelled = 'cancelled';

                /**
                 * True when a registration in this state takes one of the spots of the session.
                 */
                public function holdsASpot(): bool
                {
                    return $this === self::Confirmed || $this === self::Offered;
                }

                // The migration
                $table->timestamp('offer_expires_at')->nullable()->after('status');
                PHP)
            ->tryIt('Run `php artisan migrate`. In tinker, run `App\Enums\RegistrationStatus::Offered->holdsASpot()`. You see `true`.')
            ->commit('Add the waitlist states')
            ->docs('https://laravel.com/docs/migrations#columns', 'Laravel docs: Columns')
            ->check(Check::all(
                Check::fileContains('app/Enums/RegistrationStatus.php', 'Offered'),
                Check::table('registrations', ['offer_expires_at']),
            )),

        Step::make('Count the spots with one rule')
            ->explain(<<<'TEXT'
                Open `app/Models/Registration.php`. The scope `holdingASpot()` keeps only `Confirmed`. Change it to ask the enum: keep every status where `holdsASpot()` is true. Now `spotsLeft()`, `isFull()` and every later count agree with the enum, and the list of states lives in one place.

                Add two more scopes:

                - `signedUp()`: `Confirmed`, `Offered` or `Waitlisted`. A child in one of these states must not sign up again for the same session.
                - `waitlist()`: the `Waitlisted` registrations, oldest first. The ID grows with each new row, so `orderBy('id')` is first come, first served.

                Then open `ProgramSession::reasonToRefuse()`. Two lines change. The "already registered" check uses `signedUp()`, so a child on the waitlist cannot join twice. And remove the "This session is full" line: a full session is no longer a reason to refuse. It is a reason to wait.
                TEXT)
            ->think('A staff member asks: "How many children are in the club?" Do you count the offered spots? Why does the answer differ from "How many spots are free?"')
            ->hint('`array_filter(RegistrationStatus::cases(), fn (RegistrationStatus $status) => $status->holdsASpot())` gives the list of states for `whereIn()`.')
            ->code(<<<'PHP'
                #[Scope]
                protected function holdingASpot(Builder $query): void
                {
                    $query->whereIn('status', array_filter(RegistrationStatus::cases(), fn (RegistrationStatus $status) => $status->holdsASpot()));
                }

                #[Scope]
                protected function signedUp(Builder $query): void
                {
                    $query->whereIn('status', [RegistrationStatus::Confirmed, RegistrationStatus::Offered, RegistrationStatus::Waitlisted]);
                }

                #[Scope]
                protected function waitlist(Builder $query): void
                {
                    $query->where('status', RegistrationStatus::Waitlisted)->orderBy('id');
                }
                PHP)
            ->tryIt('Run `php artisan test tests/Feature/RegistrationsTest.php`. All the rules still pass.')
            ->commit('Count spots with the holdsASpot rule')
            ->docs('https://laravel.com/docs/eloquent#local-scopes', 'Laravel docs: Local scopes')
            ->check(Check::fileContains('app/Models/Registration.php', 'function signedUp')),

        Step::make('Lock the session when the payment arrives')
            ->explain(<<<'TEXT'
                This is the most important step. `fulfil()` runs when the money arrives. It must take the spot only if a spot is free, and two payments must never both win the last spot.

                Wrap the body of `fulfil()` in `DB::transaction()`. Inside it, read the session again with `ProgramSession::lockForUpdate()->findOrFail(...)`. The database locks that row until the transaction ends. A second payment for the same session waits at this line. When it continues, the first child is already confirmed, and the count is correct.

                After the lock, call `$this->refresh()`. Another request can have changed this registration while you waited, so read its status again.

                Then decide the new status:

                - `Offered`: the spot is held for this child. Confirm it.
                - Not `Pending` (for example, cancelled while the parent paid): change nothing.
                - `Pending`, but the session is full: `Cancelled`. The family paid, so staff refund them.
                - Otherwise: `Confirmed`.

                Also update `confirmationLine()`. The order page and the email show it. For a cancelled registration, say that the spot was taken before the payment arrived, and that staff will refund the payment.

                `markAsPaid()` on `Order` already runs in a transaction. Laravel nests your transaction inside it, so the lock lasts until the whole order is saved.

                On your machine, the database is SQLite. SQLite has no row locks: it lets only one write happen at a time, so the order is safe anyway. Laravel leaves out `FOR UPDATE` for SQLite. On Laravel Cloud, the database supports row locks, and the same code uses them.
                TEXT)
            ->think('Why must the count happen inside a transaction? What does a lock do after the transaction ends?')
            ->hint('`match (true) { ... }` with four arms gives the new status. Put the `Offered` arm first.')
            ->hint('`ProgramSession::lockForUpdate()->findOrFail($this->program_session_id)` locks the row and returns the session.')
            ->code(<<<'PHP'
                public function fulfil(): void
                {
                    DB::transaction(function () {
                        // Lock the session row. Another payment for this session waits here until this transaction ends.
                        $session = ProgramSession::lockForUpdate()->findOrFail($this->program_session_id);
                        $this->refresh();

                        $status = match (true) {
                            $this->status === RegistrationStatus::Offered => RegistrationStatus::Confirmed,
                            $this->status !== RegistrationStatus::Pending => $this->status,
                            $session->isFull() => RegistrationStatus::Cancelled,
                            default => RegistrationStatus::Confirmed,
                        };

                        $this->forceFill(['status' => $status, 'offer_expires_at' => null])->save();
                    });
                }
                PHP)
            ->tryIt('Run `php artisan test --filter="the last spot goes to one parent only"`. The rule passes: the second child is not confirmed, and the order page says "refund".')
            ->commit('Lock the session when a registration is paid')
            ->docs('https://laravel.com/docs/database#database-transactions', 'Laravel docs: Database transactions')
            ->docs('https://laravel.com/docs/queries#pessimistic-locking', 'Laravel docs: Pessimistic locking')
            ->check(Check::fileContains('app/Models/Registration.php', 'lockForUpdate')),

        Step::make('Put the child on the waitlist when the session is full')
            ->explain(<<<'TEXT'
                Open `RegistrationController::store()`. After the answers are saved, ask `$session->isFull()`. When it is full, set the status of the registration to `Waitlisted`, save it, and send the parent to the dashboard with a message. Do not add it to the cart: the parent pays only when a spot is offered.

                Then tell parents before they answer:

                - On the program page, a full session shows "This session is full" and a **Join the waitlist** button to the same registration page.
                - On the registration page, a full session shows an alert that explains the waitlist and the 48 hours. The submit button says "Join the waitlist".

                Put the 48 hours in one place: a constant `OFFER_HOURS = 48` on `Registration`. The page, the email and the offer all read it.
                TEXT)
            ->think('The parent answers the form for the waitlist, and a spot opens three weeks later. Why is it useful that the answers are already saved?')
            ->hint('`$registration->forceFill([\'status\' => RegistrationStatus::Waitlisted])->save()` works because `status` is not fillable.')
            ->code(<<<'PHP'
                $registration->submission()->associate($submission);

                if ($session->isFull()) {
                    $registration->forceFill(['status' => RegistrationStatus::Waitlisted])->save();

                    return to_route('dashboard')->with('status', "{$child->name} is on the waitlist. We email you when a spot is free.");
                }

                $registration->save();
                $cart->add($registration);
                PHP)
            ->tryIt('As staff, set the spots of a club session to 1. Register one child and pay. Then open the program page as another parent. The session says "This session is full" and shows **Join the waitlist**. Join it. The dashboard shows the badge "Waitlisted".')
            ->commit('Put children on the waitlist')
            ->check(Check::fileContains('app/Http/Controllers/RegistrationController.php', 'RegistrationStatus::Waitlisted')),

        Step::make('Offer a free spot to the next child')
            ->run('php artisan make:notification SpotOffered')
            ->explain(<<<'TEXT'
                A spot opens when a registration that holds a spot is cancelled. Write the steps as three small methods, so the controller and the scheduled command both use them:

                - `Registration::offerSpot()`: set the status to `Offered` and `offer_expires_at` to `OFFER_HOURS` from now, then notify the parent with `SpotOffered`.
                - `ProgramSession::offerFreeSpots()`: in a transaction, lock the session row, count the free spots, and call `offerSpot()` on that many children from the `waitlist()`.
                - `Registration::cancel()`: remember whether the registration held a spot, set it to `Cancelled`, and clear the deadline. If it held a spot, call `offerFreeSpots()` on its session.

                `offerFreeSpots()` locks the row for the same reason as `fulfil()`: a payment and a cancellation can happen at the same moment.

                `SpotOffered` implements `ShouldQueue`, as `ApplicationDecided` does. The email names the child, the program and the session, and the deadline. Its button opens the dashboard.
                TEXT)
            ->think('Staff cancel a child who is on the waitlist. Does a spot open? Which line in `cancel()` decides it?')
            ->hint('`$session->registrations()->waitlist()->limit($free)->get()` gives the first children in the queue.')
            ->hint('Return early in `offerFreeSpots()` when `spotsLeft()` is `null` (no limit) or `0`.')
            ->code(<<<'PHP'
                // app/Models/Registration.php
                public function cancel(): void
                {
                    $heldASpot = $this->status->holdsASpot();

                    $this->forceFill(['status' => RegistrationStatus::Cancelled, 'offer_expires_at' => null])->save();

                    if ($heldASpot) {
                        $this->session->offerFreeSpots();
                    }
                }

                // app/Models/ProgramSession.php
                public function offerFreeSpots(): void
                {
                    DB::transaction(function () {
                        $session = self::lockForUpdate()->findOrFail($this->id);
                        $free = $session->spotsLeft();

                        if ($free === null || $free === 0) {
                            return;
                        }

                        $session->registrations()->waitlist()->with('child.user')->limit($free)->get()
                            ->each(fn (Registration $registration) => $registration->offerSpot());
                    });
                }
                PHP)
            ->tryIt('In `php artisan tinker`, find a confirmed registration in your full session and run `->cancel()` on it. Then run `App\Models\Registration::where(\'status\', \'offered\')->first()->offer_expires_at`. You see a time 48 hours from now.')
            ->commit('Offer free spots to the waitlist')
            ->docs('https://laravel.com/docs/notifications#queueing-notifications', 'Laravel docs: Queueing notifications')
            ->docs('https://laravel.com/docs/notifications#testing', 'Laravel docs: Testing notifications')
            ->check(Check::all(
                Check::fileContains('app/Models/ProgramSession.php', 'function offerFreeSpots'),
                Check::fileExists('app/Notifications/SpotOffered.php'),
            )),

        Step::make('Let staff see the waitlist and cancel a registration')
            ->run('php artisan make:controller Admin/RegistrationController')
            ->explain(<<<'TEXT'
                Staff cancel a registration when a family asks. Refunds stay in the payment provider for now: the centre has not decided its refund policy yet.

                Add `Route::post('registrations/{registration}/cancel', ...)` in the staff group, named `admin.registrations.cancel`. The group already checks that the user is staff. The `cancel()` method calls `$registration->cancel()` and goes back to the session with a message that reminds staff about the refund.

                In `Admin\ProgramSessionController::edit()`, pass two lists: the registrations that hold a spot, and the `waitlist()`. On `admin/sessions/edit.blade.php`, show both under a heading "Registrations", with the number of spots taken. Each row shows the child, the parent, the status badge, the offer deadline, and a **Cancel** button. Add `<x-admin.status />` at the top, so staff see the message.
                TEXT)
            ->think('Why does the cancel route use `POST` and not `GET`? What could a link in an email do to a `GET` route?')
            ->hint('Look at the `fields/{field}/archive` route in `routes/web.php`. It is a `POST` route with its own name, too.')
            ->hint('`onsubmit="return confirm(\'...\')"` on the form asks staff before the form sends.')
            ->code(<<<'PHP'
                Route::post('registrations/{registration}/cancel', [Admin\RegistrationController::class, 'cancel'])->name('registrations.cancel');

                public function cancel(Registration $registration): RedirectResponse
                {
                    $registration->cancel();

                    return to_route('admin.sessions.edit', $registration->program_session_id)
                        ->with('status', "{$registration->child->name} is cancelled. Refund the payment in Moneris if the family paid.");
                }
                PHP)
            ->tryIt('Log in as `staff@example.com`. Open Programs, the club, and the full session. You see the registered child and the waitlist. Click Cancel on the registered child. The first child on the waitlist now shows "Spot offered" with a deadline.')
            ->commit('Let staff cancel registrations')
            ->docs('https://laravel.com/docs/routing#route-group-middleware', 'Laravel docs: Route group middleware')
            ->check(Check::route('admin.registrations.cancel')),

        Step::make('Let the parent accept the offer')
            ->explain(<<<'TEXT'
                The parent opens the dashboard from the email. An offered registration shows a button "Pay by Nov 17, 10:00 am". It sends a `POST` to a new route, `/registrations/{registration}/accept`, named `registrations.accept`, in the `auth` group.

                The `accept()` method does three checks:

                1. The registration is for a child of this parent. If not, `abort(404)`. Do not tell a stranger that the registration exists.
                2. The offer is still open: the status is `Offered` and the deadline is in the future. Write this as `hasOpenOffer(): bool` on `Registration`. The dashboard uses it to show the button.
                3. If the offer is not open, go back to the dashboard with an `offer` error.

                Then add the registration to the cart and go to the cart. Checkout and payment work as before. `fulfil()` confirms an offered registration, because its spot is held.
                TEXT)
            ->think('The offer ends while the parent is on the payment page. The command cancels it, and then the payment arrives. What does `fulfil()` do? What does the order page say?')
            ->hint('`$this->offer_expires_at?->isFuture() === true` is false when there is no deadline.')
            ->code(<<<'PHP'
                public function accept(Request $request, Registration $registration, Cart $cart): RedirectResponse
                {
                    abort_unless($registration->child->user_id === $request->user()->id, 404);

                    if (! $registration->hasOpenOffer()) {
                        return to_route('dashboard')->withErrors(['offer' => 'This offer is no longer open.']);
                    }

                    $cart->add($registration);

                    return to_route('cart.show')->with('status', "The spot for {$registration->child->name} is in your cart. Pay before the offer ends.");
                }
                PHP)
            ->tryIt('Log in as the parent with the offer. The dashboard shows the Pay button. Click it, check out, and approve the fake payment. The badge says "Confirmed".')
            ->commit('Let parents accept a waitlist offer')
            ->check(Check::route('registrations.accept')),

        Step::make('Expire old offers on a schedule')
            ->run('php artisan make:command ExpireWaitlistOffers')
            ->explain(<<<'TEXT'
                Nobody clicks a button when an offer ends. So a command does the work, and the scheduler runs it.

                Give the command the signature `registrations:expire-offers`. In `handle()`, find the offered registrations whose `offer_expires_at` is now or earlier, and call `cancel()` on each one. `cancel()` already offers the spot to the next child. Print how many offers expired, and return `self::SUCCESS`.

                Then schedule it in `routes/console.php`: `Schedule::command('registrations:expire-offers')->everyFifteenMinutes()`. An offer then ends at most 15 minutes late.

                On Laravel Cloud, the scheduler runs by itself. On your machine, run `php artisan schedule:work`, or run the command by hand.
                TEXT)
            ->think('The command runs every 15 minutes. Two runs overlap on a slow day. Which part of your code keeps a spot from being offered twice?')
            ->hint('`Registration::where(\'status\', RegistrationStatus::Offered)->where(\'offer_expires_at\', \'<=\', now())->get()` finds the expired offers.')
            ->code(<<<'PHP'
                #[Signature('registrations:expire-offers')]
                #[Description('Cancel waitlist offers that nobody paid for in time, and offer the spots to the next children')]
                class ExpireWaitlistOffers extends Command
                {
                    public function handle(): int
                    {
                        $expired = Registration::where('status', RegistrationStatus::Offered)
                            ->where('offer_expires_at', '<=', now())
                            ->get();

                        $expired->each(fn (Registration $registration) => $registration->cancel());

                        $this->info("Expired {$expired->count()} offers.");

                        return self::SUCCESS;
                    }
                }

                // routes/console.php
                Schedule::command('registrations:expire-offers')->everyFifteenMinutes();
                PHP)
            ->tryIt('Run `php artisan schedule:list`. You see `registrations:expire-offers` every 15 minutes. Then run `php artisan registrations:expire-offers`. You see "Expired 0 offers.", because your offer has not ended yet.')
            ->commit('Expire waitlist offers on a schedule')
            ->docs('https://laravel.com/docs/artisan#writing-commands', 'Laravel docs: Writing commands')
            ->docs('https://laravel.com/docs/scheduling#scheduling-artisan-commands', 'Laravel docs: Scheduling Artisan commands')
            ->docs('https://laravel.com/docs/scheduling#running-the-scheduler', 'Laravel docs: Running the scheduler')
            ->check(Check::fileContains('routes/console.php', 'registrations:expire-offers')),

        Step::make('Run the checks and open your pull request')
            ->run('php artisan guide:verify capacity-waitlist')
            ->explain(<<<'TEXT'
                Run the acceptance test with `guide:verify`. Then run `composer ci:check`. It runs Pint, PHPStan and every test, as GitHub does on your pull request.

                `RegistrationsTest` matters here. You changed `reasonToRefuse()`, `fulfil()` and `store()`. Its rules prove that a normal registration still works.

                In your pull request, tell reviewers to run `php artisan migrate`. Explain where the lock is and why: reviewers check locks with care.
                TEXT)
            ->think('PHPStan says that `offer_expires_at` is mixed in `hasOpenOffer()`. Which docblock line is missing?')
            ->tryIt('Run `composer ci:check`. Pint, PHPStan and all the tests pass.')
            ->commit('Fix the checks for capacity and waitlist')
            ->check(Check::testPassed('CapacityWaitlistTest')),
    ]);
