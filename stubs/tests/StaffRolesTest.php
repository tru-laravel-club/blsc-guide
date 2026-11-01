<?php

use App\Models\User;
use Illuminate\Support\Facades\Gate;

/*
| Acceptance test for the "Staff accounts" feature.
|
| A user is staff when is_staff is true. The access-admin gate is the one place
| that decides who can open the staff pages. Nobody can make themselves staff.
*/

test('a new user is not staff', function () {
    expect((new User)->is_staff)->toBeFalse()
        ->and(User::factory()->create()->fresh()->is_staff)->toBeFalse();
});

test('the staff factory state makes a staff user', function () {
    expect(User::factory()->staff()->create()->fresh()->is_staff)->toBeTrue();
});

test('only staff pass the access-admin gate', function () {
    expect(Gate::forUser(User::factory()->staff()->create())->allows('access-admin'))->toBeTrue()
        ->and(Gate::forUser(User::factory()->create())->allows('access-admin'))->toBeFalse();
});

test('a guest does not pass the access-admin gate', function () {
    expect(Gate::allows('access-admin'))->toBeFalse();
});

test('a person cannot make themselves staff when they register', function () {
    $this->post(route('register.store'), [
        'name' => 'Sam Sneaky',
        'email' => 'sam@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'is_staff' => '1',
    ])->assertSessionHasNoErrors();

    expect(User::firstWhere('email', 'sam@example.com')->is_staff)->toBeFalse();
});

test('the seeder makes a staff account and a parent account', function () {
    $this->seed();

    expect(User::firstWhere('email', 'staff@example.com')->is_staff)->toBeTrue()
        ->and(User::firstWhere('email', 'test@example.com')->is_staff)->toBeFalse();
});
