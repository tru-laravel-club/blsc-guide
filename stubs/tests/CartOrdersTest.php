<?php

use App\Cart;
use App\Contracts\Purchasable;
use App\Enums\OrderStatus;
use App\Events\OrderPaid;
use App\Events\PaymentSucceeded;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;

/*
| Acceptance test for the "Cart and orders" feature.
|
| Families put several things in one cart and pay once. The cart does not know
| what it sells: anything that implements Purchasable can go in it. The test
| uses its own small purchasable, CartTestItem, so it needs no other feature.
*/

class CartTestItem extends Model implements Purchasable
{
    protected $table = 'cart_test_items';

    protected $guarded = [];

    public function orderDescription(): string
    {
        return $this->name;
    }

    public function priceCents(?User $user): int
    {
        return $this->price_cents;
    }

    public function fulfil(): void
    {
        $this->increment('times_fulfilled');
    }

    public function confirmationLine(): string
    {
        return $this->name.' is ready.';
    }
}

beforeEach(function () {
    config(['payments.gateway' => 'fake']);

    Schema::create('cart_test_items', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->unsignedInteger('price_cents');
        $table->unsignedInteger('times_fulfilled')->default(0);
        $table->timestamps();
    });

    $this->kit = CartTestItem::create(['name' => 'Robot kit', 'price_cents' => 2500]);
    $this->ticket = CartTestItem::create(['name' => 'Admission ticket', 'price_cents' => 1500]);

    app(Cart::class)->add($this->kit);
    app(Cart::class)->add($this->ticket);
});

test('a visitor sees each item in the cart and the total', function () {
    $this->get(route('cart.show'))
        ->assertOk()
        ->assertSeeInOrder(['Robot kit', '$25.00', 'Admission ticket', '$15.00', '$40.00']);
});

test('a visitor removes an item from the cart', function () {
    $key = app(Cart::class)->items()->keys()->first();

    $this->delete(route('cart.remove', $key))->assertRedirect(route('cart.show'));

    expect(app(Cart::class)->items())->toHaveCount(1)
        ->and(app(Cart::class)->total(null))->toBe(1500);
});

test('a guest checks out with an email and goes to the payment', function () {
    $response = $this->post(route('checkout.store'), ['email' => 'parent@example.com']);

    $order = Order::sole();
    $payment = $order->payment;

    $response->assertRedirect($payment->url());
    expect($order->status)->toBe(OrderStatus::Pending)
        ->and($order->user_id)->toBeNull()
        ->and($order->email)->toBe('parent@example.com')
        ->and($order->total_cents)->toBe(4000)
        ->and($order->items)->toHaveCount(2)
        ->and($order->items->pluck('description')->all())->toBe(['Robot kit', 'Admission ticket'])
        ->and($payment->amount_cents)->toBe(4000)
        ->and($payment->user_id)->toBeNull()
        ->and(app(Cart::class)->items())->toBeEmpty();
});

test('a guest must give an email to check out', function () {
    $this->post(route('checkout.store'), ['email' => ''])->assertSessionHasErrors('email');

    expect(Order::count())->toBe(0);
});

test('a signed-in parent checks out with the email of their account', function () {
    $parent = User::factory()->create(['email' => 'maya.parent@example.com']);

    $this->actingAs($parent)->post(route('checkout.store'));

    $order = Order::sole();
    expect($order->user_id)->toBe($parent->id)
        ->and($order->email)->toBe('maya.parent@example.com')
        ->and($order->payment->user_id)->toBe($parent->id);
});

test('an empty cart cannot check out', function () {
    app(Cart::class)->clear();

    $this->post(route('checkout.store'), ['email' => 'parent@example.com'])
        ->assertRedirect(route('cart.show'))
        ->assertSessionHasErrors('cart');

    expect(Order::count())->toBe(0);
});

test('prices come from the server, not from the form', function () {
    $this->ticket->update(['price_cents' => 1800]);

    $this->post(route('checkout.store'), [
        'email' => 'parent@example.com',
        'total_cents' => 1,
        'price_cents' => 1,
    ]);

    expect(Order::sole()->total_cents)->toBe(4300)
        ->and(Order::sole()->items->pluck('price_cents')->all())->toBe([2500, 1800]);
});

test('paying the order fulfils every item and announces the order', function () {
    Event::fake([OrderPaid::class]);
    $this->post(route('checkout.store'), ['email' => 'parent@example.com']);
    $order = Order::sole();

    $this->post(URL::signedRoute('payments.complete', $order->payment), ['fake_result' => 'approve']);

    expect($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and($this->kit->fresh()->times_fulfilled)->toBe(1)
        ->and($this->ticket->fresh()->times_fulfilled)->toBe(1);
    Event::assertDispatched(OrderPaid::class, fn (OrderPaid $event) => $event->order->is($order));
});

test('a failed payment fulfils nothing', function () {
    Event::fake([OrderPaid::class]);
    $this->post(route('checkout.store'), ['email' => 'parent@example.com']);
    $order = Order::sole();

    $this->post(URL::signedRoute('payments.complete', $order->payment), ['fake_result' => 'decline']);

    expect($order->fresh()->status)->toBe(OrderStatus::Pending)
        ->and($this->kit->fresh()->times_fulfilled)->toBe(0);
    Event::assertNotDispatched(OrderPaid::class);
});

test('an order is fulfilled only once', function () {
    $this->post(route('checkout.store'), ['email' => 'parent@example.com']);
    $payment = Order::sole()->payment;

    PaymentSucceeded::dispatch($payment);
    PaymentSucceeded::dispatch($payment);

    expect($this->kit->fresh()->times_fulfilled)->toBe(1);
});

test('the order page needs its signed link and shows each item', function () {
    $this->post(route('checkout.store'), ['email' => 'parent@example.com']);
    $order = Order::sole();
    $this->post(URL::signedRoute('payments.complete', $order->payment), ['fake_result' => 'approve']);

    $this->get(route('orders.show', $order))->assertForbidden();

    $this->get($order->url())
        ->assertOk()
        ->assertSee('Paid')
        ->assertSee('Robot kit is ready.')
        ->assertSee('Admission ticket is ready.')
        ->assertSee('$40.00');
});

test('a parent cannot open another parent\'s order', function () {
    $this->actingAs(User::factory()->create())->post(route('checkout.store'));
    $order = Order::sole();

    $this->actingAs(User::factory()->create())
        ->get($order->url())
        ->assertForbidden();
});
