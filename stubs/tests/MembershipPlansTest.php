<?php

use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/*
| Acceptance test for the "Membership plans" feature.
|
| Staff manage the plans. A parent buys a plan through the cart. The membership
| gets its dates when the order is paid, and a renewal starts when the current
| membership ends.
*/

beforeEach(function () {
    config(['payments.gateway' => 'fake']);
    $this->travelTo(Carbon::parse('2026-11-15 10:00'));
});

/**
 * Buy a plan as this parent and pay with the fake gateway.
 */
function buyMembership(User $parent, MembershipPlan $plan): void
{
    test()->actingAs($parent)->post(route('memberships.store'), ['membership_plan_id' => $plan->id]);
    test()->actingAs($parent)->post(route('checkout.store'));
    test()->post(URL::signedRoute('payments.complete', Order::latest('id')->first()->payment), ['fake_result' => 'approve']);
}

test('visitors see the active plans in the order that staff chose', function () {
    MembershipPlan::factory()->create(['name' => 'Individual', 'position' => 2, 'price_cents' => 4500]);
    MembershipPlan::factory()->create(['name' => 'Family', 'position' => 1, 'price_cents' => 9500]);
    MembershipPlan::factory()->create(['name' => 'Founders', 'is_active' => false]);

    $this->get(route('memberships.index'))
        ->assertOk()
        ->assertSeeInOrder(['Family', '$95.00', 'Individual', '$45.00'])
        ->assertDontSee('Founders');
});

test('a guest must log in to buy a membership', function () {
    $plan = MembershipPlan::factory()->create();

    $this->post(route('memberships.store'), ['membership_plan_id' => $plan->id])
        ->assertRedirect(route('login'));
});

test('a parent buys a membership, and it starts on the day of the payment', function () {
    $parent = User::factory()->create();
    $plan = MembershipPlan::factory()->create(['name' => 'Family', 'months' => 12, 'price_cents' => 9500]);

    $this->actingAs($parent)
        ->post(route('memberships.store'), ['membership_plan_id' => $plan->id])
        ->assertRedirect(route('cart.show'));

    expect($parent->activeMembership())->toBeNull();

    buyMembership($parent, $plan);

    $membership = $parent->activeMembership();
    expect($membership)->not->toBeNull()
        ->and($membership->plan->is($plan))->toBeTrue()
        ->and($membership->starts_on->toDateString())->toBe('2026-11-15')
        ->and($membership->ends_on->toDateString())->toBe('2027-11-14');
});

test('a renewal before the end starts the day after the current membership ends', function () {
    $parent = User::factory()->create();
    $plan = MembershipPlan::factory()->create(['months' => 12]);
    Membership::factory()->for($parent)->for($plan, 'plan')->create(['starts_on' => '2025-11-25', 'ends_on' => '2026-11-24']);

    buyMembership($parent, $plan);

    $renewal = $parent->memberships()->latest('id')->first();
    expect($renewal->starts_on->toDateString())->toBe('2026-11-25')
        ->and($renewal->ends_on->toDateString())->toBe('2027-11-24');
});

test('a renewal after the end starts on the day of the payment', function () {
    $parent = User::factory()->create();
    $plan = MembershipPlan::factory()->create(['months' => 6]);
    Membership::factory()->for($parent)->for($plan, 'plan')->create(['starts_on' => '2025-01-10', 'ends_on' => '2026-01-09']);

    buyMembership($parent, $plan);

    $renewal = $parent->memberships()->latest('id')->first();
    expect($renewal->starts_on->toDateString())->toBe('2026-11-15')
        ->and($renewal->ends_on->toDateString())->toBe('2027-05-14');
});

test('an inactive plan cannot be bought', function () {
    $plan = MembershipPlan::factory()->create(['is_active' => false]);

    $this->actingAs(User::factory()->create())
        ->post(route('memberships.store'), ['membership_plan_id' => $plan->id])
        ->assertSessionHasErrors('membership_plan_id');

    expect(Membership::count())->toBe(0);
});

test('a membership is active only between its dates', function () {
    $parent = User::factory()->create();
    Membership::factory()->for($parent)->create(['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);

    expect($parent->activeMembership())->not->toBeNull()
        ->and($parent->activeMembership(Carbon::parse('2026-12-31')))->not->toBeNull()
        ->and($parent->activeMembership(Carbon::parse('2027-01-01')))->toBeNull()
        ->and($parent->activeMembership(Carbon::parse('2025-12-31')))->toBeNull();
});

test('the memberships page shows the parent\'s current membership', function () {
    $parent = User::factory()->create();
    $plan = MembershipPlan::factory()->create(['name' => 'Family']);
    Membership::factory()->for($parent)->for($plan, 'plan')->create(['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);

    $this->actingAs($parent)
        ->get(route('memberships.index'))
        ->assertSee('Your Family membership is active until Dec 31, 2026.');
});

test('only staff manage membership plans', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.membership-plans.index'))
        ->assertForbidden();
});

test('staff create a plan with a price in dollars', function () {
    $this->actingAs(User::factory()->staff()->create())
        ->post(route('admin.membership-plans.store'), [
            'name' => 'Grandparent',
            'description' => 'Two adults and their grandchildren.',
            'price' => '80.00',
            'months' => 12,
            'is_active' => '1',
            'position' => 3,
        ])
        ->assertRedirect(route('admin.membership-plans.index'));

    expect(MembershipPlan::sole())
        ->price_cents->toBe(8000)
        ->months->toBe(12)
        ->is_active->toBeTrue();
});

test('staff stop selling a plan without deleting it', function () {
    $plan = MembershipPlan::factory()->create(['name' => 'Family']);

    $this->actingAs(User::factory()->staff()->create())
        ->put(route('admin.membership-plans.update', $plan), [
            'name' => 'Family',
            'price' => '95.00',
            'months' => 12,
            'position' => 1,
        ])
        ->assertRedirect(route('admin.membership-plans.index'));

    expect($plan->fresh()->is_active)->toBeFalse();
});
