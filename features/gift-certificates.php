<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('gift-certificates')
    ->title('Gift certificates')
    ->section(Section::Signups)
    ->level(2)
    ->needs(['cart-orders'])
    ->summary('Visitors buy gift certificates without an account. Each paid certificate gets a code. Staff redeem a code once at the front desk.')
    ->teaches(['Implementing an interface', 'Service classes', 'Named exceptions', 'Route model binding by a column', 'Conditional updates', 'Money input in dollars'])
    ->test('GiftCertificatesTest.php')
    ->intro(<<<'TEXT'
        A grandparent wants to give a child a visit to the science centre. They buy a gift certificate online for an amount, with the child's name and a short message. The child brings the code to the front desk, and staff redeem it.

        A `GiftCertificate` implements `Purchasable`, so it goes through the cart from Cart and orders, and guests can buy it. It works like a ticket: the visitor makes an unpaid certificate, the cart sells it, and its fulfiller gives it a random code when the order is paid. `GiftCertificateService` holds all three rules: `create()`, `fulfil()` and `redeem()` (see `/guide/services`). A certificate without a code is not paid, so staff cannot find it.

        Staff redeem a certificate on a "front desk" page. They type the code, see the amount and the recipient, and click "Redeem". A certificate is worth money, so it must be redeemed only once, even if two staff members click at the same moment. One database query does the check and the change together: it sets `redeemed_at` only where `redeemed_at` is still empty.

        The first version redeems the whole amount at once. If the centre wants partial redemption later, the certificate needs a balance. Keep that in mind, but do not build it now.
        TEXT)
    ->docs('https://laravel.com/docs/routing#customizing-the-default-key-name', 'Laravel docs: Customizing the key of route model binding')
    ->docs('https://laravel.com/docs/queries#update-statements', 'Laravel docs: Update statements')
    ->reflect([
        'Two staff members click "Redeem" for the same code at the same moment. Explain, step by step, why only one of them succeeds.',
        'The visitor types the amount. Why is that still "a price from the server"?',
        'The centre wants to let families spend a certificate over several visits. Which columns and rules change?',
        'Which step was the hardest? What would help the next contributor? Open an issue or a pull request in blsc-guide to improve that step.',
    ])
    ->steps([
        Step::make('Claim the feature and copy in its test')
            ->run('php artisan guide:start gift-certificates')
            ->explain(<<<'TEXT'
                Run the command below. It copies `tests/Feature/GiftCertificatesTest.php` into the app. Read the whole test first.

                The first rule is the whole purchase, as in the tickets test. The other rules are about the front desk. Look at how staff search: the test types `k7p2m9qxrt ` in small letters with a space, and the page must still find `K7P2M9QXRT`. People type codes in many ways.

                The test uses `GiftCertificate::factory()->paid()` for a certificate with a code. You write that state.
                TEXT)
            ->think('The redeem route takes the code, not the ID. Why is the code a better key for the front desk?')
            ->tryIt('Run `php artisan test tests/Feature/GiftCertificatesTest.php`. All the rules fail, because the `GiftCertificate` model does not exist yet.')
            ->commit('Add the gift certificates acceptance test')
            ->docs('https://laravel.com/docs/http-tests', 'Laravel docs: HTTP tests')
            ->check(Check::fileExists('tests/Feature/GiftCertificatesTest.php')),

        Step::make('Create the gift certificates table')
            ->run('php artisan make:model GiftCertificate -mf')
            ->explain(<<<'TEXT'
                Run the command below. Fill in the migration, then run `php artisan migrate`.

                A certificate has a `code`, an `amount_cents`, a `recipient_name`, an optional `message` and a `redeemed_at` date. The code is nullable and unique, as on tickets: an unpaid certificate has no code, and two certificates never share one.

                Keep the message short with `string('message', 500)`. The form uses the same limit.
                TEXT)
            ->think('The certificate stores `amount_cents`, and the order item stores `price_cents`. Are they always the same? When could they be different in the future?')
            ->hint('Look at the `tickets` migration. The `code` column is the same.')
            ->code(<<<'PHP'
                Schema::create('gift_certificates', function (Blueprint $table) {
                    $table->id();
                    $table->string('code')->nullable()->unique();
                    $table->unsignedInteger('amount_cents');
                    $table->string('recipient_name');
                    $table->string('message', 500)->nullable();
                    $table->dateTime('redeemed_at')->nullable();
                    $table->timestamps();
                });
                PHP)
            ->tryIt('Run `php artisan migrate`. Then, in `php artisan tinker`, run `Schema::getColumnListing(\'gift_certificates\')`. You see your columns.')
            ->commit('Add the gift certificates table')
            ->docs('https://laravel.com/docs/migrations#creating-columns', 'Laravel docs: Creating columns')
            ->check(Check::table('gift_certificates', ['code', 'amount_cents', 'recipient_name', 'message', 'redeemed_at'])),

        Step::make('Make a gift certificate purchasable')
            ->explain(<<<'TEXT'
                Open `app/Models/GiftCertificate.php`. Put `amount_cents`, `recipient_name` and `message` in `#[Fillable]`. Keep `code` and `redeemed_at` out: only `GiftCertificateService` sets them. Cast `redeemed_at` to a datetime, and add an `amount()` method that formats the money.

                Add `implements Purchasable` and the four methods. Each one only reads. `orderDescription()` names the recipient, such as "Gift certificate for Maya". `priceCents()` returns `amount_cents`. `confirmationLine()` returns the recipient, the amount and the code, so the buyer finds the code on the order page and in the email. `fulfiller()` returns `GiftCertificateService::class`. You write that service in the next step.

                In the factory, make an unpaid certificate for $50.00 with a first name from `fake()`. Add a `paid()` state with a code of 10 capital letters and numbers.
                TEXT)
            ->think('Tickets use 8 characters, and gift certificates use 10. Why could a gift certificate need a longer code?')
            ->hint('Look at `Ticket`. A gift certificate has the same four methods with other bodies.')
            ->code(<<<'PHP'
                public function confirmationLine(): string
                {
                    return 'Gift certificate for '.$this->recipient_name.', '.$this->amount().': code '.$this->code;
                }

                /**
                 * @return class-string<GiftCertificateService>
                 */
                public function fulfiller(): string
                {
                    return GiftCertificateService::class;
                }
                PHP)
            ->tryIt('In `php artisan tinker`, run `App\Models\GiftCertificate::factory()->paid()->create()->confirmationLine()`. You see the recipient, $50.00 and a code.')
            ->commit('Make gift certificates purchasable')
            ->check(Check::all(
                Check::fileContains('app/Models/GiftCertificate.php', 'implements Purchasable'),
                Check::fileContains('database/factories/GiftCertificateFactory.php', 'function paid'),
            )),

        Step::make('Give a paid certificate its code in the service')
            ->run('php artisan make:class Services/GiftCertificateService')
            ->explain(<<<'TEXT'
                Run the command below and add `implements Fulfiller` to the class. Write `fulfil(Purchasable $item): void`, as `TicketService::fulfil()` does: check that the item is a `GiftCertificate`, do nothing when it has a code, and save a new random code of 10 capital letters and numbers that no other certificate has.

                This service holds every rule about gift certificates. In the next steps, you add `create()` for the public page and `redeem()` for the front desk. A certificate is worth money, so it helps that all its rules live in one class that a reviewer can read at once.
                TEXT)
            ->think('`TicketService::fulfil()` and `GiftCertificateService::fulfil()` look almost the same. Is that a problem? When would you move the shared part into one place?')
            ->hint('Copy `TicketService::fulfil()` and change the class and the length.')
            ->code(<<<'PHP'
                public function fulfil(Purchasable $item): void
                {
                    if (! $item instanceof GiftCertificate) {
                        throw new InvalidArgumentException('GiftCertificateService fulfils gift certificates only.');
                    }

                    if ($item->code !== null) {
                        return;
                    }

                    do {
                        $code = Str::upper(Str::random(10));
                    } while (GiftCertificate::where('code', $code)->exists());

                    $item->forceFill(['code' => $code])->save();
                }
                PHP)
            ->tryIt('In `php artisan tinker`, run `$g = App\Models\GiftCertificate::factory()->create(); app(App\Services\GiftCertificateService::class)->fulfil($g); $g->confirmationLine()`. You see the recipient, $50.00 and a code.')
            ->commit('Give paid gift certificates a code')
            ->docs('/guide/services', 'BLSC guide: Controllers and services')
            ->docs('https://laravel.com/docs/strings#method-str-random', 'Laravel docs: Str::random()')
            ->check(Check::fileContains('app/Services/GiftCertificateService.php', 'implements Fulfiller')),

        Step::make('Sell gift certificates on a public page')
            ->run("php artisan make:controller GiftCertificateController\nphp artisan make:request GiftCertificateRequest")
            ->explain(<<<'TEXT'
                First, add `create(array $data): GiftCertificate` to `GiftCertificateService`. It makes an unpaid certificate from the validated data. It is one line today.

                Then add two public routes: `GET /gift-certificates` named `gift-certificates.create` and `POST /gift-certificates` named `gift-certificates.store`.

                The form asks for the amount in dollars, the recipient's name and an optional message. `GiftCertificateRequest` validates the amount with `min:10`, `max:500` and `decimal:0,2`. Anyone can buy, so `authorize()` returns `true`. Add `certificateData()`, which turns `amount` into `amount_cents`, as `ProgramSessionRequest::sessionData()` turns `price` into `price_cents`.

                `store()` calls `$certificates->create($request->certificateData())`, adds the certificate to the cart, and redirects to the cart.

                This is the one item where the visitor chooses the price. That is still safe. The server checks the limits and saves the amount on the certificate. The cart then asks the certificate for its price, as it asks every item.

                Add a "Gift certificates" link to the Explore menu in `resources/views/layouts/site.blade.php`.
                TEXT)
            ->think('A visitor changes the form and sends an amount of `0.001`. Which rule refuses it?')
            ->hint('`(int) round(((float) $this->validated(\'amount\')) * 100)` turns `50.5` into `5050`. Rounding protects you from `0.1 + 0.2` problems.')
            ->hint('`<x-admin.textarea-field>` works on public pages, too. It is a plain textarea with a label and an error.')
            ->code(<<<'PHP'
                public function rules(): array
                {
                    return [
                        'amount' => ['required', 'numeric', 'min:10', 'max:500', 'decimal:0,2'],
                        'recipient_name' => ['required', 'string', 'max:255'],
                        'message' => ['nullable', 'string', 'max:500'],
                    ];
                }
                PHP)
            ->tryIt('Open http://localhost:8000/gift-certificates while you are logged out. Buy a certificate for $25 for "Maya". Check out with any email and approve the test payment. Click "Continue". The order page shows "Gift certificate for Maya, $25.00: code" and the code. Note the code for a later step.')
            ->commit('Sell gift certificates on the public site')
            ->docs('https://laravel.com/docs/validation#rule-decimal', 'Laravel docs: The decimal rule')
            ->docs('https://laravel.com/docs/validation#form-request-validation', 'Laravel docs: Form request validation')
            ->check(Check::all(
                Check::fileContains('app/Services/GiftCertificateService.php', 'function create'),
                Check::route('gift-certificates.create', 'gift-certificates.store'),
                Check::view('gift-certificates.create'),
            )),

        Step::make('Redeem a certificate once in the service')
            ->run('php artisan make:exception GiftCertificateAlreadyRedeemed')
            ->explain(<<<'TEXT'
                Add `redeem(GiftCertificate $certificate): void` to `GiftCertificateService`. A certificate is worth money, so it must be redeemed only once, even if two staff members click at the same moment.

                So the method must not check first and change later. Between the check and the change, another click can redeem the same certificate. Do both in one query: update `redeemed_at` only `whereNull('redeemed_at')`. The query returns the number of changed rows. If it is `0`, the certificate was already redeemed: throw `GiftCertificateAlreadyRedeemed` with the message "This gift certificate was already redeemed." This is the same pattern as `PaymentService::complete()` and `OrderService::markAsPaid()`.

                After the query, call `$certificate->refresh()`, so the caller sees the new `redeemed_at`.
                TEXT)
            ->think('Why is `if ($certificate->redeemed_at === null) { $certificate->update(...); }` not safe enough?')
            ->hint('`GiftCertificate::whereKey($certificate->id)->whereNull(\'redeemed_at\')->update([\'redeemed_at\' => now()])` returns `1` or `0`.')
            ->code(<<<'PHP'
                /**
                 * Redeem a paid certificate once. The database checks and changes the row in one query.
                 *
                 * @throws GiftCertificateAlreadyRedeemed when another click was first
                 */
                public function redeem(GiftCertificate $certificate): void
                {
                    $redeemed = GiftCertificate::whereKey($certificate->id)
                        ->whereNull('redeemed_at')
                        ->update(['redeemed_at' => now()]);

                    if ($redeemed === 0) {
                        throw new GiftCertificateAlreadyRedeemed;
                    }

                    $certificate->refresh();
                }
                PHP)
            ->tryIt('In `php artisan tinker`, run `$g = App\Models\GiftCertificate::factory()->paid()->create(); $s = app(App\Services\GiftCertificateService::class); $s->redeem($g); $g->redeemed_at`. You see the date. Run `$s->redeem($g)` again. You see `GiftCertificateAlreadyRedeemed` and its message.')
            ->commit('Redeem gift certificates once')
            ->docs('https://laravel.com/docs/queries#update-statements', 'Laravel docs: Update statements')
            ->check(Check::all(
                Check::fileContains('app/Services/GiftCertificateService.php', "whereNull('redeemed_at')"),
                Check::fileExists('app/Exceptions/GiftCertificateAlreadyRedeemed.php'),
            )),

        Step::make('Find and redeem a code at the front desk')
            ->run('php artisan make:controller Admin/GiftCertificateController')
            ->explain(<<<'TEXT'
                Add two routes in the staff group: `GET gift-certificates` named `gift-certificates.index`, and `POST gift-certificates/{giftCertificate:code}/redeem` named `gift-certificates.redeem`. With the staff group, the names start with `admin.`.

                `{giftCertificate:code}` tells route model binding to find the certificate by its `code` column, not by its ID. An unknown code gives a 404 error. An unpaid certificate has no code, so nobody can redeem it.

                `index()` reads `code` from the query string. Trim it and make it upper case, then look for the certificate. This is a read for one page, so it can stay in the controller. The view shows a search form, and then one of three results: "No gift certificate has this code.", the certificate with a "Redeem" button, or "Redeemed on" and the date.

                `redeem()` calls `$certificates->redeem($giftCertificate)`. When it throws `GiftCertificateAlreadyRedeemed`, send staff back to the desk with a `code` error. Otherwise, show "Gift certificate redeemed." Add a "Gift certificates" link to the staff menu.
                TEXT)
            ->think('The redeem method in the controller has no `if` about `redeemed_at`. Where did the rule go? What does a second front door, such as a till app, need to do to obey it?')
            ->hint('`$request->string(\'code\')` returns a `Stringable`. `->trim()->upper()->toString()` cleans what staff typed.')
            ->hint('Search only when the code is not empty. An unpaid certificate has no code, so an empty search must find nothing.')
            ->code(<<<'PHP'
                public function redeem(GiftCertificate $giftCertificate, GiftCertificateService $certificates): RedirectResponse
                {
                    $back = to_route('admin.gift-certificates.index', ['code' => $giftCertificate->code]);

                    try {
                        $certificates->redeem($giftCertificate);
                    } catch (GiftCertificateAlreadyRedeemed $exception) {
                        return $back->withErrors(['code' => $exception->getMessage()]);
                    }

                    return $back->with('status', 'Gift certificate redeemed.');
                }
                PHP)
            ->tryIt('Log in as `staff@example.com` and click "Gift certificates". Type the code from the public page step in small letters. You see Maya and $25.00. Click "Redeem". You see "Gift certificate redeemed." and "Redeemed on". Go back in the browser and click "Redeem" again. You see "This gift certificate was already redeemed."')
            ->commit('Add the gift certificate front desk')
            ->docs('https://laravel.com/docs/routing#customizing-the-default-key-name', 'Laravel docs: Customizing the key of route model binding')
            ->docs('https://laravel.com/docs/requests#retrieving-input', 'Laravel docs: Retrieving input')
            ->check(Check::all(
                Check::route('admin.gift-certificates.index', 'admin.gift-certificates.redeem'),
                Check::view('admin.gift-certificates.index'),
                Check::fileContains('app/Http/Controllers/Admin/GiftCertificateController.php', 'GiftCertificateService'),
            )),

        Step::make('Run the checks and open your pull request')
            ->run('php artisan guide:verify gift-certificates')
            ->explain(<<<'TEXT'
                Run the acceptance test with `guide:verify`. Then run `composer ci:check`. It runs Pint, PHPStan and every test.

                In your pull request, describe the front desk flow for the staff who will use it: where they type the code, and what each result means.
                TEXT)
            ->think('A visitor loses their code. How can staff find the certificate today? What would you add, and which data would it need?')
            ->tryIt('Run `composer ci:check`. Pint, PHPStan and all the tests pass.')
            ->commit('Fix the checks for gift certificates')
            ->check(Check::testPassed('GiftCertificatesTest')),
    ]);
