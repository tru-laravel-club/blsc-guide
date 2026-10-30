<?php

use App\Enums\QuarksApplicationStatus;
use App\Models\QuarksApplication;
use App\Models\User;

function quarksApplicationData(array $overrides = []): array
{
    return array_merge([
        'student_name' => 'Sam Lee',
        'school' => 'South Kamloops Secondary',
        'grade' => 8,
        'reason' => 'I want to do real experiments in a university lab.',
    ], $overrides);
}

test('guests must log in to apply', function () {
    $this->get(route('quarks.apply'))->assertRedirect(route('login'));
});

test('a user can submit an application and it starts as pending', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('quarks.apply.store'), quarksApplicationData())
        ->assertRedirect();

    expect(QuarksApplication::sole())
        ->user_id->toBe($user->id)
        ->student_name->toBe('Sam Lee')
        ->status->toBe(QuarksApplicationStatus::Pending);
});

test('only Grade 8 and 9 students can apply', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('quarks.apply.store'), quarksApplicationData(['grade' => 10]))
        ->assertSessionHasErrors('grade');
});

test('an applicant cannot set the status', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('quarks.apply.store'), quarksApplicationData(['status' => 'accepted']));

    expect(QuarksApplication::sole()->status)->toBe(QuarksApplicationStatus::Pending);
});

test('users who are not staff cannot see the applications', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.quarks-applications.index'))
        ->assertForbidden();
});

test('staff see the applications', function () {
    QuarksApplication::factory()->create(['student_name' => 'Sam Lee']);

    $this->actingAs(User::factory()->staff()->create())
        ->get(route('admin.quarks-applications.index'))
        ->assertOk()
        ->assertSee('Sam Lee');
});

test('staff can accept an application', function () {
    $application = QuarksApplication::factory()->create();

    $this->actingAs(User::factory()->staff()->create())
        ->put(route('admin.quarks-applications.update', $application), ['status' => 'accepted'])
        ->assertRedirect();

    expect($application->fresh()->status)->toBe(QuarksApplicationStatus::Accepted);
});
