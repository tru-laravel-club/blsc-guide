<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('cart-orders')
    ->title('Cart and orders')
    ->section(Section::Signups)
    ->level(3)
    ->needs(['payments'])
    ->summary('Families put several items in one cart and pay once. One payment pays for one order. Anything that implements the Purchasable interface can go in the cart, and the server always decides the price.')
    ->teaches(['Interfaces as contracts', 'The session', 'Polymorphic relationships', 'Database transactions', 'Event listeners', 'Signed URLs', 'Anonymous Blade components'])
    ->test('CartOrdersTest.php')
    ->intro(<<<'TEXT'
        A family registers two children for a camp and buys a membership. They do not want to pay three times. So the site has a cart: the family adds things, checks out once, and pays one payment for one `Order`.

        The cart sells many kinds of thing: tickets, registrations, memberships, deposits and gift certificates. Most of them do not exist yet. So the cart must not know about any of them. It knows only an interface, `App\Contracts\Purchasable`. Each kind of thing that you can buy implements four methods: `orderDescription()`, `priceCents(?User $user)`, `fulfil()` and `confirmationLine()`. A new kind of thing needs no change in the cart.

        This is the path of a purchase. Another feature makes a record, such as an unpaid ticket, and adds it to the `App\Cart`. The cart lives in the session, so guests have a cart, too. At checkout, the app makes an `Order` with one `OrderItem` for each record. Each item points to its record with a polymorphic `purchasable` link. Then the app makes a `Payment` for the order, from the Payments feature, and sends the payer to it.

        When the payment passes, the Payments feature sends `PaymentSucceeded`. Your listener marks the order as paid and calls `fulfil()` on each record: the ticket gets its code, and the registration is confirmed. Last, the order sends its own event, `OrderPaid`, so later features, such as Confirmation emails, can act.

        One rule protects the money: prices always come from the server. The session keeps only the class and the ID of each record. At checkout, the app asks each record for its price. A form never sends a price, so a changed form cannot change what a family pays.
        TEXT)
    ->video('https://laracasts.com/series/laravel-from-scratch-2026/episodes/16', 'Laravel From Scratch, episode 16: Eloquent Relationships')
    ->docs('https://laravel.com/docs/session#interacting-with-the-session', 'Laravel docs: Interacting with the session')
    ->docs('https://laravel.com/docs/eloquent-relationships#polymorphic-relationships', 'Laravel docs: Polymorphic relationships')
    ->docs('https://laravel.com/docs/events#event-discovery', 'Laravel docs: Event discovery')
    ->reflect([
        'Tickets, registrations, memberships and gift certificates all go in the same cart. What do they share? What does the cart not need to know?',
        'The order item keeps a copy of the description and the price. Why not read them from the record each time?',
        'Why does `markAsPaid()` change the status with a `where(\'status\', ...)` condition, and not only check the status first?',
        'Which step was the hardest? What would help the next contributor? Open an issue or a pull request in blsc-guide to improve that step.',
    ])
    ->steps([
        Step::make('Claim the feature and copy in its test')
            ->run('php artisan guide:start cart-orders')
            ->explain(<<<'TEXT'
                Run the command below. It copies `tests/Feature/CartOrdersTest.php` into the app. Read the whole test before you write code.

                Look at the top of the test. It defines its own small model, `CartTestItem`, and makes its table in `beforeEach()`. Nothing in the app is for sale yet, but the cart must work with anything that implements `Purchasable`. So the test brings its own purchasable. This also proves that the cart does not depend on any one kind of thing.

                `beforeEach()` puts two items in the cart with `app(Cart::class)->add()`. Then the rules check out, pay with the fake gateway, and look at the order.
                TEXT)
            ->think('`CartTestItem::fulfil()` adds 1 to `times_fulfilled`. Which rule uses this counter, and what does it prove?')
            ->tryIt('Run `php artisan test tests/Feature/CartOrdersTest.php`. All the rules fail, because `App\Contracts\Purchasable` does not exist yet.')
            ->commit('Add the cart and orders acceptance test')
            ->docs('https://laravel.com/docs/http-tests', 'Laravel docs: HTTP tests')
            ->check(Check::fileExists('tests/Feature/CartOrdersTest.php')),

        Step::make('Write the Purchasable interface')
            ->run('php artisan make:interface App/Contracts/Purchasable')
            ->explain(<<<'TEXT'
                The interface is the contract between the cart and everything that it sells. Ask what the cart must know about a thing, and nothing more:

                1. `orderDescription(): string` gives the line on the order, such as "Admission ticket".
                2. `priceCents(?User $user): int` gives the price for this buyer. The buyer is `null` for a guest. A member can get a lower price, so the price depends on the buyer.
                3. `fulfil(): void` gives the family what they paid for. The app calls it once, after the payment passes.
                4. `confirmationLine(): string` gives one line for the order page and the confirmation email, such as a ticket code.

                The cart never asks "is this a ticket?". If the code needs a new answer from every item, it adds a method here.
                TEXT)
            ->think('Why is the buyer a parameter of `priceCents()`, and not a property of the item?')
            ->hint('An interface method has no body. End each method with `;`.')
            ->code(<<<'PHP'
                interface Purchasable
                {
                    public function orderDescription(): string;

                    public function priceCents(?User $user): int;

                    public function fulfil(): void;

                    public function confirmationLine(): string;
                }
                PHP)
            ->tryIt('In `php artisan tinker`, run `get_class_methods(App\Contracts\Purchasable::class)`. You see the four methods.')
            ->commit('Add the Purchasable interface')
            ->docs('https://laravel.com/docs/contracts#when-to-use-contracts', 'Laravel docs: When to use contracts')
            ->check(Check::fileContains('app/Contracts/Purchasable.php', 'function confirmationLine')),

        Step::make('Create the order status enum')
            ->run('php artisan make:enum App/Enums/OrderStatus --string')
            ->explain(<<<'TEXT'
                An order is pending until its payment passes. Then it is paid. Staff can cancel an order later. Add three cases: Pending (pending), Paid (paid) and Cancelled (cancelled).

                Add a `label()` method with a `match`, as the order page shows the status to the family. "Waiting for payment" is clearer for a parent than "pending".
                TEXT)
            ->think('A payment has a status, and an order has a status. Why does the order need its own?')
            ->code(<<<'PHP'
                public function label(): string
                {
                    return match ($this) {
                        self::Pending => 'Waiting for payment',
                        self::Paid => 'Paid',
                        self::Cancelled => 'Cancelled',
                    };
                }
                PHP)
            ->tryIt('In `php artisan tinker`, run `App\Enums\OrderStatus::Pending->label()`. You see "Waiting for payment".')
            ->commit('Add the OrderStatus enum')
            ->docs('https://laravel.com/docs/eloquent-mutators#enum-casting', 'Laravel docs: Enum casting')
            ->check(fn (): bool => is_file(base_path('app/Enums/OrderStatus.php'))),

        Step::make('Create the orders and order items tables')
            ->run("php artisan make:model Order -m\nphp artisan make:model OrderItem -m")
            ->explain(<<<'TEXT'
                Run the two commands below. Then fill in both migrations and run `php artisan migrate`.

                An order has an owner and a total. `user_id` is nullable, because guests buy tickets. Every order has an `email`, so the app can send a receipt to a guest, too. Store `total_cents` in cents, as the payments do.

                An order item is one line of the order. `morphs('purchasable')` makes `purchasable_type` and `purchasable_id`. They point to the record that the line sells, such as a ticket. Use `morphs()`, not `nullableMorphs()`: every line sells something.

                The item also keeps a copy of `description` and `price_cents`. Staff can change a price or a name next month. The order must still show what the family paid on the day.
                TEXT)
            ->think('Why does an order item copy the price, when the record can tell you its price?')
            ->hint('Look at the `payments` migration. `user_id` is the same here: nullable, with `nullOnDelete()`.')
            ->hint('`$table->foreignId(\'order_id\')->constrained()->cascadeOnDelete();` deletes the lines when an order is deleted. A line without an order means nothing.')
            ->code(<<<'PHP'
                Schema::create('orders', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                    $table->string('email');
                    $table->string('status')->default('pending');
                    $table->unsignedInteger('total_cents');
                    $table->timestamps();
                });

                Schema::create('order_items', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('order_id')->constrained()->cascadeOnDelete();
                    $table->morphs('purchasable');
                    $table->string('description');
                    $table->unsignedInteger('price_cents');
                    $table->unsignedSmallInteger('quantity')->default(1);
                    $table->timestamps();
                });
                PHP)
            ->tryIt('Run `php artisan migrate`. Then, in `php artisan tinker`, run `Schema::getColumnListing(\'order_items\')`. You see `purchasable_type` and `purchasable_id`.')
            ->commit('Add the orders and order items tables')
            ->docs('https://laravel.com/docs/eloquent-relationships#one-to-many-polymorphic-relations', 'Laravel docs: One to many polymorphic relations')
            ->check(Check::all(
                Check::table('orders', ['user_id', 'email', 'status', 'total_cents']),
                Check::table('order_items', ['order_id', 'purchasable_type', 'purchasable_id', 'description', 'price_cents', 'quantity']),
            )),

        Step::make('Fill in the Order and OrderItem models')
            ->explain(<<<'TEXT'
                Open `app/Models/Order.php`. Put `user_id`, `email` and `total_cents` in `#[Fillable]`, and keep `status` out. Only the payment code makes an order paid. Give `status` a default of `pending` and cast it to `OrderStatus`.

                Add three relationships: `user(): BelongsTo`, `items(): HasMany` and `payment(): MorphOne`. The payment is polymorphic: `Payment::payable()` points to the order, so the order finds its payment with `morphOne(Payment::class, 'payable')`. Add `->latestOfMany()`, so `$order->payment` is the newest one.

                Add `url()`, as on `Payment`. It returns `URL::signedRoute('orders.show', $this)`. You add the route later.

                Open `app/Models/OrderItem.php`. Put `description`, `price_cents` and `quantity` in `#[Fillable]`. Add `order(): BelongsTo` and `purchasable(): MorphTo`. In the docblock, write `@property-read (Model&Purchasable)|null $purchasable`. PHPStan then knows that the record has the four methods.
                TEXT)
            ->think('`Payment` has `payable(): MorphTo`, and `Order` has `payment(): MorphOne`. Which table holds the link? Compare this with `belongsTo` and `hasOne`.')
            ->hint('Look at `Payment::payable()` and `Payment::url()`. You write the same kind of code here.')
            ->hint('`Model&Purchasable` is an intersection type: the value is a model and it implements the interface.')
            ->code(<<<'PHP'
                /**
                 * The latest payment for this order.
                 *
                 * @return MorphOne<Payment, $this>
                 */
                public function payment(): MorphOne
                {
                    return $this->morphOne(Payment::class, 'payable')->latestOfMany();
                }
                PHP)
            ->tryIt('In `php artisan tinker`, run `(new App\Models\Order)->status->label()`. You see "Waiting for payment", from the default status.')
            ->commit('Fill in the Order and OrderItem models')
            ->docs('https://laravel.com/docs/eloquent-relationships#one-of-many-polymorphic-relations', 'Laravel docs: One of many polymorphic relations')
            ->docs('https://laravel.com/docs/eloquent-relationships#one-to-many', 'Laravel docs: One to many relationships')
            ->check(Check::all(
                Check::fileContains('app/Models/Order.php', 'function payment'),
                Check::fileContains('app/Models/OrderItem.php', 'morphTo'),
            )),

        Step::make('Build the cart in the session')
            ->run('php artisan make:class App/Cart')
            ->explain(<<<'TEXT'
                The cart belongs to one visitor, and guests have no account. So the cart lives in the session. The session is private to one browser, and the browser cannot change it.

                Give `App\Cart` a constructor with `private Session $session` (the `Illuminate\Contracts\Session\Session` interface). The container fills it, so `app(Cart::class)` and a controller parameter both work.

                Keep the session small. Store only the class and the ID of each record under the `cart` key, such as `['App\Models\Ticket', 5]`. Give each entry a short key, such as `ticket-5`, so a route can remove it. Never store a price.

                Write six methods: `add(Model&Purchasable $item)`, `remove(string $key)`, `items()`, `total(?User $user)`, `count()` and `clear()`. `items()` loads each record from the database again. A record that staff deleted is left out. `total()` asks each item for `priceCents()` every time.
                TEXT)
            ->think('The cart keeps a ticket for three days. Staff change the ticket price on the second day. Which price does the family pay? Why is that correct?')
            ->hint('`$this->session->put(\'cart.ticket-5\', [...])` writes into the `cart` array. The dot means "inside".')
            ->hint('`Str::kebab(class_basename($item))` turns `App\Models\Ticket` into `ticket`. Add `-` and `$item->getKey()`.')
            ->hint('In `items()`, map each reference with `$reference[0]::find($reference[1])`, then `filter()` the items that are an `instanceof Purchasable`.')
            ->code(<<<'PHP'
                public function add(Model&Purchasable $item): void
                {
                    $this->session->put($this->sessionKey($item), [$item::class, $item->getKey()]);
                }

                /**
                 * The total in cents. The server asks each item for its price every time.
                 */
                public function total(?User $user): int
                {
                    return $this->items()->sum(fn (Purchasable $item): int => $item->priceCents($user));
                }

                private function sessionKey(Model $item): string
                {
                    return 'cart.'.Str::kebab(class_basename($item)).'-'.$item->getKey();
                }
                PHP)
            ->tryIt('In `php artisan tinker`, run `app(App\Cart::class)->count()`. You see `0`. The tinker session is empty, so the cart is empty, too.')
            ->commit('Add the session cart')
            ->docs('https://laravel.com/docs/session#interacting-with-the-session', 'Laravel docs: Interacting with the session')
            ->docs('https://laravel.com/docs/session#deleting-data', 'Laravel docs: Deleting data from the session')
            ->docs('https://laravel.com/docs/container#automatic-injection', 'Laravel docs: Automatic injection')
            ->check(Check::fileContains('app/Cart.php', 'function total')),

        Step::make('Show the cart')
            ->run('php artisan make:controller CartController')
            ->explain(<<<'TEXT'
                Add two public routes: `GET /cart` named `cart.show` and `DELETE /cart/{key}` named `cart.remove`. They are not in the `auth` group, because guests have a cart.

                `show()` passes `$cart->items()` and `$cart->total($request->user())` to `resources/views/cart/show.blade.php`. The view lists each item with its description, its price and a "Remove" button. Under the list, show the total and a "Check out and pay" form. A guest types an email in the form. A logged-in parent does not, because the account has one.

                Many pages show money now. Make one anonymous component, `resources/views/components/money.blade.php`, that takes `cents` and formats it. Then every page writes `<x-money :cents="$total" />`, and the format lives in one place.

                Last, show a "Cart (2)" link in the header of `resources/views/layouts/site.blade.php` when the cart is not empty.
                TEXT)
            ->think('The remove button sends a key, such as `ticket-5`. A visitor changes it to `ticket-6`. What happens? Why is that safe?')
            ->hint('An anonymous component starts with `@props([\'cents\'])`. Then format with `Illuminate\Support\Number::currency($cents / 100, in: \'CAD\', locale: \'en_CA\')`.')
            ->hint('`@foreach ($items as $key => $item)` gives you the cart key for the remove form.')
            ->hint('In the layout, `@if (($cartCount = app(App\Cart::class)->count()) > 0)` reads the count once.')
            ->code(<<<'PHP'
                Route::get('/cart', [CartController::class, 'show'])->name('cart.show');
                Route::delete('/cart/{key}', [CartController::class, 'destroy'])->name('cart.remove');
                PHP)
            ->tryIt('Run `php artisan serve` and open http://localhost:8000/cart. You see "Your cart is empty." The Tickets and Membership plans features add the first real items.')
            ->commit('Add the cart page')
            ->docs('https://laravel.com/docs/blade#anonymous-components', 'Laravel docs: Anonymous components')
            ->docs('https://laravel.com/docs/routing#route-parameters', 'Laravel docs: Route parameters')
            ->check(Check::all(Check::route('cart.show', 'cart.remove'), Check::view('cart.show'), Check::view('components.money'))),

        Step::make('Check out: make the order and its payment')
            ->run('php artisan make:controller CheckoutController')
            ->explain(<<<'TEXT'
                Add `POST /checkout` named `checkout.store`. The controller does four things:

                1. Validate the email. It is required only for a guest: `Rule::requiredIf($user === null)`.
                2. Refuse an empty cart. Send the visitor back to the cart with a `cart` error.
                3. Make the order with `Order::place($items, $user, $email)`. Then clear the cart.
                4. Make the payment with `$order->createPayment()`, and redirect to `$payment->url()`.

                Write `Order::place()` as a static method. It runs in a database transaction: it creates the order, then one item for each record, then saves the total. If one insert fails, the database keeps nothing, so no order has half of its items.

                For each record, ask the record, never the request: `orderDescription()` and `priceCents($user)`. Link the item to the record with `->purchasable()->associate($item)`.

                `createPayment()` makes the payment through `$this->payment()->create([...])`. Laravel fills in `payable_type` and `payable_id`. Give it the order's `user_id`, a description, the total, and `return_url` set to `$this->url()`. After the payment, the "Continue" button brings the family back to their order.
                TEXT)
            ->think('The test posts `total_cents` and `price_cents` with the checkout form. Which line of your code makes sure that they change nothing?')
            ->hint('`DB::transaction(function () use ($items, $user, $email): self { ... })` returns what the function returns.')
            ->hint('`$order->items()->make([...])` makes an item with the `order_id` already set, without saving it. Then `->purchasable()->associate($item)->save()`.')
            ->hint('`$user->email ?? $validated[\'email\']` uses the account email when somebody is logged in.')
            ->code(<<<'PHP'
                foreach ($items as $item) {
                    $order->items()->make([
                        'description' => $item->orderDescription(),
                        'price_cents' => $item->priceCents($user),
                    ])->purchasable()->associate($item)->save();
                }
                PHP)
            ->tryIt('Run `php artisan test tests/Feature/CartOrdersTest.php --filter=checks`. The checkout rules pass. The rules that pay still fail, because nothing listens for the payment yet.')
            ->commit('Add checkout')
            ->docs('https://laravel.com/docs/database#database-transactions', 'Laravel docs: Database transactions')
            ->docs('https://laravel.com/docs/eloquent-relationships#updating-belongs-to-relationships', 'Laravel docs: Updating belongs to relationships')
            ->docs('https://laravel.com/docs/validation#rule-required-if', 'Laravel docs: The required_if rule')
            ->check(Check::all(Check::route('checkout.store'), Check::fileContains('app/Models/Order.php', 'function place'))),

        Step::make('Mark the order as paid when its payment passes')
            ->run("php artisan make:event OrderPaid\nphp artisan make:listener MarkOrderAsPaid --event=PaymentSucceeded")
            ->explain(<<<'TEXT'
                The Payments feature sends `PaymentSucceeded` and knows nothing about orders. Now you listen for it. Laravel finds listeners in `app/Listeners` by the type of the `handle()` parameter, so you do not register the listener.

                `handle()` reads `$event->payment->payable`. If it is an `Order`, call `$order->markAsPaid()`. Other payments are not yours: a later feature can pay for something else.

                `markAsPaid()` runs in a transaction. First it changes the status with one query: `where('status', OrderStatus::Pending)->update(['status' => OrderStatus::Paid])`. The query returns the number of changed rows. If it is `0`, the order was already paid, so stop. A second event, or two requests at the same time, cannot fulfil the items twice. Then load the items with their records and call `fulfil()` on each one.

                After the transaction, dispatch `OrderPaid` with the order. Give the event a public `Order $order` property, as `PaymentSucceeded` has.
                TEXT)
            ->think('Why does the listener check `instanceof Order`, and not `payable_type === \'App\Models\Order\'`?')
            ->hint('`self::whereKey($this->id)` starts a query for this one order.')
            ->hint('`$this->items()->with(\'purchasable\')->get()` loads every record in one query for each type.')
            ->code(<<<'PHP'
                public function markAsPaid(): void
                {
                    $paid = DB::transaction(function (): bool {
                        $updated = self::whereKey($this->id)
                            ->where('status', OrderStatus::Pending)
                            ->update(['status' => OrderStatus::Paid]);

                        if ($updated === 0) {
                            return false;
                        }

                        foreach ($this->items()->with('purchasable')->get() as $item) {
                            if ($item->purchasable instanceof Purchasable) {
                                $item->purchasable->fulfil();
                            }
                        }

                        return true;
                    });

                    if ($paid) {
                        $this->refresh();
                        OrderPaid::dispatch($this);
                    }
                }
                PHP)
            ->tryIt('Run `php artisan event:list --event=PaymentSucceeded`. You see `App\Listeners\MarkOrderAsPaid@handle` under the event.')
            ->commit('Mark orders as paid and fulfil their items')
            ->docs('https://laravel.com/docs/events#defining-listeners', 'Laravel docs: Defining listeners')
            ->docs('https://laravel.com/docs/events#event-discovery', 'Laravel docs: Event discovery')
            ->docs('https://laravel.com/docs/queries#update-statements', 'Laravel docs: Update statements')
            ->check(Check::all(Check::classExists('App\Events\OrderPaid'), Check::fileContains('app/Listeners/MarkOrderAsPaid.php', 'markAsPaid'))),

        Step::make('Show the order with a signed link')
            ->run("php artisan make:controller OrderController\nphp artisan make:policy OrderPolicy --model=Order")
            ->explain(<<<'TEXT'
                The family comes back to the order page after the payment, and later from the confirmation email. A guest has no account, so the page uses a signed link, as the payment page does.

                Add `GET /orders/{order}` named `orders.show`, with `->middleware('signed')`. Write `OrderPolicy::view()` with the same rule as `PaymentPolicy`: anyone with the link can see a guest order, and an account order needs its owner.

                `show()` authorizes, loads `items.purchasable`, and shows `resources/views/orders/show.blade.php`. The view shows the status label, each item with its price, and the total. When the order is paid, show `confirmationLine()` under each item: a ticket code or the dates of a membership. When the order is pending, show a "Pay now" button to `$order->payment->url()`.
                TEXT)
            ->think('The payment page and the order page use the same rule. Should they share one policy? What would that cost later?')
            ->hint('Copy `PaymentPolicy::view()` and change the model.')
            ->hint('`$order->load(\'items.purchasable\', \'payment\')` loads all the records that the view needs.')
            ->tryIt(<<<'TEXT'
                Run `php artisan test tests/Feature/CartOrdersTest.php`. All the rules pass.

                Then run `php artisan route:list --name=orders`. You see the `signed` middleware on `orders.show`.
                TEXT)
            ->commit('Add the order page')
            ->docs('https://laravel.com/docs/urls#signed-urls', 'Laravel docs: Signed URLs')
            ->docs('https://laravel.com/docs/authorization#guest-users', 'Laravel docs: Guest users in policies')
            ->docs('https://laravel.com/docs/eloquent-relationships#eager-loading', 'Laravel docs: Eager loading')
            ->check(Check::all(Check::route('orders.show'), Check::view('orders.show'), Check::classExists('App\Policies\OrderPolicy'))),

        Step::make('Run the checks and open your pull request')
            ->run('php artisan guide:verify cart-orders')
            ->explain(<<<'TEXT'
                Run the acceptance test with `guide:verify`. Then run `composer ci:check`. It runs Pint, PHPStan and every test, as GitHub does on your pull request.

                PHPStan is strict about the cart, because the session gives back plain arrays. Describe the types in docblocks, such as `@return Collection<string, Model&Purchasable>`. Do not silence an error. Each one tells you about a value that the code does not check.

                In your pull request, tell the reviewers which features can now use the cart: tickets, memberships, registrations, deposits and gift certificates.
                TEXT)
            ->think('A reviewer asks: "What happens to an unpaid ticket that stays in a cart forever?" What is your answer?')
            ->tryIt('Run `composer ci:check`. Pint, PHPStan and all the tests pass.')
            ->commit('Fix the checks for cart and orders')
            ->check(Check::testPassed('CartOrdersTest')),
    ]);
