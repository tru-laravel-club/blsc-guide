<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('tickets')
    ->title('Tickets')
    ->section(Section::Signups)
    ->level(2)
    ->needs(['cart-orders'])
    ->summary('Visitors buy admission and event tickets without an account. Staff manage the ticket types. Each paid ticket gets a unique code.')
    ->teaches(['Implementing an interface', 'Factory states', 'Local scopes', 'Validation exceptions', 'Random codes'])
    ->test('TicketsTest.php')
    ->intro(<<<'TEXT'
        The centre sells two kinds of ticket. General admission lets a visitor in on any open day. An event ticket, such as BIG Night Out, is for one date. Visitors do not want an account to buy a ticket, so tickets use the guest checkout from Cart and orders.

        A `TicketType` is what staff sell: a name, a price, an optional event (a `ProgramSession`) and an optional capacity. Staff manage the types on an admin page. A `Ticket` is one ticket for one person. It implements `Purchasable`, so it goes in the cart with no change to the cart.

        This is the path of a purchase. The visitor chooses a type and a quantity. The app makes one unpaid `Ticket` for each person and adds each one to the cart. At checkout, the order and the payment work as before. When the payment passes, Cart and orders calls `fulfil()` on each ticket, and the ticket gets its random code. The order page and the confirmation email show the codes through `confirmationLine()`.

        A ticket without a code is not paid. That one rule does a lot of work: an unpaid ticket in an old cart does not count against the capacity, and staff at the door cannot find it.
        TEXT)
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/16', 'Laravel From Scratch, episode 16: Eloquent Relationships')
    ->docs('https://laravel.com/docs/eloquent#local-scopes', 'Laravel docs: Local scopes')
    ->docs('https://laravel.com/docs/eloquent-factories#factory-states', 'Laravel docs: Factory states')
    ->reflect([
        'BIG Night Out and general admission both sell tickets. Which fields make the difference? Does any code check the name of a ticket type?',
        'Why does a ticket get its code in `fulfil()`, and not when the visitor adds it to the cart?',
        'Two visitors buy the last ticket at the same moment. What happens with your code? What would you change?',
        'Which step was the hardest? What would help the next contributor? Open an issue or a pull request in blsc-guide to improve that step.',
    ])
    ->steps([
        Step::make('Claim the feature and copy in its test')
            ->run('php artisan guide:start tickets')
            ->explain(<<<'TEXT'
                Run the command below. It copies `tests/Feature/TicketsTest.php` into the app. Read the whole test first.

                The second rule is the whole purchase: a guest adds two tickets, checks out, pays with the fake gateway, and opens the order page. Look at what it checks between the steps. Right after "Add to cart", no ticket has a code. After the payment, both tickets have a code, and the codes are different.

                The test uses two factory states that you write: `TicketType::factory()->forSession($session)` and `Ticket::factory()->paid()`.
                TEXT)
            ->think('The rule "a visitor cannot buy more tickets than are left" makes one paid ticket and one unpaid ticket. Why does it make the unpaid one?')
            ->tryIt('Run `php artisan test tests/Feature/TicketsTest.php`. All the rules fail, because the `TicketType` model does not exist yet.')
            ->commit('Add the tickets acceptance test')
            ->docs('https://laravel.com/docs/http-tests', 'Laravel docs: HTTP tests')
            ->check(Check::fileExists('tests/Feature/TicketsTest.php')),

        Step::make('Create the ticket types and tickets tables')
            ->run("php artisan make:model TicketType -mf\nphp artisan make:model Ticket -mf")
            ->explain(<<<'TEXT'
                Run the two commands below. Fill in both migrations, then run `php artisan migrate`.

                A ticket type has a `name`, a `price_cents` and two nullable columns. `program_session_id` links an event ticket to its date. Admission tickets have none. `capacity` is the number of tickets for sale. `null` means no limit.

                A ticket has a `ticket_type_id`, a `code` and a `used_at` date. The code is nullable, because an unpaid ticket has no code yet. Make it `unique()`: two tickets must never share a code. A database allows many `null` values in a unique column, so the unpaid tickets do not clash. `used_at` is for the Ticket check-in feature.

                The order item already points to the ticket through its `purchasable` link. So the ticket does not need its own `order_item_id` column.
                TEXT)
            ->think('Staff delete an event session that has ticket types. What must happen? Which `onDelete` rule gives that result?')
            ->hint('`restrictOnDelete()` on `program_session_id` stops a session with tickets from being deleted.')
            ->hint('`cascadeOnDelete()` on `ticket_type_id` deletes the tickets with their type. The controller later refuses a type with paid tickets.')
            ->code(<<<'PHP'
                Schema::create('ticket_types', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('program_session_id')->nullable()->constrained()->restrictOnDelete();
                    $table->string('name');
                    $table->unsignedInteger('price_cents');
                    $table->unsignedInteger('capacity')->nullable();
                    $table->timestamps();
                });

                Schema::create('tickets', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('ticket_type_id')->constrained()->cascadeOnDelete();
                    $table->string('code')->nullable()->unique();
                    $table->dateTime('used_at')->nullable();
                    $table->timestamps();
                });
                PHP)
            ->tryIt('Run `php artisan migrate`. Then, in `php artisan tinker`, run `Schema::getColumnListing(\'tickets\')`. You see `ticket_type_id`, `code` and `used_at`.')
            ->commit('Add the ticket types and tickets tables')
            ->docs('https://laravel.com/docs/migrations#foreign-key-constraints', 'Laravel docs: Foreign key constraints')
            ->check(Check::all(
                Check::table('ticket_types', ['program_session_id', 'name', 'price_cents', 'capacity']),
                Check::table('tickets', ['ticket_type_id', 'code', 'used_at']),
            )),

        Step::make('Fill in the TicketType model and its factory')
            ->explain(<<<'TEXT'
                Open `app/Models/TicketType.php`. Put the four columns in `#[Fillable]`. Add `session(): BelongsTo` to `ProgramSession`. The column is `program_session_id`, so pass it as the second argument. Add `tickets(): HasMany`.

                Add three methods:

                1. An `onSale` scope. A type is on sale when it has no session, or its session starts today or later.
                2. `remaining(): ?int`. It returns `null` when there is no capacity. Otherwise it returns the capacity minus the paid tickets, the tickets with a code. Never return less than `0`.
                3. `displayName(): string`. It returns the name, and for an event ticket also the session label and dates. The cart and the order use it.

                In the factory, make a "General admission" type for $12.00 with no capacity. Add a `forSession(ProgramSession $session)` state that sets `program_session_id`.
                TEXT)
            ->think('Why does `remaining()` count only the tickets with a code? What would go wrong if it counted every ticket?')
            ->hint('`orWhereHas(\'session\', fn (Builder $query) => $query->whereDate(\'starts_on\', \'>=\', today()))` checks the session of each type.')
            ->hint('Wrap the two conditions in one `where(fn ...)`, so a later `where` in the query does not mix with the `or`.')
            ->code(<<<'PHP'
                /**
                 * The number of tickets left, or null when there is no limit. Only paid tickets count.
                 */
                public function remaining(): ?int
                {
                    if ($this->capacity === null) {
                        return null;
                    }

                    return max(0, $this->capacity - $this->tickets()->whereNotNull('code')->count());
                }
                PHP)
            ->tryIt('In `php artisan tinker`, run `App\Models\TicketType::factory()->create([\'capacity\' => 50])->remaining()`. You see `50`.')
            ->commit('Fill in the TicketType model')
            ->docs('https://laravel.com/docs/eloquent#local-scopes', 'Laravel docs: Local scopes')
            ->docs('https://laravel.com/docs/eloquent-relationships#querying-relationship-existence', 'Laravel docs: Querying relationship existence')
            ->docs('https://laravel.com/docs/eloquent-factories#factory-states', 'Laravel docs: Factory states')
            ->check(Check::all(
                Check::fileContains('app/Models/TicketType.php', 'function remaining'),
                Check::fileContains('database/factories/TicketTypeFactory.php', 'function forSession'),
            )),

        Step::make('Make a ticket purchasable')
            ->explain(<<<'TEXT'
                Open `app/Models/Ticket.php`. Add `implements Purchasable`. PHP now tells you which four methods are missing.

                Add `ticketType(): BelongsTo`. Name it after the model, so the factory can use `Ticket::factory()->for($type)`. Cast `used_at` to a datetime.

                Now write the four methods:

                1. `orderDescription()` returns `$this->ticketType->displayName()`.
                2. `priceCents()` returns the price of the type. Every buyer pays the same, so the method does not use `$user`.
                3. `fulfil()` gives the ticket a random code of 8 capital letters and numbers. If the ticket already has a code, it does nothing. Try a new code until no other ticket has it.
                4. `confirmationLine()` returns the name and the code, such as "General admission: ticket code K7P2M9QX".

                In the factory, a ticket belongs to a new `TicketType`, without a code. Add a `paid()` state that sets a random code.
                TEXT)
            ->think('`fulfil()` checks that no other ticket has the code. The column is also unique. Why both?')
            ->hint('`Str::upper(Str::random(8))` makes a code such as `K7P2M9QX`.')
            ->hint('`code` is not fillable. Save it with `$this->forceFill([\'code\' => $code])->save();`.')
            ->code(<<<'PHP'
                public function fulfil(): void
                {
                    if ($this->code !== null) {
                        return;
                    }

                    do {
                        $code = Str::upper(Str::random(8));
                    } while (self::where('code', $code)->exists());

                    $this->forceFill(['code' => $code])->save();
                }
                PHP)
            ->tryIt('In `php artisan tinker`, run `$t = App\Models\Ticket::factory()->create(); $t->fulfil(); $t->confirmationLine()`. You see "General admission: ticket code" and a code.')
            ->commit('Make tickets purchasable')
            ->docs('https://laravel.com/docs/strings#method-str-random', 'Laravel docs: Str::random()')
            ->docs('https://laravel.com/docs/eloquent-factories#belongs-to-relationships', 'Laravel docs: Belongs to relationships in factories')
            ->check(Check::all(
                Check::fileContains('app/Models/Ticket.php', 'implements Purchasable'),
                Check::fileContains('database/factories/TicketFactory.php', 'function paid'),
            )),

        Step::make('Sell tickets on a public page')
            ->run('php artisan make:controller TicketController')
            ->explain(<<<'TEXT'
                Add two public routes: `GET /tickets` named `tickets.index` and `POST /tickets` named `tickets.store`. They are not in the `auth` group.

                `index()` lists the types that are on sale. The view `resources/views/tickets/index.blade.php` shows each type with its price and a form: a hidden `ticket_type_id`, a `quantity` from 1 to 10, and an "Add to cart" button. When `remaining()` is `0`, show "Sold out" and no form.

                `store()` validates the two fields. Then it finds the type with the `onSale()` scope, so an old event cannot be bought with a changed form. Then it checks the quantity against `remaining()`. If a check fails, throw a `ValidationException`. The visitor goes back to the page with an error, as for any other validation.

                Last, make one ticket for each person with `$type->tickets()->create()`, add each one to the cart, and redirect to `cart.show`. The form never sends a price. The ticket asks its type.

                Add a "Tickets" link to the Explore menu in `resources/views/layouts/site.blade.php`.
                TEXT)
            ->think('The form has a hidden `ticket_type_id`. A visitor changes it to the ID of last year\'s gala. Which line stops them?')
            ->hint('`ValidationException::withMessages([\'quantity\' => \'This ticket is sold out.\'])` sends the visitor back with an error on `quantity`.')
            ->hint('`TicketType::onSale()->whereKey($validated[\'ticket_type_id\'])->first()` returns the type, or `null` when it is not on sale.')
            ->code(<<<'PHP'
                $remaining = $type->remaining();

                if ($remaining !== null && $validated['quantity'] > $remaining) {
                    throw ValidationException::withMessages([
                        'quantity' => $remaining === 0 ? 'This ticket is sold out.' : "Only {$remaining} tickets are left.",
                    ]);
                }

                for ($i = 0; $i < $validated['quantity']; $i++) {
                    $cart->add($type->tickets()->create());
                }
                PHP)
            ->tryIt(<<<'TEXT'
                In `php artisan tinker`, run `App\Models\TicketType::create(['name' => 'General admission', 'price_cents' => 1200, 'capacity' => 50])`.

                Log out, run `php artisan serve` and open http://localhost:8000/tickets. You see "General admission", $12.00 and "50 left". Add two tickets. The cart shows two lines and $24.00. Check out with any email and approve the test payment. Click "Continue". The order page shows two different ticket codes.
                TEXT)
            ->commit('Sell tickets on the public site')
            ->docs('https://laravel.com/docs/validation#manually-creating-validators', 'Laravel docs: Manually creating validators')
            ->docs('https://laravel.com/docs/validation#rule-exists', 'Laravel docs: The exists rule')
            ->check(Check::all(Check::route('tickets.index', 'tickets.store'), Check::view('tickets.index'))),

        Step::make('Let staff manage ticket types')
            ->run("php artisan make:controller Admin/TicketTypeController --resource --model=TicketType\nphp artisan make:request TicketTypeRequest")
            ->explain(<<<'TEXT'
                Staff create a type for each event, so they need admin pages. Copy the pattern of the category admin: a resource controller without `show`, a form request and four views in `resources/views/admin/ticket-types`.

                Staff type the price in dollars, as on the session form. Copy `sessionData()` from `ProgramSessionRequest` into a `ticketTypeData()` method that turns `price` into `price_cents`. The event is a select of the upcoming sessions, with an empty choice for admission tickets. Check it with `Rule::exists('program_sessions', 'id')`.

                The list shows each type with its event, its price and "sold of capacity". Count only paid tickets: `withCount(['tickets as sold_count' => fn ($query) => $query->whereNotNull('code')])`.

                `destroy()` refuses a type that has paid tickets, with a `ticket_type` error. A family paid for those tickets, and the order must still show them. Add a "Ticket types" link to the staff menu.
                TEXT)
            ->think('Staff want to stop selling a ticket type but keep its sold tickets. What can they do today? What small change would help them?')
            ->hint('Look at `Admin\CategoryController::destroy()`. It refuses a category with programs in the same way.')
            ->hint('The menu link uses `request()->routeIs(\'admin.ticket-types.*\')` and the `<x-lucide-ticket>` icon.')
            ->tryIt('Log in as `staff@example.com` and click "Ticket types". Create "BIG Night Out" for an upcoming session, with a price of 35.50 and 80 tickets. Open http://localhost:8000/tickets. You see the new type with its date and "80 left".')
            ->commit('Add the ticket type admin')
            ->docs('https://laravel.com/docs/controllers#resource-controllers', 'Laravel docs: Resource controllers')
            ->docs('https://laravel.com/docs/eloquent-relationships#counting-related-models', 'Laravel docs: Counting related models')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/12', 'Laravel From Scratch, episode 12: Form Request Classes')
            ->check(Check::all(Check::route('admin.ticket-types.index', 'admin.ticket-types.store', 'admin.ticket-types.destroy'), Check::view('admin.ticket-types.index'))),

        Step::make('Run the checks and open your pull request')
            ->run('php artisan guide:verify tickets')
            ->explain(<<<'TEXT'
                Run the acceptance test with `guide:verify`. Then run `composer ci:check`. It runs Pint, PHPStan and every test.

                PHPStan can complain about `find()` with a value from the request, because `find()` also accepts an array of IDs. `whereKey(...)->first()` always returns one model or `null`.
                TEXT)
            ->think('An unpaid ticket stays in the database when a visitor leaves without paying. Is that a problem? How could the app clean up old unpaid tickets?')
            ->tryIt('Run `composer ci:check`. Pint, PHPStan and all the tests pass.')
            ->commit('Fix the checks for tickets')
            ->check(Check::testPassed('TicketsTest')),
    ]);
