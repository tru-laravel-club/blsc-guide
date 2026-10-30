<?php

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\Field;
use App\Models\Form;
use App\Models\Program;
use App\Models\User;
use App\Notifications\ApplicationDecided;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/*
| Acceptance test for the "Applications" feature.
|
| A program with the apply mode takes applications, such as QUARKS. The applicant
| logs in and answers the program's form. Staff accept or decline each application,
| and the applicant sees the decision on the dashboard.
*/

/**
 * Make a published program with the apply mode and a published form that asks one required question.
 *
 * @param  array<string, mixed>  $attributes
 */
function programTakingApplications(array $attributes = []): Program
{
    $question = Field::firstWhere('key', 'why_science')
        ?? Field::factory()->create(['key' => 'why_science', 'label' => 'Why do you like science?']);

    $form = Form::factory()->create(['name' => 'QUARKS application']);
    $form->fields()->attach($question, ['position' => 0, 'required' => true]);
    $form->publish();

    return Program::factory()->create(['name' => 'QUARKS', 'signup_mode' => 'apply', 'form_id' => $form->id, ...$attributes]);
}

/**
 * The URL parameters of a program page and its sign-up pages.
 *
 * @return array{category: mixed, program: Program}
 */
function applyParameters(Program $program): array
{
    return ['category' => $program->category, 'program' => $program];
}

test('a program with the apply mode and a published form shows an Apply button', function () {
    $program = programTakingApplications();

    $this->get(route('programs.show', applyParameters($program)))
        ->assertOk()
        ->assertSee('Applications are open')
        ->assertSee(route('applications.create', applyParameters($program)))
        ->assertDontSee('Applications open soon');
});

test('an apply program without a published form still says that applications open soon', function () {
    $program = Program::factory()->create(['signup_mode' => 'apply', 'form_id' => Form::factory()->create()->id]);

    $this->get(route('programs.show', applyParameters($program)))
        ->assertSee('Applications open soon')
        ->assertDontSee(route('applications.create', applyParameters($program)));
});

test('a guest must log in to apply', function () {
    $program = programTakingApplications();

    $this->get(route('applications.create', applyParameters($program)))->assertRedirect(route('login'));
    $this->post(route('applications.store', applyParameters($program)), ['why_science' => 'Rockets'])->assertRedirect(route('login'));

    expect(Application::count())->toBe(0);
});

test('the applicant sees the questions of the program form', function () {
    $program = programTakingApplications();

    $this->actingAs(User::factory()->create())
        ->get(route('applications.create', applyParameters($program)))
        ->assertOk()
        ->assertSee('QUARKS')
        ->assertSee('Why do you like science?');
});

test('the applicant answers the form and the application is saved', function () {
    $program = programTakingApplications();
    $applicant = User::factory()->create();

    $this->actingAs($applicant)
        ->post(route('applications.store', applyParameters($program)), ['why_science' => 'I want to build rockets.'])
        ->assertRedirect(route('dashboard'));

    $application = Application::sole();

    expect($application->program->is($program))->toBeTrue()
        ->and($application->user->is($applicant))->toBeTrue()
        ->and($application->status)->toBe(ApplicationStatus::Submitted)
        ->and($application->decided_at)->toBeNull()
        ->and($application->submission->answer('why_science'))->toBe('I want to build rockets.')
        ->and($application->submission->user_id)->toBe($applicant->id)
        ->and($application->submission->version->is($program->form->currentVersion))->toBeTrue();
});

test('the answers follow the rules of the form', function () {
    $program = programTakingApplications();

    $this->actingAs(User::factory()->create())
        ->post(route('applications.store', applyParameters($program)), ['why_science' => ''])
        ->assertSessionHasErrors('why_science');

    expect(Application::count())->toBe(0);
});

test('only apply programs take applications', function (array $attributes) {
    $program = programTakingApplications($attributes);
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('applications.create', applyParameters($program)))->assertNotFound();
    $this->actingAs($user)->post(route('applications.store', applyParameters($program)), ['why_science' => 'Rockets'])->assertNotFound();

    expect(Application::count())->toBe(0);
})->with([
    'register mode' => [['signup_mode' => 'register']],
    'request mode' => [['signup_mode' => 'request']],
    'no sign-up' => [['signup_mode' => 'none']],
    'unpublished program' => [['is_published' => false]],
]);

test('only staff can see the applications', function () {
    $program = programTakingApplications();
    $application = Application::factory()->for($program)->create();

    $this->actingAs(User::factory()->create())->get(route('admin.programs.applications.index', $program))->assertForbidden();
    $this->actingAs(User::factory()->create())->get(route('admin.applications.show', $application))->assertForbidden();
});

test('staff list the applications of one program', function () {
    $program = programTakingApplications();
    Application::factory()->for($program)->for(User::factory()->create(['name' => 'Ada Lovelace']))->create();
    Application::factory()->for(programTakingApplications(['name' => 'Other program']))->for(User::factory()->create(['name' => 'Grace Hopper']))->create();

    $this->actingAs(User::factory()->staff()->create())
        ->get(route('admin.programs.applications.index', $program))
        ->assertOk()
        ->assertSee('Ada Lovelace')
        ->assertSee('Submitted')
        ->assertDontSee('Grace Hopper');
});

test('staff read the answers of one application', function () {
    $program = programTakingApplications();
    $applicant = User::factory()->create();

    $this->actingAs($applicant)->post(route('applications.store', applyParameters($program)), ['why_science' => 'I want to build rockets.']);

    $this->actingAs(User::factory()->staff()->create())
        ->get(route('admin.applications.show', Application::sole()))
        ->assertOk()
        ->assertSee($applicant->name)
        ->assertSee('Why do you like science?')
        ->assertSee('I want to build rockets.');
});

test('staff accept an application and the applicant gets a queued email', function () {
    Notification::fake();
    $application = Application::factory()->for(programTakingApplications())->create();
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)
        ->patch(route('admin.applications.update', $application), ['status' => 'accepted'])
        ->assertRedirect();

    $application->refresh();

    expect($application->status)->toBe(ApplicationStatus::Accepted)
        ->and($application->decided_at)->not->toBeNull()
        ->and($application->decided_by)->toBe($staff->id)
        ->and(new ApplicationDecided($application))->toBeInstanceOf(ShouldQueue::class);

    Notification::assertSentTo($application->user, ApplicationDecided::class);
});

test('staff decline an application', function () {
    Notification::fake();
    $application = Application::factory()->for(programTakingApplications())->create();

    $this->actingAs(User::factory()->staff()->create())
        ->patch(route('admin.applications.update', $application), ['status' => 'declined'])
        ->assertRedirect();

    expect($application->fresh()->status)->toBe(ApplicationStatus::Declined);

    Notification::assertSentTo($application->user, ApplicationDecided::class);
});

test('a decision must be accepted or declined', function (string $status) {
    Notification::fake();
    $application = Application::factory()->for(programTakingApplications())->create();

    $this->actingAs(User::factory()->staff()->create())
        ->patch(route('admin.applications.update', $application), ['status' => $status])
        ->assertSessionHasErrors('status');

    expect($application->fresh()->status)->toBe(ApplicationStatus::Submitted);

    Notification::assertNothingSent();
})->with(['submitted', 'maybe']);

test('the applicant sees the status of each application on the dashboard', function () {
    $applicant = User::factory()->create();
    Application::factory()->for(programTakingApplications())->for($applicant)->create(['status' => 'accepted']);
    Application::factory()->for(programTakingApplications(['name' => 'Someone else\'s program']))->create();

    $this->actingAs($applicant)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('QUARKS')
        ->assertSee('Accepted')
        ->assertDontSee('Someone else');
});
