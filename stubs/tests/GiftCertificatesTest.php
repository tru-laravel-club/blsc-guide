<?php

use App\Cart;
use App\Models\GiftCertificate;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\URL;

/*
| Acceptance test for the "Gift certificates" feature.
|
| A visitor buys a gift certificate through the cart, without an account. The
| certificate gets its code when the order is paid. Staff redeem a code once.
*/

beforeEach(function () {
    config(['payments.gateway' => 'fake']);
});

test('a visitor buys a gift certificate and gets its code after the payment', function () {
    $this->post(route('gift-certificates.store'), [
        'amount' => '50',
        'recipient_name' => 'Maya',
        'message' => 'Happy birthday!',
    ])->assertRedirect(route('cart.show'));

    $certificate = GiftCertificate::sole();
    expect($certificate->code)->toBeNull()
        ->and($certificate->amount_cents)->toBe(5000)
        ->and(app(Cart::class)->total(null))->toBe(5000);

    $this->post(route('checkout.store'), ['email' => 'aunt@example.com']);
    $order = Order::sole();
    $this->post(URL::signedRoute('payments.complete', $order->payment), ['fake_result' => 'approve']);

    $code = $certificate->fresh()->code;
    expect($code)->not->toBeNull();

    $this->get($order->url())
        ->assertSee($code)
        ->assertSee('Maya');
});

test('the amount must be between $10 and $500, and the recipient is required', function () {
    $this->post(route('gift-certificates.store'), ['amount' => '5', 'recipient_name' => ''])
        ->assertSessionHasErrors(['amount', 'recipient_name']);

    $this->post(route('gift-certificates.store'), ['amount' => '501', 'recipient_name' => 'Maya'])
        ->assertSessionHasErrors('amount');

    expect(GiftCertificate::count())->toBe(0);
});

test('visitors see the gift certificate form', function () {
    $this->get(route('gift-certificates.create'))
        ->assertOk()
        ->assertSee('Recipient');
});

test('only staff open the gift certificate desk', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.gift-certificates.index'))
        ->assertForbidden();
});

test('staff find a certificate by its code', function () {
    GiftCertificate::factory()->paid()->create(['code' => 'K7P2M9QXRT', 'recipient_name' => 'Maya', 'amount_cents' => 5000]);

    $this->actingAs(User::factory()->staff()->create())
        ->get(route('admin.gift-certificates.index', ['code' => 'k7p2m9qxrt ']))
        ->assertOk()
        ->assertSee('Maya')
        ->assertSee('$50.00')
        ->assertSee('Redeem');
});

test('staff redeem a certificate once, and a second redeem is refused', function () {
    $certificate = GiftCertificate::factory()->paid()->create(['code' => 'K7P2M9QXRT']);
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)
        ->post(route('admin.gift-certificates.redeem', $certificate))
        ->assertSessionHas('status');

    expect($certificate->fresh()->redeemed_at)->not->toBeNull();

    $this->actingAs($staff)
        ->post(route('admin.gift-certificates.redeem', $certificate))
        ->assertSessionHasErrors('code');

    $this->actingAs($staff)
        ->get(route('admin.gift-certificates.index', ['code' => 'K7P2M9QXRT']))
        ->assertSee('Redeemed on');
});

test('an unknown code is not found', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)
        ->get(route('admin.gift-certificates.index', ['code' => 'NOPE123456']))
        ->assertOk()
        ->assertSee('No gift certificate has this code.');

    $this->actingAs($staff)
        ->post(route('admin.gift-certificates.redeem', 'NOPE123456'))
        ->assertNotFound();
});

test('an unpaid certificate cannot be found or redeemed', function () {
    GiftCertificate::factory()->create(['recipient_name' => 'Maya']);

    $this->actingAs(User::factory()->staff()->create())
        ->get(route('admin.gift-certificates.index', ['code' => '']))
        ->assertDontSee('Maya');
});
