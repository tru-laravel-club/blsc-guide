<?php

use App\Cart;
use App\Models\Order;
use App\Models\ProgramSession;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Support\Facades\URL;

/*
| Acceptance test for the "Tickets" feature.
|
| Visitors buy admission and event tickets without an account. A ticket goes
| through the cart, and it gets its code only when the order is paid.
*/

beforeEach(function () {
    config(['payments.gateway' => 'fake']);
});

test('visitors see the ticket types that are on sale', function () {
    TicketType::factory()->create(['name' => 'General admission', 'price_cents' => 1200]);
    TicketType::factory()->forSession(ProgramSession::factory()->create(['label' => 'BIG Night Out, March']))->create(['name' => 'BIG Night Out']);
    TicketType::factory()->forSession(ProgramSession::factory()->past()->create())->create(['name' => 'Last year\'s gala']);

    $this->get(route('tickets.index'))
        ->assertOk()
        ->assertSee('General admission')
        ->assertSee('$12.00')
        ->assertSee('BIG Night Out, March')
        ->assertDontSee('Last year\'s gala');
});

test('a guest buys two tickets, and each paid ticket has its own code', function () {
    $type = TicketType::factory()->create(['name' => 'General admission', 'price_cents' => 1200]);

    $this->post(route('tickets.store'), ['ticket_type_id' => $type->id, 'quantity' => 2])
        ->assertRedirect(route('cart.show'));

    expect(Ticket::count())->toBe(2)
        ->and(Ticket::whereNotNull('code')->count())->toBe(0)
        ->and(app(Cart::class)->total(null))->toBe(2400);

    $this->post(route('checkout.store'), ['email' => 'visitor@example.com']);
    $order = Order::sole();
    $this->post(URL::signedRoute('payments.complete', $order->payment), ['fake_result' => 'approve']);

    $codes = Ticket::pluck('code');
    expect($codes->filter())->toHaveCount(2)
        ->and($codes->unique())->toHaveCount(2);

    $this->get($order->url())
        ->assertSee($codes[0])
        ->assertSee($codes[1]);
});

test('a ticket costs the price of its type, whatever the form says', function () {
    $type = TicketType::factory()->create(['price_cents' => 1200]);

    $this->post(route('tickets.store'), ['ticket_type_id' => $type->id, 'quantity' => 1, 'price_cents' => 1]);

    expect(app(Cart::class)->total(null))->toBe(1200);
});

test('a sold-out ticket type is refused', function () {
    $type = TicketType::factory()->create(['capacity' => 2]);
    Ticket::factory()->for($type)->paid()->count(2)->create();

    $this->post(route('tickets.store'), ['ticket_type_id' => $type->id, 'quantity' => 1])
        ->assertSessionHasErrors('quantity');

    $this->get(route('tickets.index'))->assertSee('Sold out');
});

test('a visitor cannot buy more tickets than are left', function () {
    $type = TicketType::factory()->create(['capacity' => 3]);
    Ticket::factory()->for($type)->paid()->create();
    Ticket::factory()->for($type)->create();

    $this->post(route('tickets.store'), ['ticket_type_id' => $type->id, 'quantity' => 3])
        ->assertSessionHasErrors('quantity');

    $this->post(route('tickets.store'), ['ticket_type_id' => $type->id, 'quantity' => 2])
        ->assertSessionHasNoErrors();
});

test('a ticket for a session that has started cannot be bought', function () {
    $type = TicketType::factory()->forSession(ProgramSession::factory()->past()->create())->create();

    $this->post(route('tickets.store'), ['ticket_type_id' => $type->id, 'quantity' => 1])
        ->assertSessionHasErrors('ticket_type_id');
});

test('only staff manage ticket types', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.ticket-types.index'))
        ->assertForbidden();
});

test('staff create a ticket type with a price in dollars', function () {
    $session = ProgramSession::factory()->create();

    $this->actingAs(User::factory()->staff()->create())
        ->post(route('admin.ticket-types.store'), [
            'name' => 'BIG Night Out',
            'price' => '35.50',
            'program_session_id' => $session->id,
            'capacity' => 80,
        ])
        ->assertRedirect(route('admin.ticket-types.index'));

    expect(TicketType::sole())
        ->price_cents->toBe(3550)
        ->program_session_id->toBe($session->id)
        ->capacity->toBe(80);
});

test('staff cannot delete a ticket type that has sold tickets', function () {
    $type = TicketType::factory()->create();
    Ticket::factory()->for($type)->paid()->create();

    $this->actingAs(User::factory()->staff()->create())
        ->delete(route('admin.ticket-types.destroy', $type))
        ->assertSessionHasErrors('ticket_type');

    expect($type->fresh())->not->toBeNull();
});
