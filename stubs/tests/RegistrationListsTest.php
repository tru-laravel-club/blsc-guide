<?php

use App\Enums\FieldRole;
use App\Enums\FieldScope;
use App\Enums\FieldType;
use App\Models\Child;
use App\Models\Field;
use App\Models\Form;
use App\Models\Program;
use App\Models\ProgramSession;
use App\Models\Registration;
use App\Models\Submission;
use App\Models\User;

/*
| Acceptance test for the "Registration lists" feature.
|
| Staff print a list of the confirmed children of one session, with the answers
| that keep a child safe: allergies, the emergency contact and the pickup people.
| The list asks the form for roles, not for keys or labels, so it keeps working
| when staff change the form.
*/

beforeEach(function () {
    $this->fields = [
        'school' => Field::factory()->create(['key' => 'school', 'label' => 'School', 'scope' => FieldScope::Child]),
        'allergies' => Field::factory()->create(['key' => 'allergies', 'label' => 'Allergies', 'type' => FieldType::LongText, 'scope' => FieldScope::Child, 'role' => FieldRole::Allergies]),
        'emergency_name' => Field::factory()->create(['key' => 'emergency_name', 'label' => 'Emergency contact', 'scope' => FieldScope::Family, 'role' => FieldRole::EmergencyName]),
        'emergency_phone' => Field::factory()->type(FieldType::Phone)->create(['key' => 'emergency_phone', 'label' => 'Emergency phone', 'scope' => FieldScope::Family, 'role' => FieldRole::EmergencyPhone]),
        'pickup_people' => Field::factory()->create(['key' => 'pickup_people', 'label' => 'Pickup', 'type' => FieldType::LongText, 'scope' => FieldScope::Family, 'role' => FieldRole::PickupPeople]),
    ];

    $this->form = Form::factory()->create(['name' => 'Club registration']);

    foreach (array_values($this->fields) as $position => $field) {
        $this->form->fields()->attach($field, ['position' => $position, 'required' => false]);
    }

    $this->form->publish();

    $program = Program::factory()->create(['name' => 'Junior Robotics Club', 'signup_mode' => 'register', 'form_id' => $this->form->id]);
    $this->session = ProgramSession::factory()->for($program)->create(['label' => 'Winter 2026']);
});

/**
 * A registration in the session with answers to the current version of the form.
 *
 * @param  array<string, mixed>  $answers
 */
function listedRegistration(ProgramSession $session, string $childName, array $answers, string $status = 'confirmed'): Registration
{
    $parent = User::factory()->create(['name' => "Parent of {$childName}"]);

    $submission = Submission::factory()
        ->for($session->program->form->currentVersion()->first(), 'version')
        ->create(['user_id' => $parent->id, 'answers' => $answers]);

    return Registration::factory()
        ->for($session, 'session')
        ->for(Child::factory()->for($parent)->create(['name' => $childName]))
        ->for($submission)
        ->create(['status' => $status]);
}

/**
 * The answers of a child with a peanut allergy.
 *
 * @return array<string, string>
 */
function samsAnswers(): array
{
    return [
        'school' => 'Westsyde Elementary',
        'allergies' => 'Peanuts',
        'emergency_name' => 'Grandma Lee',
        'emergency_phone' => '250-555-0100',
        'pickup_people' => 'Aunt May',
    ];
}

test('only staff can open a registration list', function () {
    $routes = [
        route('admin.sessions.registrations.index', $this->session),
        route('admin.sessions.registrations.print', $this->session),
        route('admin.sessions.registrations.export', $this->session),
    ];

    foreach ($routes as $url) {
        $this->get($url)->assertRedirect(route('login'));
    }

    $parent = User::factory()->create();

    foreach ($routes as $url) {
        $this->actingAs($parent)->get($url)->assertForbidden();
    }
});

test('staff see the confirmed children of a session with the answers for each role', function () {
    listedRegistration($this->session, 'Sam', samsAnswers());
    $otherSession = ProgramSession::factory()->for($this->session->program)->create();
    listedRegistration($otherSession, 'Robin', ['allergies' => 'Shellfish']);

    $this->actingAs(User::factory()->staff()->create())
        ->get(route('admin.sessions.registrations.index', $this->session))
        ->assertOk()
        ->assertSee('Junior Robotics Club')
        ->assertSee('Winter 2026')
        ->assertSeeInOrder(['Allergies', 'Emergency contact name', 'Emergency contact phone', 'People who can pick up'])
        ->assertSeeInOrder(['Sam', 'Parent of Sam', 'Peanuts', 'Grandma Lee', '250-555-0100', 'Aunt May'])
        ->assertDontSee('Westsyde Elementary')
        ->assertDontSee('Robin');
});

test('a cancelled or unpaid registration is not on the list', function () {
    listedRegistration($this->session, 'Sam', samsAnswers());
    listedRegistration($this->session, 'Cleopatra', ['allergies' => 'Dust'], 'cancelled');
    listedRegistration($this->session, 'Devika', ['allergies' => 'Cats'], 'pending');

    $this->actingAs(User::factory()->staff()->create())
        ->get(route('admin.sessions.registrations.index', $this->session))
        ->assertSee('Sam')
        ->assertDontSee('Cleopatra')
        ->assertDontSee('Devika');
});

test('the list keeps every answer after staff rename and replace fields', function () {
    listedRegistration($this->session, 'Sam', samsAnswers());

    $this->fields['emergency_phone']->update(['label' => 'Phone for emergencies']);
    $this->fields['allergies']->update(['role' => null, 'archived_at' => now()]);
    $foodAllergies = Field::factory()->create(['key' => 'food_allergies', 'label' => 'Food allergies', 'scope' => FieldScope::Child, 'role' => FieldRole::Allergies]);
    $this->form->fields()->attach($foodAllergies, ['position' => 9, 'required' => false]);
    $this->form->publish();

    listedRegistration($this->session, 'Ada', ['food_allergies' => 'Eggs', 'emergency_phone' => '250-555-0199']);

    $this->actingAs(User::factory()->staff()->create())
        ->get(route('admin.sessions.registrations.index', $this->session))
        ->assertSeeInOrder(['Ada', 'Eggs', '250-555-0199'])
        ->assertSeeInOrder(['Sam', 'Peanuts', '250-555-0100']);
});

test('the print view shows the list without the staff menu', function () {
    listedRegistration($this->session, 'Sam', samsAnswers());

    $this->actingAs(User::factory()->staff()->create())
        ->get(route('admin.sessions.registrations.print', $this->session))
        ->assertOk()
        ->assertSee('Winter 2026')
        ->assertSeeInOrder(['Sam', 'Peanuts', 'Grandma Lee', '250-555-0100', 'Aunt May'])
        ->assertDontSee(route('admin.programs.index'));
});

test('staff download the list as a CSV with one column for each role', function () {
    listedRegistration($this->session, 'Sam', samsAnswers());

    $response = $this->actingAs(User::factory()->staff()->create())
        ->get(route('admin.sessions.registrations.export', $this->session))
        ->assertOk()
        ->assertDownload();

    $lines = array_map(str_getcsv(...), array_filter(explode("\n", trim($response->streamedContent()))));

    expect($lines)->toHaveCount(2)
        ->and($lines[0])->toContain('Child', 'Allergies', 'Emergency contact name', 'Emergency contact phone', 'People who can pick up')
        ->and($lines[0])->not->toContain('School')
        ->and($lines[1])->toContain('Sam', 'Peanuts', 'Grandma Lee', '250-555-0100', 'Aunt May');
});

test('a CSV cell cannot start a formula', function () {
    listedRegistration($this->session, 'Sam', ['allergies' => '=HYPERLINK("http://example.com")']);

    $response = $this->actingAs(User::factory()->staff()->create())
        ->get(route('admin.sessions.registrations.export', $this->session));

    expect($response->streamedContent())->toContain("'=HYPERLINK");
});

test('staff find the list of each session from the program', function () {
    $this->actingAs(User::factory()->staff()->create())
        ->get(route('admin.programs.edit', $this->session->program))
        ->assertSee(route('admin.sessions.registrations.index', $this->session));
});
