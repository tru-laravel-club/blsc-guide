<?php

use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\User;
use Illuminate\Support\Carbon;

/*
| Acceptance test for the "Member lookup" feature.
|
| At the front desk, staff search for a member by name or email and see the
| plan and the end date of the membership.
*/

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-11-15 10:00'));
    $this->staff = User::factory()->staff()->create(['name' => 'Desk Staff', 'email' => 'desk@example.com']);
});

test('only staff search for members', function () {
    $this->get(route('admin.members.index'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->get(route('admin.members.index'))
        ->assertForbidden();
});

test('staff find an active member by name', function () {
    $parent = User::factory()->create(['name' => 'Priya Sharma']);
    $plan = MembershipPlan::factory()->create(['name' => 'Family']);
    Membership::factory()->for($parent)->for($plan, 'plan')->create(['starts_on' => '2026-03-01', 'ends_on' => '2027-02-28']);

    $this->actingAs($this->staff)
        ->get(route('admin.members.index', ['search' => 'priya']))
        ->assertOk()
        ->assertSee('Priya Sharma')
        ->assertSee('Family')
        ->assertSee('Feb 28, 2027')
        ->assertSee('Active');
});

test('staff find an expired member by email and see when it ended', function () {
    $parent = User::factory()->create(['name' => 'Tom Lee', 'email' => 'tom.lee@example.com']);
    Membership::factory()->for($parent)->create(['starts_on' => '2025-01-01', 'ends_on' => '2025-12-31']);

    $this->actingAs($this->staff)
        ->get(route('admin.members.index', ['search' => 'tom.lee@']))
        ->assertSee('Tom Lee')
        ->assertSee('Dec 31, 2025')
        ->assertSee('Expired');
});

test('the latest membership counts after a renewal', function () {
    $parent = User::factory()->create(['name' => 'Ana Silva']);
    Membership::factory()->for($parent)->create(['starts_on' => '2025-01-01', 'ends_on' => '2025-12-31']);
    Membership::factory()->for($parent)->create(['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);

    $this->actingAs($this->staff)
        ->get(route('admin.members.index', ['search' => 'Ana']))
        ->assertSee('Dec 31, 2026')
        ->assertDontSee('Dec 31, 2025')
        ->assertSee('Active');
});

test('a search with no member shows no result', function () {
    $this->actingAs($this->staff)
        ->get(route('admin.members.index', ['search' => 'nobody']))
        ->assertOk()
        ->assertSee('No member matches');
});

test('people without a paid membership are not members', function () {
    $parent = User::factory()->create(['name' => 'Sam Unpaid']);
    Membership::factory()->for($parent)->unpaid()->create();
    User::factory()->create(['name' => 'Sam Visitor']);

    $this->actingAs($this->staff)
        ->get(route('admin.members.index', ['search' => 'Sam']))
        ->assertDontSee('Sam Unpaid')
        ->assertDontSee('Sam Visitor')
        ->assertSee('No member matches');
});
