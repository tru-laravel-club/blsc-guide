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
    ->teaches(['Implementing an interface', 'Service classes', 'Named exceptions', 'Factory states', 'Local scopes', 'Form requests', 'Random codes'])
    ->test('TicketsTest.php')
    ->intro(<<<'TEXT'
        The centre sells two kinds of ticket. General admission lets a visitor in on any open day. An event ticket, such as BIG Night Out, is for one date. Visitors do not want an account to buy a ticket, so tickets use the guest checkout from Cart and orders.

        A `TicketType` is what staff sell: a name, a price, an optional event (a `ProgramSession`) and an optional capacity. Staff manage the types on an admin page. A `Ticket` is one ticket for one person. It implements `Purchasable`, so it goes in the cart with no change to the cart. `TicketService` does the work on tickets, and `TicketTypeService` saves the types (see `/guide/services`).

        This is the path of a purchase. The visitor chooses a type and a quantity. `TicketService` makes one unpaid `Ticket` for each person, and the controller adds each one to the cart. At checkout, the order and the payment work as before. When the payment passes, `OrderService` asks the fulfiller of each ticket, `TicketService`, to fulfil it, and the ticket gets its random code. The order page and the confirmation email show the codes through `confirmationLine()`.

        A ticket without a code is not paid. That one rule does a lot of work: an unpaid ticket in an old cart does not count against the capacity, and staff at the door cannot find it.
        TEXT)
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/16', 'Laravel From Scratch, episode 16: Eloquent Relationships')
    ->docs('https://laravel.com/docs/eloquent#local-scopes', 'Laravel docs: Local scopes')
    ->docs('https://laravel.com/docs/eloquent-factories#factory-states', 'Laravel docs: Factory states')
    ->reflect([
        'BIG Night Out and general admission both sell tickets. Which fields make the difference? Does any code check the name of a ticket type?',
        'Why does a ticket get its code in `TicketService::fulfil()`, and not when the visitor adds it to the cart?',
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

                Now write the four methods. Each one only reads:

                1. `orderDescription()` returns `$this->ticketType->displayName()`.
                2. `priceCents()` returns the price of the type. Every buyer pays the same, so the method does not use `$user`.
                3. `confirmationLine()` returns the name and the code, such as "General admission: ticket code K7P2M9QX".
                4. `fulfiller()` returns `TicketService::class`. You write that service in the next step. PHP does not load a class for `::class`, so the line works before the file exists.

                Keep `code` out of `#[Fillable]`. Only `TicketService` gives a ticket its code.

                In the factory, a ticket belongs to a new `TicketType`, without a code. Add a `paid()` state that sets a random code of 8 capital letters and numbers.
                TEXT)
            ->think('The ticket gets its code after the payment. Why does the model only name `TicketService`, and not make the code itself?')
            ->hint('`Str::upper(Str::random(8))` makes a code such as `K7P2M9QX`. The `paid()` state can use it, too.')
            ->hint('Import `App\Services\TicketService` at the top of the model, so `TicketService::class` names the right class.')
            ->code(<<<'PHP'
                public function confirmationLine(): string
                {
                    return $this->ticketType->name.': ticket code '.$this->code;
                }

                /**
                 * @return class-string<TicketService>
                 */
                public function fulfiller(): string
                {
                    return TicketService::class;
                }
                PHP)
            ->tryIt('In `php artisan tinker`, run `App\Models\Ticket::factory()->paid()->create()->confirmationLine()`. You see "General admission: ticket code" and a code.')
            ->commit('Make tickets purchasable')
            ->docs('https://laravel.com/docs/strings#method-str-random', 'Laravel docs: Str::random()')
            ->docs('https://laravel.com/docs/eloquent-factories#belongs-to-relationships', 'Laravel docs: Belongs to relationships in factories')
            ->check(Check::all(
                Check::fileContains('app/Models/Ticket.php', 'implements Purchasable'),
                Check::fileContains('database/factories/TicketFactory.php', 'function paid'),
            )),

        Step::make('Give a paid ticket its code in the ticket service')
            ->run('php artisan make:class Services/TicketService')
            ->explain(<<<'TEXT'
                Now write the work that happens when the money arrives. Run the command below. It makes `app/Services/TicketService.php`. Add `implements Fulfiller` to the class.

                `fulfil(Purchasable $item): void` gives the ticket a random code of 8 capital letters and numbers. It holds three rules:

                1. The order gives the method a `Purchasable`, because the interface says so. Check that it is a `Ticket`. If it is not, throw an `InvalidArgumentException`: a wrong fulfiller is a bug, and the transaction of the order then saves nothing.
                2. If the ticket already has a code, do nothing. A second call must not change the code that the family has.
                3. Try a new code until no other ticket has it. Then save it.

                Why a service, and not a method on the model? Giving a code changes data, and the rule of the app is that a service does that work (`/guide/services`). The ticket check-in feature adds a second method here, `checkIn()`. All the rules about tickets then live in one class.
                TEXT)
            ->think('Why does the code live in `TicketService` and not in `OrderService`? What would `OrderService` need to know about tickets otherwise?')
            ->hint('The parameter type must stay `Purchasable`. PHP does not let a class narrow the parameter type of an interface method. So check the type inside the method.')
            ->hint('`code` is not fillable. Save it with `$item->forceFill([\'code\' => $code])->save();`.')
            ->hint('`fulfil()` checks that no other ticket has the code. The column is also unique. The check finds a free code, and the unique index is the last guard if two payments pick the same code at the same moment.')
            ->code(<<<'PHP'
                class TicketService implements Fulfiller
                {
                    /**
                     * Give a paid ticket its unique code. A ticket that has a code keeps it.
                     */
                    public function fulfil(Purchasable $item): void
                    {
                        if (! $item instanceof Ticket) {
                            throw new InvalidArgumentException('TicketService fulfils tickets only.');
                        }

                        if ($item->code !== null) {
                            return;
                        }

                        do {
                            $code = Str::upper(Str::random(8));
                        } while (Ticket::where('code', $code)->exists());

                        $item->forceFill(['code' => $code])->save();
                    }
                }
                PHP)
            ->tryIt('In `php artisan tinker`, run `$t = App\Models\Ticket::factory()->create(); app(App\Services\TicketService::class)->fulfil($t); $t->code`. You see a code of 8 characters. Run the `fulfil()` line again. The code stays the same.')
            ->commit('Give paid tickets a code in the ticket service')
            ->docs('/guide/services', 'BLSC guide: Controllers and services')
            ->docs('https://laravel.com/docs/strings#method-str-random', 'Laravel docs: Str::random()')
            ->check(Check::fileContains('app/Services/TicketService.php', 'implements Fulfiller')),

        Step::make('Make unpaid tickets in the service')
            ->run("php artisan make:exception TicketNotOnSale\nphp artisan make:exception NotEnoughTickets")
            ->explain(<<<'TEXT'
                A visitor chooses a type and a quantity. The app makes one unpaid ticket for each person. Two business rules can refuse the request:

                1. The type must be on sale. A visitor can change the hidden `ticket_type_id` in the form to last year's gala. Throw `TicketNotOnSale` with the message "This ticket is not on sale."
                2. The quantity must fit in `remaining()`. Throw `NotEnoughTickets`. Its message depends on the number: "This ticket is sold out." when nothing is left, otherwise "Only 3 tickets are left." Give the exception a constructor that takes the number and builds the message.

                Add `create(TicketType $type, int $quantity): Collection` to `TicketService`. Check both rules, then make the tickets with `$type->tickets()->create()` and return them. The service does not know about the cart. The controller adds the tickets to the cart in the next step.

                These are business rules, not form rules. A staff page that sells at the door must obey them, too. So they live in the service, and each front door turns the exception into a message.
                TEXT)
            ->think('The form already validates that `ticket_type_id` exists. Why is "on sale" a rule for the service, and not a validation rule in the form?')
            ->hint('`TicketType::onSale()->whereKey($type->id)->exists()` uses the scope that you wrote, so the rule "on sale" lives in one place.')
            ->hint('Look at `app/Exceptions/CategoryHasPrograms.php`. Its constructor sets the message. `NotEnoughTickets` does the same with a parameter: `public function __construct(int $remaining)`.')
            ->code(<<<'PHP'
                /**
                 * Make unpaid tickets of this type. They get their codes when the order is paid.
                 *
                 * @return Collection<int, Ticket>
                 *
                 * @throws TicketNotOnSale when the type is not on sale
                 * @throws NotEnoughTickets when fewer tickets are left than the visitor wants
                 */
                public function create(TicketType $type, int $quantity): Collection
                {
                    if (! TicketType::onSale()->whereKey($type->id)->exists()) {
                        throw new TicketNotOnSale;
                    }

                    $remaining = $type->remaining();

                    if ($remaining !== null && $quantity > $remaining) {
                        throw new NotEnoughTickets($remaining);
                    }

                    return Collection::times($quantity, fn (): Ticket => $type->tickets()->create());
                }
                PHP)
            ->tryIt('In `php artisan tinker`, run `$type = App\Models\TicketType::factory()->create([\'capacity\' => 2]); app(App\Services\TicketService::class)->create($type, 3)`. You see `NotEnoughTickets` with "Only 2 tickets are left." Then run it with `2`. You see two tickets without a code.')
            ->commit('Make unpaid tickets in the ticket service')
            ->docs('/guide/services', 'BLSC guide: Controllers and services')
            ->check(Check::all(
                Check::fileContains('app/Services/TicketService.php', 'function create'),
                Check::fileExists('app/Exceptions/TicketNotOnSale.php'),
                Check::fileExists('app/Exceptions/NotEnoughTickets.php'),
            )),

        Step::make('Sell tickets on a public page')
            ->run("php artisan make:controller TicketController\nphp artisan make:request TicketRequest")
            ->explain(<<<'TEXT'
                Add two public routes: `GET /tickets` named `tickets.index` and `POST /tickets` named `tickets.store`. They are not in the `auth` group.

                `index()` lists the types that are on sale. The view `resources/views/tickets/index.blade.php` shows each type with its price and a form: a hidden `ticket_type_id`, a `quantity` from 1 to 10, and an "Add to cart" button. When `remaining()` is `0`, show "Sold out" and no form. Otherwise, when the type has a capacity, show how many are left, such as "50 left".

                `TicketRequest` validates the two fields. Anyone can buy, so `authorize()` returns `true`.

                `store()` does only HTTP work. It loads the type, calls `$tickets->create($type, $quantity)`, adds each ticket to the cart with `CartService`, and redirects to `cart.show`. When the service throws, send the visitor back with the message of the exception: `TicketNotOnSale` on `ticket_type_id`, and `NotEnoughTickets` on `quantity`. The form never sends a price. The ticket asks its type.

                Add a "Tickets" link to the Explore menu in `resources/views/layouts/site.blade.php`.
                TEXT)
            ->think('The form has a hidden `ticket_type_id`. A visitor changes it to the ID of last year\'s gala. Which line stops them, and which line shows the message?')
            ->hint('`back()->withErrors([\'quantity\' => $exception->getMessage()])->withInput()` sends the visitor back with the error and keeps the form values.')
            ->hint('`TicketType::findOrFail($request->integer(\'ticket_type_id\'))` loads the type. The `exists` rule in the request already checked that it is there.')
            ->code(<<<'PHP'
                public function store(TicketRequest $request, TicketService $tickets, CartService $cart): RedirectResponse
                {
                    $type = TicketType::findOrFail($request->integer('ticket_type_id'));

                    try {
                        $bought = $tickets->create($type, $request->integer('quantity'));
                    } catch (TicketNotOnSale $exception) {
                        return back()->withErrors(['ticket_type_id' => $exception->getMessage()]);
                    } catch (NotEnoughTickets $exception) {
                        return back()->withErrors(['quantity' => $exception->getMessage()])->withInput();
                    }

                    $bought->each(fn (Ticket $ticket) => $cart->add($ticket));

                    return to_route('cart.show');
                }
                PHP)
            ->tryIt(<<<'TEXT'
                In `php artisan tinker`, run `App\Models\TicketType::create(['name' => 'General admission', 'price_cents' => 1200, 'capacity' => 50])`.

                Log out, run `php artisan serve` and open http://localhost:8000/tickets. You see "General admission", $12.00 and "50 left". Add two tickets. The cart shows two lines and $24.00. Check out with any email and approve the test payment. Click "Continue". The order page shows two different ticket codes.
                TEXT)
            ->commit('Sell tickets on the public site')
            ->docs('https://laravel.com/docs/validation#form-request-validation', 'Laravel docs: Form request validation')
            ->docs('https://laravel.com/docs/validation#rule-exists', 'Laravel docs: The exists rule')
            ->docs('https://laravel.com/docs/responses#redirecting-with-flashed-session-data', 'Laravel docs: Redirecting with flashed session data')
            ->check(Check::all(
                Check::route('tickets.index', 'tickets.store'),
                Check::view('tickets.index'),
                Check::fileContains('app/Http/Controllers/TicketController.php', 'TicketService'),
            )),

        Step::make('Manage ticket types in a service')
            ->run("php artisan make:class Services/TicketTypeService\nphp artisan make:exception TicketTypeHasSales")
            ->explain(<<<'TEXT'
                Staff create a type for each event, so they need admin pages. The writes go in `TicketTypeService`, as the category writes go in `CategoryService`. Open `app/Services/CategoryService.php` and copy its shape.

                Write three methods: `create(array $data): TicketType`, `update(TicketType $type, array $data): void` and `delete(TicketType $type): void`. Today `create()` and `update()` are one line each. That is fine: the methods give the work a name and a home.

                `delete()` holds a business rule. Staff cannot delete a type that has paid tickets, the tickets with a code. A family paid for those tickets, and the order must still show them. Throw `TicketTypeHasSales` with the message "Families bought tickets of this type. Keep it, so their orders stay complete."
                TEXT)
            ->think('`create()` is one line. Why does it still go in a service, and not in the controller?')
            ->hint('`$type->tickets()->whereNotNull(\'code\')->exists()` is `true` when the type has a paid ticket.')
            ->code(<<<'PHP'
                /**
                 * Delete a ticket type that nobody paid for. Its unpaid tickets go with it.
                 *
                 * @throws TicketTypeHasSales when the type has paid tickets
                 */
                public function delete(TicketType $type): void
                {
                    if ($type->tickets()->whereNotNull('code')->exists()) {
                        throw new TicketTypeHasSales;
                    }

                    $type->delete();
                }
                PHP)
            ->tryIt('In `php artisan tinker`, run `$type = App\Models\Ticket::factory()->paid()->create()->ticketType; app(App\Services\TicketTypeService::class)->delete($type)`. You see `TicketTypeHasSales` and its message.')
            ->commit('Manage ticket types in a service')
            ->docs('/guide/services', 'BLSC guide: Controllers and services')
            ->check(Check::all(
                Check::fileContains('app/Services/TicketTypeService.php', 'function delete'),
                Check::fileExists('app/Exceptions/TicketTypeHasSales.php'),
            )),

        Step::make('Let staff manage ticket types')
            ->run("php artisan make:controller Admin/TicketTypeController --resource --model=TicketType\nphp artisan make:request TicketTypeRequest")
            ->explain(<<<'TEXT'
                Copy the pattern of the category admin: a resource controller without `show`, a form request and four views in `resources/views/admin/ticket-types`. Each method that writes asks for `TicketTypeService $types` and calls it.

                Staff type the price in dollars, as on the session form. Copy `sessionData()` from `ProgramSessionRequest` into a `ticketTypeData()` method that turns `price` into `price_cents`. The controller passes `$request->ticketTypeData()` to the service. The event is a select of the upcoming sessions, with an empty choice for admission tickets. Check it with `Rule::exists('program_sessions', 'id')`.

                The list shows each type with its event, its price and "sold of capacity". Count only paid tickets: `withCount(['tickets as sold_count' => fn ($query) => $query->whereNotNull('code')])`.

                `destroy()` catches `TicketTypeHasSales` and sends staff back with a `ticket_type` error. Add a "Ticket types" link to the staff menu.
                TEXT)
            ->think('Staff want to stop selling a ticket type but keep its sold tickets. What can they do today? What small change would help them?')
            ->hint('Look at `Admin\CategoryController::destroy()`. It catches `CategoryHasPrograms` in the same way.')
            ->hint('The menu link uses `request()->routeIs(\'admin.ticket-types.*\')` and the `<x-lucide-ticket>` icon.')
            ->tryIt('Log in as `staff@example.com` and click "Ticket types". Create "BIG Night Out" for an upcoming session, with a price of 35.50 and 80 tickets. Open http://localhost:8000/tickets. You see the new type with its date and "80 left".')
            ->commit('Add the ticket type admin')
            ->docs('https://laravel.com/docs/controllers#resource-controllers', 'Laravel docs: Resource controllers')
            ->docs('https://laravel.com/docs/eloquent-relationships#counting-related-models', 'Laravel docs: Counting related models')
            ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/12', 'Laravel From Scratch, episode 12: Form Request Classes')
            ->check(Check::all(
                Check::route('admin.ticket-types.index', 'admin.ticket-types.store', 'admin.ticket-types.destroy'),
                Check::view('admin.ticket-types.index'),
                Check::fileContains('app/Http/Controllers/Admin/TicketTypeController.php', 'TicketTypeService'),
            )),

        Step::make('Run the checks and open your pull request')
            ->run('php artisan guide:verify tickets')
            ->explain(<<<'TEXT'
                Run the acceptance test with `guide:verify`. Then run `composer ci:check`. It runs Pint, PHPStan and every test.

                If a rule shows an error page and not a message, the controller does not catch an exception from the service. Read the class name in the error, and catch that exception in the controller.
                TEXT)
            ->think('An unpaid ticket stays in the database when a visitor leaves without paying. Is that a problem? How could the app clean up old unpaid tickets?')
            ->tryIt('Run `composer ci:check`. Pint, PHPStan and all the tests pass.')
            ->commit('Fix the checks for tickets')
            ->check(Check::testPassed('TicketsTest')),
    ]);
