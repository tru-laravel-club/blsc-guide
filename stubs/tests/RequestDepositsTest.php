<?php

use App\Cart;
use App\Models\Deposit;
use App\Models\Inquiry;
use App\Models\Order;
use App\Models\Program;
use App\Models\User;
use App\Notifications\DepositRequested;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;

/*
| Acceptance test for the "Request deposits" feature.
|
| Staff ask for a deposit on a request, such as a birthday party. The visitor
| gets an email with a signed link. The link puts the deposit in the cart, so a
| guest pays without an account. The default amount comes from config.
*/

beforeEach(function () {
    config(['payments.gateway' => 'fake', 'blsc.deposit_cents' => 7500]);
    Notification::fake();
    $this->inquiry = Inquiry::factory()
        ->for(Program::factory()->state(['name' => 'Birthday Parties', 'signup_mode' => 'request']))
        ->create(['name' => 'Maya Patel', 'email' => 'maya@example.com']);
});

/**
 * Ask for a deposit on the request as a staff member.
 */
function askForDeposit(Inquiry $inquiry, mixed $amount): TestResponse
{
    return test()->actingAs(User::factory()->staff()->create())
        ->post(route('admin.inquiries.deposit.store', $inquiry), ['amount' => $amount]);
}

test('only staff can ask for a deposit', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('admin.inquiries.deposit.store', $this->inquiry), ['amount' => 75])
        ->assertForbidden();

    expect(Deposit::count())->toBe(0);
});

test('the request page suggests the default amount from config', function () {
    $this->actingAs(User::factory()->staff()->create())
        ->get(route('admin.inquiries.show', $this->inquiry))
        ->assertOk()
        ->assertSee('Ask for a deposit')
        ->assertSee('75.00');
});

test('staff ask for a deposit and the visitor gets a queued email', function () {
    askForDeposit($this->inquiry, '75.00')->assertRedirect(route('admin.inquiries.show', $this->inquiry));

    $deposit = $this->inquiry->deposit;

    expect($deposit->amount_cents)->toBe(7500)
        ->and($deposit->paid_at)->toBeNull()
        ->and(new DepositRequested($deposit))->toBeInstanceOf(ShouldQueue::class);

    Notification::assertSentOnDemand(
        DepositRequested::class,
        fn (DepositRequested $notification, array $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === 'maya@example.com',
    );
});

test('the link in the email puts the deposit in the cart of a guest', function () {
    askForDeposit($this->inquiry, 75);
    $deposit = $this->inquiry->deposit;

    $url = (new DepositRequested($deposit))->toMail(new AnonymousNotifiable)->actionUrl;
    expect($url)->toBe($deposit->payUrl());

    auth()->logout();

    $this->get($url)->assertRedirect(route('cart.show'));

    expect(app(Cart::class)->total(null))->toBe(7500);

    $this->get(route('cart.show'))->assertSee('Birthday')->assertSee('$75.00');
});

test('a tampered or unsigned link is refused', function () {
    askForDeposit($this->inquiry, 75);
    $deposit = $this->inquiry->deposit;
    $other = Deposit::factory()->create();

    auth()->logout();

    $this->get(str_replace("/deposits/{$deposit->id}/", "/deposits/{$other->id}/", $deposit->payUrl()))->assertForbidden();
    $this->get(route('deposits.pay', $deposit))->assertForbidden();

    expect(app(Cart::class)->count())->toBe(0);
});

test('paying the order marks the deposit as paid', function () {
    askForDeposit($this->inquiry, 75);
    $deposit = $this->inquiry->deposit;
    auth()->logout();

    $this->get($deposit->payUrl());
    $this->post(route('checkout.store'), ['email' => 'maya@example.com']);
    $order = Order::sole();
    $this->post(URL::signedRoute('payments.complete', $order->payment), ['fake_result' => 'approve']);

    expect($deposit->fresh()->paid_at)->not->toBeNull()
        ->and($order->fresh()->total_cents)->toBe(7500);

    $this->get($order->url())->assertSee('Deposit paid');
});

test('staff can change the amount before the visitor pays', function () {
    askForDeposit($this->inquiry, 50);
    askForDeposit($this->inquiry, '80.50');

    expect(Deposit::count())->toBe(1)
        ->and($this->inquiry->deposit->amount_cents)->toBe(8050);

    Notification::assertSentOnDemandTimes(DepositRequested::class, 2);

    auth()->logout();
    $this->get($this->inquiry->deposit->payUrl());

    expect(app(Cart::class)->total(null))->toBe(8050);
});

test('a paid deposit cannot change or go in the cart again', function () {
    $deposit = Deposit::factory()->for($this->inquiry)->paid()->create(['amount_cents' => 7500]);

    askForDeposit($this->inquiry, 100)->assertSessionHasErrors('amount');

    expect($deposit->fresh()->amount_cents)->toBe(7500);
    Notification::assertNothingSent();

    auth()->logout();
    $this->get($deposit->payUrl())->assertRedirect();

    expect(app(Cart::class)->count())->toBe(0);
});

test('the amount must be a positive number of dollars', function (mixed $amount) {
    askForDeposit($this->inquiry, $amount)->assertSessionHasErrors('amount');

    expect(Deposit::count())->toBe(0);
})->with([
    'empty' => [''],
    'zero' => [0],
    'not a number' => ['fifty'],
    'too many decimals' => ['10.555'],
]);
