<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('payments')
    ->title('Online payments')
    ->section(Section::Signups)
    ->level(3)
    ->needs(['accounts'])
    ->summary('Take card payments through Moneris Checkout, so card numbers never touch our server. The app talks to Moneris through a PaymentGateway interface, so BLSC can change the provider later. A Payment pays for one record, such as an order, and a signed link lets guests pay without an account.')
    ->teaches(['Interfaces', 'The service container', 'Config files', 'HTTP client', 'Http::fake', 'Events', 'Polymorphic relationships', 'Policies', 'Signed URLs'])
    ->test('PaymentsTest.php')
    ->intro(<<<'TEXT'
        Registrations, tickets, memberships and deposits all cost money. A family puts them in a cart and pays once for the whole order. The Cart and orders feature builds the cart and the `Order`. In this feature, you build the part under it: one `Payment` that pays for one record, and the code that talks to the card provider.

        A `Payment` does not know what it pays for. It has a polymorphic `payable` link, so it can point to an order today and to another kind of record later. The parent types the card number into a Moneris Checkout form. The card number goes from the browser to Moneris. It never touches the BLSC server, so BLSC does not have to store or protect card numbers.

        The app never talks to Moneris directly. It talks to a `PaymentGateway` interface with two methods: `start()` and `confirm()`. `MonerisGateway` implements the interface for real payments. `FakeGateway` implements it for your machine and for the tests. A config value names the gateway, and the service container gives that gateway to the controller. If BLSC changes to a different provider, a contributor writes one new class and changes one line.

        Guests buy tickets without an account, so a payment cannot always belong to a user. The payment page uses a signed link instead. Only the app can make the link, so nobody can guess the page of another payment. When a payment belongs to an account, a policy also checks that the right parent is logged in.

        One rule protects the money: the server, not the browser, decides that a payment passed. A user controls their browser and can send any request to the server. So when the browser says "done", the server asks Moneris for the receipt and checks the result and the amount. If both are correct, the server marks the payment as paid and sends a `PaymentSucceeded` event. Other features listen for that event.
        TEXT)
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/18', 'Laravel From Scratch, episode 18: Authorization Using Policies')
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/22', 'Laravel From Scratch, episode 22: How to Get Started Testing Your Code')
    ->docs('https://laravel.com/docs/container#binding-interfaces-to-implementations', 'Laravel docs: Binding interfaces to implementations')
    ->docs('https://laravel.com/docs/urls#signed-urls', 'Laravel docs: Signed URLs')
    ->docs('https://laravel.com/docs/http-client#faking-responses', 'Laravel docs: Faking HTTP responses')
    ->docs('https://developer.moneris.com/hosted-solutions/docs/setup-guide', 'Moneris docs: Checkout setup guide')
    ->reflect([
        'Why does `complete()` ask the gateway to confirm the payment, and not trust the browser that posted the form?',
        'A guest payment has no owner. What protects it? What can a person do if somebody forwards them the link?',
        'BLSC wants to use Stripe next year. Which files do you change, and which files stay the same?',
        'Which step was the hardest? What would help the next contributor? Open an issue or a pull request in blsc-guide to improve that step.',
    ])
    ->steps([
        Step::make('Claim the feature and copy in its test')
            ->run('php artisan guide:start payments')
            ->explain(<<<'TEXT'
                You start with the test, not with the code. Run the command below. It copies `tests/Feature/PaymentsTest.php` into the app.

                Read the whole test first. It shows how the parts fit together. The first rules use routes and the fake gateway, as a payer would. Look at how they open a payment: with `$payment->url()` or `URL::signedRoute()`, never with a plain `route()`. A plain link gets a 403 error. The last rules call `MonerisGateway` directly. They use `Http::fake()`, so no request leaves your machine.

                Look at `beforeEach()` at the top, too. It sets the payments config before each rule, so every rule starts from the same values. Do not change what the test checks. If you think that a rule is wrong, ask on the issue.
                TEXT)
            ->think('Some payments in the test have `user_id` set to `null`. Who pays them? How does the app know that this person may open the payment?')
            ->tryIt('Run `php artisan test tests/Feature/PaymentsTest.php`. All the rules fail. That is correct, because the payment code does not exist yet.')
            ->commit('Add the payments acceptance test')
            ->docs('https://laravel.com/docs/http-tests', 'Laravel docs: HTTP tests')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/22', 'Laravel From Scratch, episode 22: How to Get Started Testing Your Code')
            ->check(Check::fileExists('tests/Feature/PaymentsTest.php')),

        Step::make('Create the status enum')
            ->run('php artisan make:enum App/Enums/PaymentStatus --string')
            ->code(<<<'PHP'
                case Pending = 'pending';
                case Paid = 'paid';
                case Failed = 'failed';
                PHP)
            ->explain(<<<'TEXT'
                A payment is always in one of three states. An enum lists these states in one place. PHP refuses any other value, so a typo such as `'payed'` cannot get into the database.

                Run the command below. `--string` makes a backed enum. Each case has a string value, and the database stores that value. The command names the full namespace, `App/Enums`, so the file goes into `app/Enums` even if that folder does not exist yet. Add three cases: Pending (pending), Paid (paid) and Failed (failed).

                Failed is not the end. A failed payment can be tried again. The payer opens the payment again, and the app starts a new checkout.
                TEXT)
            ->think('A card is declined. What can the payer do next? Which status lets them do it?')
            ->tryIt('In `php artisan tinker`, run `App\Enums\PaymentStatus::cases()`. You see the three cases. Type `exit` to leave tinker.')
            ->commit('Add the PaymentStatus enum')
            ->docs('https://laravel.com/docs/eloquent-mutators#enum-casting', 'Laravel docs: Enum casting')
            ->check(fn (): bool => is_file(base_path('app/Enums/PaymentStatus.php'))),

        Step::make('Create the Payment model, migration and factory')
            ->run('php artisan make:model Payment -mf')
            ->explain(<<<'TEXT'
                Now you make a home for the payments. Run the command below. It makes the `Payment` model, a migration for the `payments` table and a factory for the tests.

                One `payments` table serves every feature that takes money. Orders, and anything else that needs a payment later, do not get their own payment tables. So the payment code stays in one place.
                TEXT)
            ->think('Look at the test. Which fields does a payment need?')
            ->tryIt('Open the three new files: `app/Models/Payment.php`, the new file in `database/migrations` and `database/factories/PaymentFactory.php`. You fill them in the next steps.')
            ->commit('Add the Payment model')
            ->docs('https://laravel.com/docs/eloquent#generating-model-classes', 'Laravel docs: Generating model classes')
            ->check(Check::classExists('App\Models\Payment')),

        Step::make('Add the columns, then migrate')
            ->run('php artisan migrate')
            ->code(<<<'PHP'
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->nullableMorphs('payable');
                $table->string('description');
                $table->unsignedInteger('amount_cents');
                $table->string('status')->default('pending');
                $table->string('gateway')->nullable();
                $table->string('reference')->nullable();
                $table->string('return_url')->nullable();
                $table->dateTime('paid_at')->nullable();
                $table->timestamps();
                PHP)
            ->explain(<<<'TEXT'
                Add the columns to the migration, then run `php artisan migrate`. Store the price as whole cents in `amount_cents`, such as `2500` for $25.00. Decimal numbers in PHP can round, and money must not round.

                `user_id` is nullable, because a guest can pay without an account. `nullOnDelete()` keeps the payment when a user deletes their account. A payment is a record of money, and the centre needs it for its books.

                `payable` links the payment to what it pays for, such as an order. It is a polymorphic relationship, so one payments table serves every feature. `nullableMorphs('payable')` makes two columns. `payable_type` stores the class name, and `payable_id` stores the row.

                `return_url` is the page that the payer goes back to after the payment, such as their order. The payment does not know what an order is, so the code that makes the payment gives it this address.

                Next, open the model. Add `payable(): MorphTo` and `user(): BelongsTo`. Put `user_id`, `description`, `amount_cents` and `return_url` in `#[Fillable]`. The code that makes a payment fills in these columns. Keep `status`, `gateway`, `reference` and `paid_at` out. Only the payment code sets them. If `status` were fillable, a mistake in a later feature could mark a payment as paid.

                Cast `status` to `PaymentStatus` and `paid_at` to a datetime. Add an `amount()` method that formats the price, as `ProgramSession::price()` does. Update the docblock with each column, so PHPStan knows them.
                TEXT)
            ->think('Why does the table store `2500` cents and not `25.00` dollars? Why must a payment stay when its user deletes their account?')
            ->hint('Look at `app/Models/ProgramSession.php`. Find `price()`, `formatCents()` and the `casts()` method.')
            ->hint('`payable()` returns `$this->morphTo();`. Laravel reads `payable_type` and `payable_id` to find the row.')
            ->hint('Give the model a default status with `protected $attributes = [\'status\' => \'pending\'];`. Then a new `Payment` is pending before you save it.')
            ->tryIt(<<<'TEXT'
                In `php artisan tinker`, run `Schema::getColumnListing('payments')`. You see your columns.

                Then run `(new App\Models\Payment)->fill(['description' => 'Test', 'amount_cents' => 100, 'status' => 'paid'])->status`. You see the `Pending` case, not `Paid`. Laravel ignored `status`, because it is not fillable.
                TEXT)
            ->commit('Add the payments columns')
            ->docs('https://laravel.com/docs/eloquent-relationships#one-to-many-polymorphic-relations', 'Laravel docs: One to many polymorphic relations')
            ->docs('https://laravel.com/docs/migrations#foreign-key-constraints', 'Laravel docs: Foreign key constraints')
            ->docs('https://laravel.com/docs/eloquent#mass-assignment', 'Laravel docs: Mass assignment')
            ->docs('https://laravel.com/docs/helpers#method-number-currency', 'Laravel docs: Number::currency()')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/16', 'Laravel From Scratch, episode 16: Eloquent Relationships')
            ->check(Check::table('payments', ['user_id', 'payable_type', 'payable_id', 'description', 'amount_cents', 'status', 'gateway', 'reference', 'return_url', 'paid_at'])),

        Step::make('Fill in the factory')
            ->code(<<<'PHP'
                'user_id' => User::factory(),
                'description' => 'Robotics Club, Fall 2026',
                'amount_cents' => 2500,
                PHP)
            ->explain(<<<'TEXT'
                The tests make payments with the factory. By default, a payment belongs to a new user, so `User::factory()` makes one. A test that needs a guest payment says so: `Payment::factory()->create(['user_id' => null])`. The test expects `$25.00` on the page, so the factory uses `2500` cents.

                Leave out `status`, `gateway`, `reference`, `return_url` and `paid_at`. A new payment is pending, and the gateway and the controller set the other columns later. Leave out `payable`, too. It is nullable, and the tests do not need it.
                TEXT)
            ->think('Why does the factory not set `status`?')
            ->tryIt('In `php artisan tinker`, run `App\Models\Payment::factory()->make()->amount()`. You see `$25.00`.')
            ->commit('Fill in the Payment factory')
            ->docs('https://laravel.com/docs/eloquent-factories#defining-model-factories', 'Laravel docs: Defining model factories')
            ->check(Check::fileContains('database/factories/PaymentFactory.php', "'amount_cents'")),

        Step::make('Create the payments config file')
            ->code(<<<'PHP'
                return [
                    'gateway' => env('PAYMENT_GATEWAY', 'fake'),

                    'moneris' => [
                        'store_id' => env('MONERIS_STORE_ID'),
                        'api_token' => env('MONERIS_API_TOKEN'),
                        'checkout_id' => env('MONERIS_CHECKOUT_ID'),
                        'environment' => env('MONERIS_ENVIRONMENT', 'qa'),
                    ],
                ];
                PHP)
            ->explain(<<<'TEXT'
                The app must know which gateway to use and the Moneris keys. These values are different on your machine and on the live site. So they go in `.env`, and a config file reads them.

                Create `config/payments.php`. The rest of the code reads `config('payments.gateway')`, never `env()`. When the live site caches its config, `env()` returns `null` outside the config files.

                Add the five variables to `.env.example`. Give `PAYMENT_GATEWAY` the value `fake` and `MONERIS_ENVIRONMENT` the value `qa`, and leave the keys empty. Git keeps `.env.example`, but not `.env`. So `.env.example` tells other contributors the names, and `.env` keeps the secrets.

                Never commit a real API token. A maintainer gives you the QA test values.
                TEXT)
            ->think('Why does the gateway default to `fake` and not to `moneris`?')
            ->hint('Look at an existing config file, such as `config/services.php`.')
            ->hint('The second argument of `env()` is the default value. Laravel uses it when `.env` does not set the variable.')
            ->tryIt('In `php artisan tinker`, run `config(\'payments.gateway\')`. You see `"fake"`. Then run `config(\'payments.moneris\')`. You see the four keys, and `environment` is `"qa"`.')
            ->commit('Add the payments config file')
            ->docs('https://laravel.com/docs/configuration#accessing-configuration-values', 'Laravel docs: Accessing configuration values')
            ->docs('https://laravel.com/docs/configuration#environment-configuration', 'Laravel docs: Environment configuration')
            ->docs('https://laravel.com/docs/configuration#configuration-caching', 'Laravel docs: Configuration caching')
            ->check(Check::all(Check::fileContains('config/payments.php', "'moneris'"), Check::fileContains('.env.example', 'PAYMENT_GATEWAY'))),

        Step::make('Write the PaymentGateway interface')
            ->code(<<<'PHP'
                namespace App\Payments;

                use App\Models\Payment;
                use Symfony\Component\HttpFoundation\Response;

                interface PaymentGateway
                {
                    /**
                     * Ask the provider for a checkout and save its reference on the payment.
                     * Return the page that shows the checkout, or a redirect to the provider.
                     */
                    public function start(Payment $payment): Response;

                    /** Ask the provider if the payment was approved. */
                    public function confirm(Payment $payment): bool;
                }
                PHP)
            ->explain(<<<'TEXT'
                This step is the center of the design. An interface is a list of methods without code. A class that `implements` the interface must have each method, with the same parameters and return type.

                Create `app/Payments/PaymentGateway.php`. The rest of the app uses only this interface, never Moneris directly. So the controller does not know which provider it talks to. That is why BLSC can change the provider later.

                Every provider does two jobs. It starts a checkout, and it tells you if the payment was approved. `start()` returns a normal response. Moneris returns a page with its checkout form. Stripe and Square would return `redirect()->away()` to their own page. Both are a Symfony `Response`, so one return type covers every provider.
                TEXT)
            ->think('What must every payment provider do, no matter how it works?')
            ->hint('Two methods are enough. One starts the checkout, and one confirms the result.')
            ->hint('Use `Symfony\Component\HttpFoundation\Response` as the return type of `start()`. A view response and a redirect both extend it.')
            ->tryIt('In `php artisan tinker`, run `interface_exists(App\Payments\PaymentGateway::class)`. You see `true`.')
            ->commit('Add the PaymentGateway interface')
            ->docs('https://laravel.com/docs/contracts#when-to-use-contracts', 'Laravel docs: When to use contracts')
            ->docs('https://laravel.com/docs/responses#redirecting-external-domains', 'Laravel docs: Redirecting to external domains')
            ->check(Check::fileContains('app/Payments/PaymentGateway.php', 'interface PaymentGateway')),

        Step::make('Write the fake gateway')
            ->code(<<<'PHP'
                class FakeGateway implements PaymentGateway
                {
                    public function start(Payment $payment): Response
                    {
                        $payment->forceFill(['reference' => 'fake-'.Str::random(12)])->save();

                        return response()->view('payments.fake', ['payment' => $payment]);
                    }

                    public function confirm(Payment $payment): bool
                    {
                        return request()->input('fake_result') === 'approve';
                    }
                }
                PHP)
            ->explain(<<<'TEXT'
                You cannot pay Moneris each time that you run a test. So you write a second gateway that only pretends. It has the same two methods, so the controller cannot see a difference. This shows why the interface is useful. You use this gateway on your machine and in tests.

                Create `app/Payments/FakeGateway.php`. `start()` saves a random reference and returns `response()->view('payments.fake')`. `confirm()` returns `true` when the request has `fake_result` set to `approve`.

                Create the view `resources/views/payments/fake.blade.php` with the `<x-layouts::site>` layout. It shows the description and `amount()`, an "Approve test payment" button and a "Decline test payment" button. Each button posts `fake_result=approve` or `fake_result=decline` to the `payments.complete` route. The route needs a signed link, so the `action` attribute of the form is `URL::signedRoute('payments.complete', $payment)`. You add the route in a later step.

                When the status is failed, show "The last payment did not go through. Try again." above the buttons. The test checks for this text.
                TEXT)
            ->think('The controller does not know which gateway it has. What must be the same in both gateways for that to work?')
            ->hint('Start with `class FakeGateway implements PaymentGateway`. PHP then tells you if a method is missing.')
            ->hint('`reference` is not fillable. Set it with `$payment->forceFill([\'reference\' => \'fake-\'.Str::random(12)])->save();`.')
            ->hint('Give both buttons `name="fake_result"` and a different `value`. The browser sends the value of the button that you click.')
            ->hint('`<april:alert variant="destructive">` with a `<slot:title>` shows a red message. Show it `@if ($payment->status === App\Enums\PaymentStatus::Failed)`.')
            ->tryIt(<<<'TEXT'
                The page needs the route, so you open it in the controller step. Try `confirm()` now. In `php artisan tinker`, run this line:

                `request()->merge(['fake_result' => 'approve']); (new App\Payments\FakeGateway)->confirm(App\Models\Payment::factory()->make())`

                You see `true`. Run it again with `'decline'`. You see `false`.
                TEXT)
            ->commit('Add the fake payment gateway')
            ->docs('https://laravel.com/docs/responses#view-responses', 'Laravel docs: View responses')
            ->docs('https://laravel.com/docs/urls#signed-urls', 'Laravel docs: Signed URLs')
            ->check(Check::all(Check::classExists('App\Payments\FakeGateway'), Check::view('payments.fake'))),

        Step::make('Write the Moneris gateway')
            ->code(<<<'PHP'
                /**
                 * Send one request to Moneris Checkout and return the "response" part of the answer.
                 *
                 * @param  array<string, mixed>  $data
                 * @return array<string, mixed>
                 */
                private function send(array $data): array
                {
                    return Http::post($this->host().'/chktv2/request/request.php', [
                        'store_id' => $this->config['store_id'],
                        'api_token' => $this->config['api_token'],
                        'checkout_id' => $this->config['checkout_id'],
                        'environment' => $this->config['environment'],
                        ...$data,
                    ])->throw()->json('response', []);
                }

                private function host(): string
                {
                    return $this->config['environment'] === 'prod'
                        ? 'https://gateway.moneris.com'
                        : 'https://gatewayt.moneris.com';
                }
                PHP)
            ->explain(<<<'TEXT'
                Now you write the real gateway. Moneris Checkout uses two server requests, with the browser between them. First, `start()` sends a preload request, and Moneris sends back a ticket. Then the page shows the Moneris card form. When the payer finishes, the page posts to `payments.complete`. Last, `confirm()` sends a receipt request with the ticket and checks the answer.

                Create `app/Payments/MonerisGateway.php`. Its constructor takes the moneris config array in a parameter called `$config`. The test passes the config to the container by that name. If you use a different name, the container cannot fill the parameter.

                The host is `https://gatewayt.moneris.com` for `qa` and `https://gateway.moneris.com` for `prod`. Both requests go to `/chktv2/request/request.php` on that host.

                `start()` sends the preload request with `action` set to `preload` and `txn_total` set to the amount in dollars, such as `25.00`. Moneris sends back `success` as the string `"true"` and a `ticket`. Throw an exception if `success` is not `"true"`. Save the ticket as the reference and return `response()->view('payments.moneris')`.

                `confirm()` sends a receipt request with `action` set to `receipt` and the ticket. It returns `true` only if `receipt.result` is `"a"` and `receipt.cc.amount` equals the amount. The amount check makes sure that Moneris charged the full price.

                Create the view `resources/views/payments/moneris.blade.php`. The view loads the Moneris script, `/chktv2/js/chkt_v3.00.js` on the same host, and calls `startCheckout(ticket)`. In the `payment_complete` callback, it submits a form to the signed `payments.complete` link. Never decide in the browser that a payment passed.
                TEXT)
            ->think('The browser calls `payment_complete`. Why does the server still ask Moneris for the receipt?')
            ->hint('Write a private `host()` method. It returns the `gatewayt` host for `qa` and the `gateway` host for `prod`. The requests and the script use the same host.')
            ->hint('Both requests send the same four config values. Put the shared code in one private `send()` method. Call `->throw()` on the response, then read the data with `->json(\'response\', [])`.')
            ->hint('Moneris sends the string `"true"`, not the boolean `true`. Compare with `=== \'true\'`. `data_get($response, \'receipt.cc.amount\')` reads a nested value. Compare it with the amount as text: `number_format($payment->amount_cents / 100, 2, \'.\', \'\')` gives `25.00`.')
            ->hint('Pass the ticket, the mode (`$this->config[\'environment\']`) and the script URL to the view. In the view, `@js($ticket)` writes a PHP value safely into JavaScript.')
            ->tryIt(<<<'TEXT'
                In `php artisan tinker`, fake the Moneris answer, then confirm a payment. Run this as one line:

                `Http::fake(['gatewayt.moneris.com/*' => Http::response(['response' => ['success' => 'true', 'receipt' => ['result' => 'a', 'cc' => ['amount' => '25.00']]]])]); app(App\Payments\MonerisGateway::class, ['config' => config('payments.moneris')])->confirm(App\Models\Payment::factory()->make())`

                You see `true`. `Http::fake()` stopped the real request, so no request went to Moneris. Run it again with the amount `'1.00'`. You see `false`, because Moneris charged the wrong amount.
                TEXT)
            ->commit('Add the Moneris payment gateway')
            ->docs('https://developer.moneris.com/hosted-solutions/docs/setup-guide', 'Moneris docs: Checkout setup guide')
            ->docs('https://laravel.com/docs/http-client#making-requests', 'Laravel docs: Making HTTP requests')
            ->docs('https://laravel.com/docs/http-client#faking-responses', 'Laravel docs: Faking HTTP responses')
            ->docs('https://laravel.com/docs/container#the-make-method', 'Laravel docs: The make method')
            ->check(Check::all(Check::classExists('App\Payments\MonerisGateway'), Check::view('payments.moneris'))),

        Step::make('Bind the interface in the service container')
            ->code(<<<'PHP'
                $this->app->bind(PaymentGateway::class, fn (): PaymentGateway => match (config('payments.gateway')) {
                    'moneris' => new MonerisGateway(config('payments.moneris')),
                    'fake' => $this->app->isProduction()
                        ? throw new RuntimeException('The fake payment gateway cannot run in production.')
                        : new FakeGateway,
                    default => throw new InvalidArgumentException('Set PAYMENT_GATEWAY to moneris or fake.'),
                });
                PHP)
            ->explain(<<<'TEXT'
                You have two gateways. Now the app must pick one. The service container makes objects for Laravel. A binding tells it what to make when code asks for `PaymentGateway`.

                Put this in `register()` in `app/Providers/AppServiceProvider.php`. Controllers ask for `PaymentGateway`, and the container gives them the gateway that the config names. To add a provider, write one class and add one line here.

                The fake gateway refuses production. If the live site used it by mistake, every payment would pass without money. So the app throws an exception. An unknown name throws an exception, too, so a typo in `.env` fails at once.
                TEXT)
            ->think('The test changes `payments.gateway`, then asks the container again. It expects the other gateway. Why does `bind()` give the correct gateway each time?')
            ->hint('`bind()` runs the function each time that code asks for the interface. So it reads the config at that time.')
            ->hint('Use `match (config(\'payments.gateway\'))` with one arm for each gateway name.')
            ->hint('`$this->app->isProduction()` is `true` on the live site.')
            ->tryIt(<<<'TEXT'
                In `php artisan tinker`, run `app(App\Payments\PaymentGateway::class)`. You see `App\Payments\FakeGateway`.

                Then run `config(['payments.gateway' => 'moneris']); app(App\Payments\PaymentGateway::class)`. You see `App\Payments\MonerisGateway`.

                Then run `config(['payments.gateway' => 'stripe']); app(App\Payments\PaymentGateway::class)`. You see the error "Set PAYMENT_GATEWAY to moneris or fake."
                TEXT)
            ->commit('Bind PaymentGateway in the container')
            ->docs('https://laravel.com/docs/container#binding-interfaces-to-implementations', 'Laravel docs: Binding interfaces to implementations')
            ->docs('https://laravel.com/docs/container#automatic-injection', 'Laravel docs: Automatic injection')
            ->check(Check::fileContains('app/Providers/AppServiceProvider.php', 'PaymentGateway::class')),

        Step::make('Create the PaymentSucceeded event')
            ->run('php artisan make:event PaymentSucceeded')
            ->code('public function __construct(public Payment $payment) {}')
            ->explain(<<<'TEXT'
                When a payment passes, other features must act. An order becomes paid, and each thing in it is given to the family. The payment code could call each of them. But then the payment code would grow with every new feature.

                An event solves this problem. The payment code announces "this payment passed", and other features listen. Run `php artisan make:event PaymentSucceeded`.

                Give it a constructor with a public `Payment $payment` property. The Cart and orders feature listens for it later. Payments do not need to know about orders. Remove the broadcast code that the command made, because this event does not go to the browser.
                TEXT)
            ->think('A new feature must act when a payment passes. What must change in the payment code?')
            ->hint('Use constructor property promotion: put `public` before the parameter in the constructor.')
            ->tryIt('In `php artisan tinker`, run `(new App\Events\PaymentSucceeded(App\Models\Payment::factory()->make()))->payment->description`. You see `"Robotics Club, Fall 2026"`.')
            ->commit('Add the PaymentSucceeded event')
            ->docs('https://laravel.com/docs/events', 'Laravel docs: Events')
            ->docs('https://laravel.com/docs/events#defining-events', 'Laravel docs: Defining events')
            ->check(Check::classExists('App\Events\PaymentSucceeded')),

        Step::make('Protect each payment with a policy')
            ->run('php artisan make:policy PaymentPolicy --model=Payment')
            ->code(<<<'PHP'
                /**
                 * Anyone with the signed link can pay a guest payment.
                 * A payment that belongs to an account needs that account.
                 */
                public function view(?User $user, Payment $payment): bool
                {
                    return $payment->user_id === null || $payment->user_id === $user?->id;
                }
                PHP)
            ->explain(<<<'TEXT'
                The payment URL contains the payment ID, such as `/payments/4`. In the next step, a signed link stops people who change the number. But a link can travel: a parent forwards an email, or a browser keeps it in its history. So a payment that belongs to an account needs a second check: the right parent must be logged in.

                A policy holds this rule. Run `php artisan make:policy PaymentPolicy --model=Payment`. Laravel finds `PaymentPolicy` for `Payment` from the names.

                Laravel calls a policy for a guest only when the `$user` parameter is nullable. So write `?User $user`. A guest payment has no owner, and the signed link is enough. A payment with an owner needs that owner. Both methods of the controller use the same rule, so one `view()` method is enough. Remove the methods that you do not use.
                TEXT)
            ->think('A parent forwards the link of their own payment to a friend. What does the friend see? What does the friend see for a guest payment?')
            ->hint('`$user?->id` is `null` when `$user` is `null`. PHP does not throw an error.')
            ->hint('One line is enough: a guest payment, or a payment whose `user_id` is the ID of the user.')
            ->tryIt(<<<'TEXT'
                In `php artisan tinker`, run `App\Models\User::factory()->create()->can('view', App\Models\Payment::factory()->create())`. You see `false`, because the user does not own the payment.

                Then run `$p = App\Models\Payment::factory()->create(); $p->user->can('view', $p)`. You see `true`.

                Then run `Gate::forUser(null)->allows('view', App\Models\Payment::factory()->create(['user_id' => null]))`. You see `true`: a guest can open a guest payment.
                TEXT)
            ->commit('Add the PaymentPolicy')
            ->docs('https://laravel.com/docs/authorization#generating-policies', 'Laravel docs: Generating policies')
            ->docs('https://laravel.com/docs/authorization#writing-policies', 'Laravel docs: Writing policies')
            ->docs('https://laravel.com/docs/authorization#guest-users', 'Laravel docs: Guest users in policies')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/18', 'Laravel From Scratch, episode 18: Authorization Using Policies')
            ->check(Check::fileContains('app/Policies/PaymentPolicy.php', '?User')),

        Step::make('Create the controller and the signed routes')
            ->run('php artisan make:controller PaymentController')
            ->code(<<<'PHP'
                Route::middleware('signed')->group(function () {
                    Route::get('/payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
                    Route::post('/payments/{payment}/complete', [PaymentController::class, 'complete'])->name('payments.complete');
                });
                PHP)
            ->explain(<<<'TEXT'
                Now you connect all the parts. Run `php artisan make:controller PaymentController`.

                Put the routes in a group with the `signed` middleware, not in the `auth` group. A signed link has a `signature` in its query string. The app makes the signature from the URL and the secret `APP_KEY`. If somebody changes the payment ID, the signature does not match, and Laravel returns a 403 error. So nobody can guess the link to another payment, and a guest does not need an account.

                Add a `url()` method to `Payment` that returns `URL::signedRoute('payments.show', $this)`. Other features send the payer to `$payment->url()`, and the test uses it, too.

                Type-hint `PaymentGateway` in both methods. The container uses your binding and gives the controller the gateway. The controller never names Moneris.

                `show()` returns `$gateway->start($payment)` when the payment is pending or failed. When it is paid, `show()` shows a "Payment received" page instead. If the payment has a `return_url`, the page has a "Continue" button to it.

                `complete()` calls `confirm()` only while the payment is not paid. A payer can click twice or reload the page. Without this check, the app would announce the same payment twice. On success, set the status, gateway and `paid_at`, then dispatch `PaymentSucceeded`. On failure, set the status to failed. Then redirect to `$payment->url()`.
                TEXT)
            ->think('A payer clicks the button twice. What stops the app from sending `PaymentSucceeded` twice?')
            ->hint('Start both methods with `Gate::authorize(\'view\', $payment);`. The policy then decides, for guests and for users.')
            ->hint('Write `public function show(Payment $payment, PaymentGateway $gateway)`. Route model binding fills `$payment`, and the container fills `$gateway`.')
            ->hint('Use `forceFill([...])->save()`, because these columns are not fillable. Save `config(\'payments.gateway\')` in `gateway`. Then call `PaymentSucceeded::dispatch($payment)` and return `redirect($payment->url())`.')
            ->tryIt(<<<'TEXT'
                Run `php artisan route:list --name=payments`. You see your two routes with the `signed` middleware.

                In `php artisan tinker`, make a guest payment and print its link: `App\Models\Payment::factory()->create(['user_id' => null])->url()`. Open the link in your browser. You see the fake page with $25.00. Click "Decline test payment". You see "The last payment did not go through". Click "Approve test payment". You see "Payment received".

                Now change one character of the `signature` in the address bar, and open the page again. You see a 403 error.

                Last, make a payment for the test user: `App\Models\Payment::factory()->for(App\Models\User::firstWhere('email', 'test@example.com'))->create()->url()`. Open the link while you are logged out. You see a 403 error. Log in as `test@example.com` with the password `password`, and open it again. You see the fake page.
                TEXT)
            ->commit('Add the payment controller and routes')
            ->docs('https://laravel.com/docs/urls#signed-urls', 'Laravel docs: Signed URLs')
            ->docs('https://laravel.com/docs/urls#validating-signed-route-requests', 'Laravel docs: Validating signed route requests')
            ->docs('https://laravel.com/docs/authorization#via-the-gate-facade', 'Laravel docs: Authorizing with the Gate facade')
            ->docs('https://laravel.com/docs/routing#route-model-binding', 'Laravel docs: Route model binding')
            ->docs('https://laravel.com/docs/events#dispatching-events', 'Laravel docs: Dispatching events')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/10', 'Laravel From Scratch, episode 10: Controllers')
            ->check(Check::all(Check::route('payments.show', 'payments.complete'), Check::view('payments.paid'))),

        Step::make('Run the checks and try a real test payment')
            ->run('php artisan guide:verify payments')
            ->explain(<<<'TEXT'
                You are close. Run the acceptance test with the command below. The guide saves the result and ticks off this step.

                If the test fails, read the first failure only. It names the rule and the line. Fix that one problem, then run the test again. Later failures often come from the first one. When the test passes, run `composer ci:check`. It runs the code style check, the type check and every other test in the app.

                The test uses fake Moneris answers. If a maintainer gave you the Moneris QA values, also try the real QA system. The acceptance test cannot check this part. Set `PAYMENT_GATEWAY=moneris` and the QA values in your `.env`. Run `php artisan config:clear`, then open a payment link as in the controller step. Pay with the Moneris test Visa 4242 4242 4242 4242 and any future expiry date. Check the payment in the QA Merchant Resource Center.

                Compare the receipt fields in `MonerisGateway::confirm()` with the Moneris receipt reference. The field names come from a summary of the Moneris docs, and nobody has fully checked them yet. If a name is different, do not change the acceptance test. Tell a maintainer in your pull request. The maintainer then fixes `confirm()` and the test together.

                When you finish, set `PAYMENT_GATEWAY=fake` again. Never commit a real API token.
                TEXT)
            ->think('Moneris approves the card, but `confirm()` reads a wrong field name. What does the payer see? Why is this worse than an error?')
            ->tryIt('Run `composer ci:check`. Pint, PHPStan and all the tests pass.')
            ->commit('Fix the checks for payments')
            ->docs('https://laravel.com/docs/testing', 'Laravel docs: Testing')
            ->docs('https://developer.moneris.com/hosted-solutions/docs/setup-guide', 'Moneris docs: Checkout setup guide')
            ->check(Check::testPassed('PaymentsTest')),
    ]);
