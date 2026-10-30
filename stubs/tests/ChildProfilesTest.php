<?php

use App\Models\Child;
use App\Models\User;
use Illuminate\Support\Carbon;

/*
| Acceptance test for the "Child profiles" feature.
|
| A parent adds each child once: a name, a birthdate and photo consent.
| A parent sees and changes only their own children. Staff do not see them here.
*/

test('guests are sent to the login page', function () {
    $this->get(route('children.index'))->assertRedirect(route('login'));
});

test('the account menu links to my children', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertSee('My children')
        ->assertSee(route('children.index'));
});

test('a parent sees only their own children, with their age', function () {
    $this->travelTo(Carbon::parse('2026-09-01'));
    $parent = User::factory()->create();
    Child::factory()->for($parent)->create(['name' => 'Sam Rivers', 'birthdate' => '2018-05-01']);
    Child::factory()->create(['name' => 'Alex Stone']);

    $this->actingAs($parent)
        ->get(route('children.index'))
        ->assertOk()
        ->assertSee('Sam Rivers')
        ->assertSee('Age 8')
        ->assertDontSee('Alex Stone');
});

test('the list explains when a parent has no children yet', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('children.index'))
        ->assertOk()
        ->assertSee('You have not added a child yet.');
});

test('a parent adds a child', function () {
    $parent = User::factory()->create();

    $this->actingAs($parent)->get(route('children.create'))->assertOk()->assertSee('Photo consent');

    $this->actingAs($parent)
        ->post(route('children.store'), [
            'name' => 'Sam Rivers',
            'birthdate' => '2018-05-01',
            'photo_consent' => '1',
        ])
        ->assertRedirect(route('children.index'));

    $child = $parent->children()->first();

    expect($child)->not->toBeNull()
        ->and($child->name)->toBe('Sam Rivers')
        ->and($child->birthdate->toDateString())->toBe('2018-05-01')
        ->and($child->photo_consent)->toBeTrue();
});

test('the child always belongs to the parent who is logged in', function () {
    $parent = User::factory()->create();
    $otherParent = User::factory()->create();

    $this->actingAs($parent)->post(route('children.store'), [
        'name' => 'Sam Rivers',
        'birthdate' => '2018-05-01',
        'user_id' => $otherParent->id,
    ]);

    expect($parent->children()->count())->toBe(1)
        ->and($otherParent->children()->count())->toBe(0);
});

test('a child needs a name and a birthdate in the past', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('children.store'), [
            'name' => '',
            'birthdate' => now()->addDay()->toDateString(),
        ])
        ->assertSessionHasErrors(['name', 'birthdate']);

    expect(Child::count())->toBe(0);
});

test('photo consent is no unless the parent says yes', function () {
    $parent = User::factory()->create();

    $this->actingAs($parent)->post(route('children.store'), [
        'name' => 'Sam Rivers',
        'birthdate' => '2018-05-01',
    ]);

    expect($parent->children()->first()->photo_consent)->toBeFalse();
});

test('a parent edits a child and can take back photo consent', function () {
    $parent = User::factory()->create();
    $child = Child::factory()->for($parent)->create(['name' => 'Sam', 'photo_consent' => true]);

    $this->actingAs($parent)->get(route('children.edit', $child))->assertOk()->assertSee('Sam');

    $this->actingAs($parent)
        ->put(route('children.update', $child), [
            'name' => 'Sam Rivers',
            'birthdate' => '2018-05-01',
            'photo_consent' => '0',
        ])
        ->assertRedirect(route('children.index'));

    expect($child->fresh())
        ->name->toBe('Sam Rivers')
        ->photo_consent->toBeFalse();
});

test('a parent deletes a child', function () {
    $parent = User::factory()->create();
    $child = Child::factory()->for($parent)->create();

    $this->actingAs($parent)
        ->delete(route('children.destroy', $child))
        ->assertRedirect(route('children.index'));

    expect($child->fresh())->toBeNull();
});

test('a parent cannot see or change the child of another family', function () {
    $child = Child::factory()->create(['name' => 'Alex']);
    $parent = User::factory()->create();

    $this->actingAs($parent)->get(route('children.edit', $child))->assertForbidden();

    $this->actingAs($parent)
        ->put(route('children.update', $child), ['name' => 'Changed', 'birthdate' => '2018-05-01'])
        ->assertForbidden();

    $this->actingAs($parent)->delete(route('children.destroy', $child))->assertForbidden();

    expect($child->fresh()->name)->toBe('Alex');
});

test('staff cannot open the profile of a child here', function () {
    $child = Child::factory()->create();

    $this->actingAs(User::factory()->staff()->create())
        ->get(route('children.edit', $child))
        ->assertForbidden();
});

test('deleting an account deletes its children', function () {
    $parent = User::factory()->create();
    Child::factory()->for($parent)->count(2)->create();

    $parent->delete();

    expect(Child::count())->toBe(0);
});

test('the age of a child goes up on the birthday', function () {
    $child = Child::factory()->make(['birthdate' => '2018-05-01']);

    expect($child->ageOn(Carbon::parse('2026-04-30')))->toBe(7)
        ->and($child->ageOn(Carbon::parse('2026-05-01')))->toBe(8);
});
