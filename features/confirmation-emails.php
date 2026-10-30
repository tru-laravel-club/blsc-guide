<?php

use TruLaravelClub\BlscGuide\Check;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\Section;
use TruLaravelClub\BlscGuide\Step;

return Feature::make('confirmation-emails')
    ->title('Confirmation emails')
    ->section(Section::Signups)
    ->level(2)
    ->needs(['cart-orders'])
    ->summary('A queued email confirms each paid order, with the details of each item. Each item writes its own line, so the email does not need to know what it confirms.')
    ->teaches(['Markdown mailables', 'Queued mail', 'Event listeners', 'Mail::fake'])
    ->test('ConfirmationEmailsTest.php')
    ->intro(<<<'TEXT'
        A family pays for an order. They need a receipt and the details of what they bought: a ticket code, the dates of a membership, the code of a gift certificate. A guest has no account, so the email is the only place where they find their order again.

        The Cart and orders feature already sends an `OrderPaid` event after it fulfils the items. In this feature, a listener hears that event and queues an `OrderConfirmation` mail to the order's email address.

        The email does not know what it confirms. It asks each item for its `confirmationLine()`, from the `Purchasable` interface. A ticket writes its code, and a membership writes its dates. When the centre sells a new kind of thing, the email needs no change.

        The email goes on the queue. Sending mail means a slow request to a mail server. The payer must not wait for it, and a mail server that is down must not break the payment. A queue worker sends the email a moment later.
        TEXT)
    ->docs('https://laravel.com/docs/mail#generating-markdown-mailables', 'Laravel docs: Generating Markdown mailables')
    ->docs('https://laravel.com/docs/mail#queueing-mail', 'Laravel docs: Queueing mail')
    ->docs('https://laravel.com/docs/mail#testing-mailable-sending', 'Laravel docs: Testing mailable sending')
    ->reflect([
        'Why does each item write its own line, and not the mail?',
        'The mail server is down for ten minutes. What happens to the payment? What happens to the email?',
        'The same order is paid twice by mistake. Why does the family still get only one email?',
        'Which step was the hardest? What would help the next contributor? Open an issue or a pull request in blsc-guide to improve that step.',
    ])
    ->steps([
        Step::make('Claim the feature and copy in its test')
            ->run('php artisan guide:start confirmation-emails')
            ->explain(<<<'TEXT'
                Run the command below. It copies `tests/Feature/ConfirmationEmailsTest.php` into the app.

                The test defines its own purchasable, `EmailTestItem`, as the cart test does. Its `fulfil()` saves a code, and its `confirmationLine()` writes the code. So the test proves that the email shows a line that only exists after the payment.

                `Mail::fake()` in `beforeEach()` stops every real email. The rules then ask the fake what the app tried to send: `Mail::assertQueued()` and `Mail::assertNothingQueued()`.
                TEXT)
            ->think('The second rule makes a new `OrderConfirmation` and reads its text. Why does it not need to send the email to check the text?')
            ->tryIt('Run `php artisan test tests/Feature/ConfirmationEmailsTest.php`. All the rules fail, because `App\Mail\OrderConfirmation` does not exist yet.')
            ->commit('Add the confirmation emails acceptance test')
            ->docs('https://laravel.com/docs/mail#testing-mailable-sending', 'Laravel docs: Testing mailable sending')
            ->check(Check::fileExists('tests/Feature/ConfirmationEmailsTest.php')),

        Step::make('Create the mailable and its Markdown view')
            ->run('php artisan make:mail OrderConfirmation --markdown=mail.orders.confirmation')
            ->explain(<<<'TEXT'
                Run the command below. It makes `app/Mail/OrderConfirmation.php` and the view `resources/views/mail/orders/confirmation.blade.php`. A Markdown mailable uses Laravel's email layout, so the email looks correct in every mail program without your own HTML.

                Give the constructor a public `Order $order` property. A public property is available in the view, and the test reads it, too.

                Add `implements ShouldQueue` to the class. Then every send of this mailable goes on the queue, even if a later developer calls `send()` instead of `queue()`.

                Set the subject in `envelope()` to "Your BLSC order" and the order ID. In `content()`, pass the items with their records: `$this->order->items()->with('purchasable')->get()`.
                TEXT)
            ->think('The mailable waits on the queue for a few seconds. The `SerializesModels` trait stores only the ID of the order. Why is that better than a copy of the whole order?')
            ->hint('Look at `PaymentSucceeded`. It has a public property in its constructor, too.')
            ->hint('`new Content(markdown: \'mail.orders.confirmation\', with: [\'items\' => ...])` passes extra data to the view.')
            ->code(<<<'PHP'
                class OrderConfirmation extends Mailable implements ShouldQueue
                {
                    use Queueable, SerializesModels;

                    public function __construct(public Order $order) {}

                    public function envelope(): Envelope
                    {
                        return new Envelope(
                            subject: 'Your BLSC order '.$this->order->id,
                        );
                    }

                    public function content(): Content
                    {
                        return new Content(
                            markdown: 'mail.orders.confirmation',
                            with: [
                                'items' => $this->order->items()->with('purchasable')->get(),
                            ],
                        );
                    }
                }
                PHP)
            ->tryIt('Run `php artisan test tests/Feature/ConfirmationEmailsTest.php --filter=queued`. The rule "the email is queued" passes. The other rules still fail.')
            ->commit('Add the OrderConfirmation mailable')
            ->docs('https://laravel.com/docs/mail#generating-markdown-mailables', 'Laravel docs: Generating Markdown mailables')
            ->docs('https://laravel.com/docs/mail#queueing-by-default', 'Laravel docs: Queueing by default')
            ->check(Check::all(Check::classExists('App\Mail\OrderConfirmation'), Check::fileContains('app/Mail/OrderConfirmation.php', 'ShouldQueue'))),

        Step::make('Write the email')
            ->explain(<<<'TEXT'
                Open `resources/views/mail/orders/confirmation.blade.php`. Write a heading, then one list line for each item, then the total and a button to the order page.

                For each item, write `$item->purchasable->confirmationLine()`. If staff deleted the record, `purchasable` is `null`. Then fall back to `$item->description`, the copy that the order item keeps.

                The button goes to `$order->url()`, the signed link from Cart and orders. A guest has no account, so this link is their way back to the order.

                Markdown is sensitive to spaces. Do not indent the lines inside `<x-mail::message>`. An indented line becomes a code block.
                TEXT)
            ->think('Why does the email use `confirmationLine()` and not `description`? What does a ticket buyer need to read in the email?')
            ->hint('`{{ $item->purchasable?->confirmationLine() ?? $item->description }}` uses the record when it exists.')
            ->hint('`<x-money :cents="$order->total_cents" />` from Cart and orders formats the total.')
            ->code(<<<'PHP'
                <x-mail::message>
                # Thank you for your order

                We received your payment for order {{ $order->id }}.

                @foreach ($items as $item)
                - {{ $item->purchasable?->confirmationLine() ?? $item->description }}
                @endforeach

                Total paid: <x-money :cents="$order->total_cents" />

                <x-mail::button :url="$order->url()">
                See your order
                </x-mail::button>

                Thanks,<br>
                {{ config('app.name') }}
                </x-mail::message>
                PHP)
            ->tryIt('In `php artisan tinker`, run `echo (new App\Mail\OrderConfirmation(App\Models\Order::create([\'email\' => \'test@example.com\', \'total_cents\' => 2500])))->render();`. You see the HTML of the email with "Thank you for your order" and $25.00.')
            ->commit('Write the order confirmation email')
            ->docs('https://laravel.com/docs/mail#writing-markdown-messages', 'Laravel docs: Writing Markdown messages')
            ->check(Check::fileContains('resources/views/mail/orders/confirmation.blade.php', 'confirmationLine()')),

        Step::make('Queue the email when an order is paid')
            ->run('php artisan make:listener SendOrderConfirmation --event=OrderPaid')
            ->explain(<<<'TEXT'
                Run the command below. Laravel finds the listener by the type of its `handle()` parameter, as it found `MarkOrderAsPaid`.

                In `handle()`, queue the mailable to the order's email: `Mail::to($event->order->email)->queue(...)`. Every order has an email, also a guest order, so you do not need the user.

                You do not need a check for "already sent". `markAsPaid()` sends `OrderPaid` only once for each order. The listener trusts that rule. The last rule of the test proves it: the same payment twice gives one email.
                TEXT)
            ->think('Cart and orders sends `OrderPaid`, and this feature listens. What changed in the cart code to add the email? Why is that good?')
            ->hint('Remove the empty constructor that the command made. The listener needs nothing in it.')
            ->code(<<<'PHP'
                public function handle(OrderPaid $event): void
                {
                    Mail::to($event->order->email)->queue(new OrderConfirmation($event->order));
                }
                PHP)
            ->tryIt(<<<'TEXT'
                Your `.env` uses `MAIL_MAILER=log` and the `database` queue. In `php artisan tinker`, run `App\Events\OrderPaid::dispatch(App\Models\Order::create(['email' => 'test@example.com', 'total_cents' => 2500]));`.

                Nothing is sent yet: the email waits on the queue. Run `php artisan queue:work --once`. You see `App\Mail\OrderConfirmation ... DONE`. Open `storage/logs/laravel.log`. At the end, you see the email with the subject "Your BLSC order" and its number.
                TEXT)
            ->commit('Queue the confirmation email for paid orders')
            ->docs('https://laravel.com/docs/events#defining-listeners', 'Laravel docs: Defining listeners')
            ->docs('https://laravel.com/docs/mail#log-driver', 'Laravel docs: The log driver')
            ->docs('https://laravel.com/docs/queues#running-the-queue-worker', 'Laravel docs: Running the queue worker')
            ->check(Check::fileContains('app/Listeners/SendOrderConfirmation.php', 'OrderConfirmation')),

        Step::make('Run the checks and open your pull request')
            ->run('php artisan guide:verify confirmation-emails')
            ->explain(<<<'TEXT'
                Run the acceptance test with `guide:verify`. Then run `composer ci:check`. It runs Pint, PHPStan and every test.

                In your pull request, tell reviewers how to see the email on their machine: the tinker line and `php artisan queue:work --once` from the last step.
                TEXT)
            ->think('On Laravel Cloud, who runs `queue:work`? What happens to the emails if no worker runs?')
            ->tryIt('Run `composer ci:check`. Pint, PHPStan and all the tests pass.')
            ->commit('Fix the checks for confirmation emails')
            ->check(Check::testPassed('ConfirmationEmailsTest')),
    ]);
