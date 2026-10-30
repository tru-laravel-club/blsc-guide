<?php

use App\Enums\FieldScope;
use App\Models\Child;
use App\Models\Field;
use App\Models\Form;
use App\Models\Membership;
use App\Models\Order;
use App\Models\Program;
use App\Models\ProgramSession;
use App\Models\User;
use App\Pricing;
use Illuminate\Support\Carbon;

/*
| Acceptance test for the "Member pricing" feature.
|
| A session can have a member price. A parent pays it when the family has an
| active membership on the first day of the session. The server decides the
| price, and one class holds the rule.
*/

beforeEach(function () {
    config(['payments.gateway' => 'fake']);
    $this->travelTo(Carbon::parse('2026-11-15 10:00'));
});

/**
 * Make a session that starts on December 1, 2026, costs $150.00, and $135.00 for members.
 * Its program takes registrations with a form that asks one optional question.
 *
 * @param  array<string, mixed>  $attributes
 */
function memberPricedSession(array $attributes = []): ProgramSession
{
    $form = Form::factory()->create();
    $form->fields()->attach(Field::factory()->create(['key' => 'school', 'scope' => FieldScope::Child]), ['position' => 0, 'required' => false]);
    $form->publish();

    $program = Program::factory()->create(['signup_mode' => 'register', 'form_id' => $form->id, 'age_min' => null, 'age_max' => null]);

    return ProgramSession::factory()->for($program)->create([
        'starts_on' => '2026-12-01',
        'ends_on' => '2027-02-02',
        'price_cents' => 15000,
        'member_price_cents' => 13500,
        'registration_opens_at' => '2026-11-01 09:00',
        ...$attributes,
    ]);
}

/**
 * Register a new child of this parent for the session, check out, and return the order.
 */
function checkOutRegistration(User $parent, ProgramSession $session): Order
{
    $child = Child::factory()->for($parent)->create();

    test()->actingAs($parent)->post(route('registrations.store', [
        'category' => $session->program->category,
        'program' => $session->program,
        'session' => $session,
        'child' => $child->id,
    ]), ['school' => 'Westsyde Elementary']);

    test()->actingAs($parent)->post(route('checkout.store'));

    return Order::latest('id')->firstOrFail();
}

test('a member pays the member price for a registration', function () {
    $session = memberPricedSession();
    $parent = User::factory()->create();
    Membership::factory()->for($parent)->create(['starts_on' => '2026-01-10', 'ends_on' => '2027-01-09']);

    $order = checkOutRegistration($parent, $session);

    expect($order->total_cents)->toBe(13500)
        ->and($order->items->sole()->price_cents)->toBe(13500);
});

test('a parent without a membership pays the normal price', function () {
    $order = checkOutRegistration(User::factory()->create(), memberPricedSession());

    expect($order->total_cents)->toBe(15000);
});

test('a membership that ends before the first day of the session does not count', function () {
    $session = memberPricedSession();
    $parent = User::factory()->create();
    Membership::factory()->for($parent)->create(['starts_on' => '2025-12-01', 'ends_on' => '2026-11-30']);

    expect($parent->activeMembership())->not->toBeNull();

    $order = checkOutRegistration($parent, $session);

    expect($order->total_cents)->toBe(15000);
});

test('a membership that starts before the first day of the session counts, even if it starts after today', function () {
    $session = memberPricedSession();
    $parent = User::factory()->create();
    Membership::factory()->for($parent)->create(['starts_on' => '2026-11-20', 'ends_on' => '2027-11-19']);

    expect($parent->activeMembership())->toBeNull();

    $order = checkOutRegistration($parent, $session);

    expect($order->total_cents)->toBe(13500);
});

test('a membership that nobody has paid for does not count', function () {
    $session = memberPricedSession();
    $parent = User::factory()->create();
    Membership::factory()->for($parent)->unpaid()->create();

    expect(checkOutRegistration($parent, $session)->total_cents)->toBe(15000);
});

test('a session without a member price charges members the normal price', function () {
    $session = memberPricedSession(['member_price_cents' => null]);
    $parent = User::factory()->create();
    Membership::factory()->for($parent)->create(['starts_on' => '2026-01-10', 'ends_on' => '2027-01-09']);

    expect(checkOutRegistration($parent, $session)->total_cents)->toBe(15000);
});

test('one class decides the price for every buyer', function () {
    $session = memberPricedSession();
    $member = User::factory()->create();
    Membership::factory()->for($member)->create(['starts_on' => '2026-01-10', 'ends_on' => '2027-01-09']);

    expect(Pricing::for($session, $member))->toBe(13500)
        ->and(Pricing::for($session, User::factory()->create()))->toBe(15000)
        ->and(Pricing::for($session, null))->toBe(15000);
});

test('the registration page shows a member the price that they pay', function () {
    $session = memberPricedSession();
    $parent = User::factory()->create();
    Membership::factory()->for($parent)->create(['starts_on' => '2026-01-10', 'ends_on' => '2027-01-09']);

    $this->actingAs($parent)
        ->get(route('registrations.create', ['category' => $session->program->category, 'program' => $session->program, 'session' => $session]))
        ->assertOk()
        ->assertSee('$135.00')
        ->assertSee('member price');
});
