<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('request-deposits')
    ->title('Request deposits')
    ->section(Section::Signups)
    ->level(2)
    ->needs(['requests', 'cart-orders'])
    ->summary('Staff ask for a deposit on a request, such as a party. The deposit amount comes from config, not from code.')
    ->teaches(['Config files', 'Environment values', 'Signed URLs', 'On-demand notifications', 'hasOne relationships', 'Purchasable models'])
    ->test('RequestDepositsTest.php')
    ->intro(<<<'TEXT'
        A family asks for a birthday party on a Saturday in March. Staff reply and agree on the date. Before the centre holds the date, it wants a deposit. Today staff must take the money by phone. In this feature, staff click one button, and the family gets an email with a link to pay.

        Follow one deposit. A staff member opens the request and clicks **Ask for a deposit**. The amount field already holds the usual amount, such as $50.00, from config. Staff can change it for a big party. The app saves a `Deposit` for the request and queues an email to the address on the request. The email has a **Pay the deposit** button. The family clicks it, and the deposit is in their cart. They check out as a guest, with an email, and pay. The order calls `fulfil()`, which sets `paid_at`. Staff see "paid" on the request.

        Two ideas make this safe and easy. First, the family has no account, so the link cannot depend on a login. It is a signed URL: Laravel adds a signature that only the app can make. If somebody changes the deposit ID in the link, the signature no longer matches, and the app answers 403. Second, the link only puts the deposit in the cart. It does not trust anything else in the URL. The price comes from the database when the cart asks `priceCents()`.

        Where does the usual amount come from? It changes when the centre changes its prices, and it is not a secret. It is a value that the code reads, but it is not code. So it lives in a config file, `config/blsc.php`, that reads the environment. The centre can change it on Laravel Cloud without a code change.
        TEXT)
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/16', 'Laravel From Scratch, episode 16: Eloquent Relationships')
    ->docs('https://laravel.com/docs/urls#signed-urls', 'Laravel docs: Signed URLs')
    ->docs('https://laravel.com/docs/configuration#accessing-configuration-values', 'Laravel docs: Accessing configuration values')
    ->reflect([
        'Why a signed URL and not a login? What can a person do who gets the email by mistake? What can they not do?',
        'The default amount is in config, but staff change the amount of each deposit on the page. Why both? Which one is data, and which one is a setting?',
        'School bookings need a deposit too next year. What changes in the code? What stays the same?',
        'Which step was the hardest? What would help the next contributor? Open an issue or a pull request in blsc-guide to improve that step.',
    ])
    ->steps([
        Step::make('Claim the feature and copy in its test')
            ->run('php artisan guide:start request-deposits')
            ->explain(<<<'TEXT'
                You start with the test. Run the command below. It copies `tests/Feature/RequestDepositsTest.php` into the app.

                Look at `beforeEach()`. It sets `blsc.deposit_cents` to `7500` with `config()`. The test changes the config value, not the code, and the page must show $75.00. It also calls `Notification::fake()`, so no email leaves your machine, and the test can check which emails the app sent.

                Then read the rule "a tampered or unsigned link is refused". It takes a real link and changes the deposit ID. The answer must be 403.

                Many rules call `auth()->logout()` after staff ask for the deposit. The family in the email is a guest, so the rest of the rule runs as a guest.
                TEXT)
            ->think('The test reads the link from the email with `toMail(...)->actionUrl`. Why is that better than building the link in the test?')
            ->tryIt('Run `php artisan test tests/Feature/RequestDepositsTest.php`. All the rules fail, because the `Deposit` model does not exist yet.')
            ->commit('Add the request deposits acceptance test')
            ->docs('https://laravel.com/docs/notifications#testing-on-demand-notifications', 'Laravel docs: Testing on-demand notifications')
            ->check(Check::fileExists('tests/Feature/RequestDepositsTest.php')),

        Step::make('Put the default amount in a config file')
            ->explain(<<<'TEXT'
                Create `config/blsc.php`. It returns an array of settings of the centre that the code reads. Add one key: `deposit_cents`. Read it from the environment with `env('BLSC_DEPOSIT_CENTS', 5000)`, and cast it to `int`, because environment values are strings.

                Add `BLSC_DEPOSIT_CENTS=5000` to `.env.example`, so the next contributor knows the setting exists.

                Use `env()` only in config files. The app code reads `config('blsc.deposit_cents')`. On Laravel Cloud, the config is cached, and `env()` outside a config file then returns `null`.
                TEXT)
            ->think('Why is the default amount in config, and not a constant in the `Deposit` class? Who changes it, and how often?')
            ->hint('Open `config/payments.php`. It reads `PAYMENT_GATEWAY` in the same way.')
            ->code(<<<'PHP'
                <?php

                /*
                | Settings of the BIG Little Science Centre that the code reads.
                | Change a value here, or in the environment, not in the code.
                */

                return [
                    // The deposit that staff ask for by default on a request, such as a party, in cents.
                    'deposit_cents' => (int) env('BLSC_DEPOSIT_CENTS', 5000),
                ];
                PHP)
            ->tryIt('Run `php artisan tinker` and then `config(\'blsc.deposit_cents\')`. You see `5000`. Add `BLSC_DEPOSIT_CENTS=6000` to your `.env`, restart tinker, and run it again. You see `6000`.')
            ->commit('Add the default deposit to config')
            ->docs('https://laravel.com/docs/configuration#environment-configuration', 'Laravel docs: Environment configuration')
            ->docs('https://laravel.com/docs/configuration#configuration-caching', 'Laravel docs: Configuration caching')
            ->check(Check::fileContains('config/blsc.php', 'deposit_cents')),

        Step::make('Create the Deposit model and table')
            ->run('php artisan make:model Deposit -mf')
            ->explain(<<<'TEXT'
                A deposit belongs to one request. Add these columns to the migration:

                - `inquiry_id`: a foreign key. Make it `unique()`: a request has one deposit at most. Restrict deletes, because a paid deposit is a money record.
                - `amount_cents`: an unsigned integer. Money is in cents.
                - `paid_at`: a nullable timestamp. A deposit without it is not paid.

                Then open the model. Put only `amount_cents` in `#[Fillable]`: `paid_at` changes only when the order is paid. Cast `paid_at` to a datetime. Add `inquiry(): BelongsTo` and an `isPaid(): bool` method.

                On `Inquiry`, add `deposit(): HasOne`. In the factory, make an inquiry, `5000` cents and no `paid_at`. Add a `paid()` state.
                TEXT)
            ->think('Why is `paid_at` a date and not a true-or-false column? What does staff learn from the date?')
            ->hint('`$table->foreignId(\'inquiry_id\')->unique()->constrained()->restrictOnDelete();` makes a key that is also unique.')
            ->code(<<<'PHP'
                $table->id();
                $table->foreignId('inquiry_id')->unique()->constrained()->restrictOnDelete();
                $table->unsignedInteger('amount_cents');
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();

                // app/Models/Inquiry.php
                /**
                 * @return HasOne<Deposit, $this>
                 */
                public function deposit(): HasOne
                {
                    return $this->hasOne(Deposit::class);
                }
                PHP)
            ->tryIt('Run `php artisan migrate`. In tinker, run `App\Models\Deposit::factory()->create()->inquiry->deposit->amount_cents`. You see `5000`.')
            ->commit('Add the Deposit model and table')
            ->docs('https://laravel.com/docs/eloquent-relationships#one-to-one', 'Laravel docs: One to one relationships')
            ->check(Check::table('deposits', ['inquiry_id', 'amount_cents', 'paid_at'])),

        Step::make('Make a deposit something the cart can sell')
            ->explain(<<<'TEXT'
                Implement `App\Contracts\Purchasable` on `Deposit`, as `Ticket` and `GiftCertificate` do:

                - `orderDescription()`: "Deposit: Birthday Parties, request from Maya Patel". Staff read the order and know which request it is for.
                - `priceCents()`: `amount_cents`, from the database.
                - `fulfil()`: set `paid_at` to now, once. Do nothing if it is already paid.
                - `confirmationLine()`: "Deposit paid for your Birthday Parties request. Staff will contact you to confirm the details."

                Add one more method: `payUrl(): string`. It returns `URL::signedRoute('deposits.pay', $this)`. The email and the tests use it, so the link is built in one place.
                TEXT)
            ->think('Staff change the amount after the family put the deposit in the cart, but before they check out. Which amount do they pay? Why?')
            ->hint('Copy the four methods from `GiftCertificate`. A deposit has no code: `paid_at` is what changes.')
            ->code(<<<'PHP'
                public function fulfil(): void
                {
                    if ($this->isPaid()) {
                        return;
                    }

                    $this->forceFill(['paid_at' => now()])->save();
                }

                /**
                 * The signed link in the email. It puts the deposit in the cart. Only the app can make it.
                 */
                public function payUrl(): string
                {
                    return URL::signedRoute('deposits.pay', $this);
                }
                PHP)
            ->tryIt('In tinker, run `$d = App\Models\Deposit::factory()->create(); $d->fulfil(); $d->isPaid()`. You see `true`.')
            ->commit('Make deposits purchasable')
            ->docs('https://laravel.com/docs/eloquent#mass-assignment', 'Laravel docs: Mass assignment')
            ->check(Check::fileContains('app/Models/Deposit.php', 'implements Purchasable')),

        Step::make('Add the signed link that fills the cart')
            ->run('php artisan make:controller DepositController --invokable')
            ->explain(<<<'TEXT'
                Add `Route::get('/deposits/{deposit}/pay', DepositController::class)->name('deposits.pay')` to the `signed` group in `routes/web.php`, next to the payment routes. The `signed` middleware checks the signature before your controller runs. A changed or missing signature gets a 403 page.

                The controller does one thing. If the deposit is paid, it sends the family to the cart with a message. Otherwise it adds the deposit to the cart and goes to the cart.

                Why `GET`? A link in an email can only open a page. Here a `GET` is safe: a second click adds the same deposit to the cart again, and the cart keeps it once.
                TEXT)
            ->think('The family forwards the email to a grandparent. What can the grandparent do with the link? Is that a problem?')
            ->hint('`$cart->add($deposit)` uses the key `deposit-5`. A second call replaces the same key.')
            ->code(<<<'PHP'
                public function __invoke(Deposit $deposit, Cart $cart): RedirectResponse
                {
                    if ($deposit->isPaid()) {
                        return to_route('cart.show')->with('status', 'This deposit is already paid. Thank you.');
                    }

                    $cart->add($deposit);

                    return to_route('cart.show')->with('status', 'The deposit is in your cart.');
                }
                PHP)
            ->tryIt('In tinker, run `App\Models\Deposit::factory()->create()->payUrl()`. Open the link in a private browser window. Your cart shows the deposit. Change one digit of the signature in the link and open it again. You see a 403 page.')
            ->commit('Add the signed deposit link')
            ->docs('https://laravel.com/docs/urls#validating-signed-route-requests', 'Laravel docs: Validating signed route requests')
            ->check(Check::route('deposits.pay')),

        Step::make('Email the link to the visitor')
            ->run('php artisan make:notification DepositRequested')
            ->explain(<<<'TEXT'
                The visitor may have no account, so there is no `User` to notify. Laravel sends an on-demand notification to an address: `Notification::route('mail', $inquiry->email)->notify(...)`.

                `DepositRequested` implements `ShouldQueue`, as `ApplicationDecided` does. Its mail greets the person by the name on the request, names the program and the amount, and has one button: **Pay the deposit**, with `$this->deposit->payUrl()`. Add one line that says that no account is necessary.

                Format the amount with `Number::currency()`, as `ProgramSession::price()` does.
                TEXT)
            ->think('The email is queued. Staff change the amount one minute later, before the queue sends the first email. Which amount does the first email show? Why?')
            ->hint('`->action(\'Pay the deposit\', $this->deposit->payUrl())` adds the button.')
            ->code(<<<'PHP'
                public function toMail(object $notifiable): MailMessage
                {
                    $program = $this->deposit->inquiry->program->name;
                    $amount = Number::currency($this->deposit->amount_cents / 100, in: 'CAD', locale: 'en_CA');

                    return (new MailMessage)
                        ->subject("A deposit for your {$program} request")
                        ->greeting("Hello {$this->deposit->inquiry->name},")
                        ->line("Thank you for your {$program} request. To hold the date, please pay a deposit of {$amount}.")
                        ->action('Pay the deposit', $this->deposit->payUrl())
                        ->line('You do not need an account. The button puts the deposit in your cart.');
                }
                PHP)
            ->tryIt('Run `php artisan test --filter="the link in the email"`. The rule passes: the link in the email is the signed link, and it fills the cart of a guest.')
            ->commit('Email the deposit link')
            ->docs('https://laravel.com/docs/notifications#on-demand-notifications', 'Laravel docs: On-demand notifications')
            ->docs('https://laravel.com/docs/notifications#queueing-notifications', 'Laravel docs: Queueing notifications')
            ->check(Check::fileExists('app/Notifications/DepositRequested.php')),

        Step::make('Let staff ask for a deposit')
            ->run('php artisan make:controller Admin/DepositController')
            ->explain(<<<'TEXT'
                Add `Route::post('inquiries/{inquiry}/deposit', ...)` in the staff group, named `admin.inquiries.deposit.store`.

                The `store()` method:

                1. Validates `amount` in dollars, as the session form does: required, numeric, at least 1, at most two decimals.
                2. Refuses a paid deposit with an `amount` error. A paid amount must never change.
                3. Saves the amount in cents with `$inquiry->deposit()->updateOrCreate([], [...])`. The first time it creates the deposit. Later it changes the amount of the same deposit.
                4. Sends the email, and goes back to the request with a message.

                On `admin/inquiries/show.blade.php`, add a "Deposit" section. When the deposit is paid, show the amount and the date. Otherwise show an amount field and a button. The field starts with the current amount, or with `config('blsc.deposit_cents')` for a new deposit. The button says "Ask for a deposit", or "Change and send again".
                TEXT)
            ->think('`updateOrCreate([], [...])` gets an empty first array. Which condition finds the existing deposit? Why does the relationship make it safe?')
            ->hint('`(int) round(((float) $validated[\'amount\']) * 100)` turns "80.50" into `8050` without a float error.')
            ->hint('`number_format(config(\'blsc.deposit_cents\') / 100, 2, \'.\', \'\')` gives `50.00` for the number field.')
            ->code(<<<'PHP'
                public function store(Request $request, Inquiry $inquiry): RedirectResponse
                {
                    $validated = $request->validate([
                        'amount' => ['required', 'numeric', 'min:1', 'max:5000', 'decimal:0,2'],
                    ]);

                    if ($inquiry->deposit?->isPaid()) {
                        return back()->withErrors(['amount' => 'This deposit is already paid. Its amount cannot change.']);
                    }

                    $deposit = $inquiry->deposit()->updateOrCreate([], [
                        'amount_cents' => (int) round(((float) $validated['amount']) * 100),
                    ]);

                    Notification::route('mail', $inquiry->email)->notify(new DepositRequested($deposit));

                    return to_route('admin.inquiries.show', $inquiry)->with('status', "We emailed {$inquiry->email} a link to pay the deposit.");
                }
                PHP)
            ->tryIt(<<<'TEXT'
                Send a party request as a guest on http://localhost:8000/programs/parties/birthday-parties. Log in as `staff@example.com`, open Programs, the party program, and your request. The Deposit section shows $50.00. Click Ask for a deposit.

                `composer dev` runs the queue. Open `storage/logs/laravel.log` and find "Pay the deposit". Copy the link and open it in a private window. Check out with an email and approve the fake payment. Back on the staff page, the deposit shows "paid".
                TEXT)
            ->commit('Let staff ask for a deposit')
            ->docs('https://laravel.com/docs/eloquent#upserts', 'Laravel docs: Upserts')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/11', 'Laravel From Scratch, episode 11: Request Validation')
            ->check(Check::route('admin.inquiries.deposit.store')),

        Step::make('Run the checks and open your pull request')
            ->run('php artisan guide:verify request-deposits')
            ->explain(<<<'TEXT'
                Run the acceptance test with `guide:verify`. Then run `composer ci:check`. It runs Pint, PHPStan and every test, as GitHub does on your pull request.

                In your pull request, tell reviewers to run `php artisan migrate`, and name the new setting `BLSC_DEPOSIT_CENTS`. A maintainer adds it to Laravel Cloud before the release.
                TEXT)
            ->think('PHPStan says that `$inquiry->deposit` is an undefined property. Which docblock line on `Inquiry` fixes it?')
            ->tryIt('Run `composer ci:check`. Pint, PHPStan and all the tests pass.')
            ->commit('Fix the checks for request deposits')
            ->check(Check::testPassed('RequestDepositsTest')),
    ]);
