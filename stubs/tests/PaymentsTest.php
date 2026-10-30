<?php

use App\Enums\PaymentStatus;
use App\Events\PaymentSucceeded;
use App\Models\Payment;
use App\Models\User;
use App\Payments\FakeGateway;
use App\Payments\MonerisGateway;
use App\Payments\PaymentGateway;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;

/*
| Acceptance test for the "Online payments" feature.
|
| A payment pays for one record, such as an order. The payer opens it with a
| signed link, so a guest can pay without an account. The server, not the
| browser, decides that a payment passed.
*/

beforeEach(function () {
    config([
        'payments.gateway' => 'fake',
        'payments.moneris' => [
            'store_id' => 'store2',
            'api_token' => 'test-token',
            'checkout_id' => 'chkt-test',
            'environment' => 'qa',
        ],
    ]);
});

test('a payment link works only with its signature', function () {
    $payment = Payment::factory()->create(['user_id' => null]);

    $this->get(route('payments.show', $payment))->assertForbidden();
    $this->get(URL::signedRoute('payments.show', $payment))->assertOk();
});

test('a guest opens a guest payment and sees the checkout', function () {
    $payment = Payment::factory()->create(['user_id' => null, 'amount_cents' => 2500]);

    $this->get($payment->url())
        ->assertOk()
        ->assertSee('$25.00');

    expect($payment->fresh()->reference)->not->toBeNull();
});

test('a parent must log in to open their own payment', function () {
    $payment = Payment::factory()->create();

    $this->get($payment->url())->assertForbidden();

    $this->actingAs($payment->user)->get($payment->url())->assertOk();
});

test('a parent cannot open another parent\'s payment, even with the link', function () {
    $payment = Payment::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get($payment->url())
        ->assertForbidden();
});

test('an approved payment is marked as paid and announced', function () {
    Event::fake([PaymentSucceeded::class]);
    $payment = Payment::factory()->create(['user_id' => null]);

    $this->post(URL::signedRoute('payments.complete', $payment), ['fake_result' => 'approve'])
        ->assertRedirect($payment->url());

    $payment->refresh();
    expect($payment->status)->toBe(PaymentStatus::Paid)
        ->and($payment->gateway)->toBe('fake')
        ->and($payment->paid_at)->not->toBeNull();
    Event::assertDispatched(PaymentSucceeded::class, fn (PaymentSucceeded $event) => $event->payment->is($payment));
});

test('a declined payment is marked as failed, and the payer can try again', function () {
    Event::fake([PaymentSucceeded::class]);
    $payment = Payment::factory()->create(['user_id' => null]);

    $this->post(URL::signedRoute('payments.complete', $payment), ['fake_result' => 'decline']);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Failed);
    Event::assertNotDispatched(PaymentSucceeded::class);

    $this->get($payment->url())
        ->assertOk()
        ->assertSee('did not go through');
});

test('a paid payment is not completed twice', function () {
    Event::fake([PaymentSucceeded::class]);
    $payment = Payment::factory()->create(['return_url' => 'https://example.com/orders/7']);
    $payment->forceFill(['status' => PaymentStatus::Paid, 'paid_at' => now()])->save();

    $this->actingAs($payment->user)
        ->post(URL::signedRoute('payments.complete', $payment), ['fake_result' => 'approve']);

    $this->actingAs($payment->user)
        ->get($payment->url())
        ->assertSee('Payment received')
        ->assertSee('https://example.com/orders/7');

    Event::assertNotDispatched(PaymentSucceeded::class);
});

test('the config chooses the gateway', function () {
    expect(app(PaymentGateway::class))->toBeInstanceOf(FakeGateway::class);

    config(['payments.gateway' => 'moneris']);

    expect(app(PaymentGateway::class))->toBeInstanceOf(MonerisGateway::class);
});

test('the fake gateway cannot run in production', function () {
    app()->detectEnvironment(fn () => 'production');

    app(PaymentGateway::class);
})->throws(RuntimeException::class);

test('Moneris starts a checkout with a preload request', function () {
    Http::fake(['gatewayt.moneris.com/*' => Http::response(['response' => ['success' => 'true', 'ticket' => 'ticket-123']])]);
    $payment = Payment::factory()->create(['amount_cents' => 2500]);

    app(MonerisGateway::class, ['config' => config('payments.moneris')])->start($payment);

    expect($payment->fresh()->reference)->toBe('ticket-123');
    Http::assertSent(fn (Request $request) => $request->url() === 'https://gatewayt.moneris.com/chktv2/request/request.php'
        && $request['action'] === 'preload'
        && $request['txn_total'] === '25.00'
        && $request['environment'] === 'qa');
});

test('Moneris refuses to start when the preload fails', function () {
    Http::fake(['gatewayt.moneris.com/*' => Http::response(['response' => ['success' => 'false']])]);

    app(MonerisGateway::class, ['config' => config('payments.moneris')])->start(Payment::factory()->create());
})->throws(Exception::class);

test('Moneris confirms only an approved receipt for the right amount', function (string $result, string $amount, bool $expected) {
    Http::fake(['gatewayt.moneris.com/*' => Http::response(['response' => [
        'success' => 'true',
        'receipt' => ['result' => $result, 'cc' => ['amount' => $amount]],
    ]])]);
    $payment = Payment::factory()->create(['amount_cents' => 2500]);
    $payment->forceFill(['reference' => 'ticket-123'])->save();

    expect(app(MonerisGateway::class, ['config' => config('payments.moneris')])->confirm($payment))->toBe($expected);

    Http::assertSent(fn (Request $request) => $request['action'] === 'receipt' && $request['ticket'] === 'ticket-123');
})->with([
    'approved' => ['a', '25.00', true],
    'declined' => ['d', '25.00', false],
    'wrong amount' => ['a', '1.00', false],
]);
