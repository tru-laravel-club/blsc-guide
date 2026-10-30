<?php

use App\Cart;
use App\Contracts\Purchasable;
use App\Events\PaymentSucceeded;
use App\Mail\OrderConfirmation;
use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;

/*
| Acceptance test for the "Confirmation emails" feature.
|
| When an order is paid, the buyer gets one queued email. Each item writes its
| own line in the email, so the mail does not know what it confirms.
*/

class EmailTestItem extends Model implements Purchasable
{
    protected $table = 'email_test_items';

    protected $guarded = [];

    public function orderDescription(): string
    {
        return $this->name;
    }

    public function priceCents(?User $user): int
    {
        return 1000;
    }

    public function fulfil(): void
    {
        $this->update(['code' => strtoupper(substr($this->name, 0, 4)).'-7']);
    }

    public function confirmationLine(): string
    {
        return $this->name.': your code is '.$this->code;
    }
}

beforeEach(function () {
    config(['payments.gateway' => 'fake']);
    Mail::fake();

    Schema::create('email_test_items', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('code')->nullable();
        $table->timestamps();
    });

    app(Cart::class)->add(EmailTestItem::create(['name' => 'Admission ticket']));
    app(Cart::class)->add(EmailTestItem::create(['name' => 'Gift certificate']));

    $this->post(route('checkout.store'), ['email' => 'parent@example.com']);
    $this->order = Order::sole();
});

test('a paid order queues one confirmation email to the buyer', function () {
    $this->post(URL::signedRoute('payments.complete', $this->order->payment), ['fake_result' => 'approve']);

    Mail::assertQueued(OrderConfirmation::class, 1);
    Mail::assertQueued(OrderConfirmation::class, fn (OrderConfirmation $mail) => $mail->hasTo('parent@example.com')
        && $mail->order->is($this->order));
});

test('the email has one line for each item, written by the item', function () {
    $this->post(URL::signedRoute('payments.complete', $this->order->payment), ['fake_result' => 'approve']);

    $mail = new OrderConfirmation($this->order->fresh());

    $mail->assertHasSubject('Your BLSC order '.$this->order->id);
    $mail->assertSeeInOrderInText([
        'Admission ticket: your code is ADMI-7',
        'Gift certificate: your code is GIFT-7',
        '$20.00',
    ]);
    $mail->assertSeeInHtml($this->order->url(), false);
});

test('a failed payment sends no email', function () {
    $this->post(URL::signedRoute('payments.complete', $this->order->payment), ['fake_result' => 'decline']);

    Mail::assertNothingQueued();
});

test('the same payment twice sends one email', function () {
    $this->post(URL::signedRoute('payments.complete', $this->order->payment), ['fake_result' => 'approve']);
    PaymentSucceeded::dispatch($this->order->payment);

    Mail::assertQueued(OrderConfirmation::class, 1);
});

test('the email is queued, so checkout does not wait for the mail server', function () {
    expect(new OrderConfirmation($this->order))->toBeInstanceOf(ShouldQueue::class);
});
