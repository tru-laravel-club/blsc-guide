<?php

use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;

/*
| Acceptance test for the "Ticket check-in" feature.
|
| Staff at the door type the code of a ticket. A paid ticket is marked as used,
| and the same ticket cannot get in twice.
*/

test('only staff open the check-in page', function () {
    $this->get(route('admin.check-in.show'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->get(route('admin.check-in.show'))
        ->assertForbidden();

    $this->actingAs(User::factory()->staff()->create())
        ->get(route('admin.check-in.show'))
        ->assertOk()
        ->assertSee('Code');
});

test('staff check in a ticket once', function () {
    $type = TicketType::factory()->create(['name' => 'BIG Night Out']);
    $ticket = Ticket::factory()->for($type)->create(['code' => 'K7P2M9QX']);

    $this->actingAs(User::factory()->staff()->create())
        ->followingRedirects()
        ->post(route('admin.check-in.store'), ['code' => 'K7P2M9QX'])
        ->assertSee('Checked in')
        ->assertSee('BIG Night Out');

    expect($ticket->fresh()->used_at)->not->toBeNull();
});

test('a second check-in of the same ticket shows that it was already used', function () {
    $ticket = Ticket::factory()->paid()->create();
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)->post(route('admin.check-in.store'), ['code' => $ticket->code]);
    $firstUse = $ticket->fresh()->used_at;

    $this->travel(5)->minutes();

    $this->actingAs($staff)
        ->post(route('admin.check-in.store'), ['code' => $ticket->code])
        ->assertSessionHasErrors(['code' => 'This ticket was already used at '.$firstUse->format('g:i A').'.']);

    expect($ticket->fresh()->used_at->equalTo($firstUse))->toBeTrue();
});

test('an unknown code shows that the ticket was not found', function () {
    $this->actingAs(User::factory()->staff()->create())
        ->post(route('admin.check-in.store'), ['code' => 'NOPE1234'])
        ->assertSessionHasErrors(['code' => 'Ticket not found. Check the code with the visitor.']);
});

test('staff can type the code in small letters with spaces', function () {
    $ticket = Ticket::factory()->create(['code' => 'K7P2M9QX']);

    $this->actingAs(User::factory()->staff()->create())
        ->post(route('admin.check-in.store'), ['code' => ' k7p2m9qx '])
        ->assertSessionHasNoErrors();

    expect($ticket->fresh()->used_at)->not->toBeNull();
});
