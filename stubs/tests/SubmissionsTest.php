<?php

use App\Enums\FieldRole;
use App\Enums\FieldType;
use App\Models\Field;
use App\Models\Form;
use App\Models\Submission;
use App\Models\User;

/*
| Acceptance test for the "Submissions and versions" feature.
|
| Staff publish a version of a form. A version keeps a copy of the field
| definitions. Each submission belongs to the version that the person answered,
| so its answers keep their questions after staff change the form.
*/

/**
 * Make a form with these fields, in this order. Every field is required.
 *
 * @param  array<int, Field>  $fields
 */
function formWithFields(array $fields, string $slug = 'camp-form'): Form
{
    $form = Form::factory()->create(['slug' => $slug, 'name' => 'Camp form']);

    foreach (array_values($fields) as $position => $field) {
        $form->fields()->attach($field, ['position' => $position, 'required' => true]);
    }

    return $form;
}

test('publishing creates the next version with a copy of the field definitions', function () {
    $form = formWithFields([Field::factory()->create(['key' => 'school', 'label' => 'School'])]);

    $first = $form->publish();
    $second = $form->publish();

    expect($first->number)->toBe(1)
        ->and($second->number)->toBe(2)
        ->and($second->published_at)->not->toBeNull()
        ->and($form->currentVersion->is($second))->toBeTrue()
        ->and($first->fields[0])->toMatchArray(['key' => 'school', 'label' => 'School', 'type' => 'text', 'required' => true]);
});

test('an archived field is not in the next version', function () {
    $school = Field::factory()->create(['key' => 'school']);
    $fax = Field::factory()->create(['key' => 'fax']);
    $form = formWithFields([$school, $fax]);

    $fax->update(['label' => 'Fax number']);
    $fax->archived_at = now();
    $fax->save();

    expect(collect($form->publish()->fields)->pluck('key')->all())->toBe(['school']);
});

test('a form shows its latest published version, not the draft', function () {
    $form = formWithFields([Field::factory()->create(['label' => 'School'])]);

    $this->get(route('forms.show', $form))->assertNotFound();

    $form->publish();
    $form->fields()->attach(Field::factory()->create(['label' => 'Shoe size']), ['position' => 5, 'required' => true]);

    $this->get(route('forms.show', $form))
        ->assertOk()
        ->assertSee('School')
        ->assertDontSee('Shoe size');
});

test('a visitor sends answers and they are saved under the field keys', function () {
    $form = formWithFields([
        Field::factory()->create(['key' => 'school']),
        Field::factory()->choice(['k' => 'Kindergarten', 'grade_1' => 'Grade 1'])->create(['key' => 'grade']),
    ]);
    $version = $form->publish();

    $this->post(route('forms.store', $form), ['school' => 'Westsyde Elementary', 'grade' => 'grade_1'])
        ->assertRedirect(route('forms.thanks', $form));

    $submission = Submission::sole();

    expect($submission->form_version_id)->toBe($version->id)
        ->and($submission->user_id)->toBeNull()
        ->and($submission->submitted_at)->not->toBeNull()
        ->and($submission->answers)->toBe(['school' => 'Westsyde Elementary', 'grade' => 'grade_1']);
});

test('a signed-in person is saved with the submission', function () {
    $form = formWithFields([Field::factory()->create(['key' => 'school'])]);
    $form->publish();
    $parent = User::factory()->create();

    $this->actingAs($parent)->post(route('forms.store', $form), ['school' => 'Westsyde Elementary']);

    expect(Submission::sole()->user_id)->toBe($parent->id);
});

test('a wrong answer is not saved', function () {
    $form = formWithFields([Field::factory()->type(FieldType::Email)->create(['key' => 'email'])]);
    $form->publish();

    $this->post(route('forms.store', $form), ['email' => 'not an email'])->assertSessionHasErrors('email');

    expect(Submission::count())->toBe(0);
});

test('an old submission keeps its old labels after staff change a label and publish', function () {
    $school = Field::factory()->create(['key' => 'school', 'label' => 'School']);
    $form = formWithFields([$school]);
    $form->publish();
    $this->post(route('forms.store', $form), ['school' => 'Westsyde Elementary']);

    $school->update(['label' => 'Name of your school']);
    $form->publish();
    $this->post(route('forms.store', $form), ['school' => 'Brock Middle']);

    [$old, $new] = Submission::orderBy('id')->get();
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)->get(route('admin.submissions.show', $old))
        ->assertOk()
        ->assertSee('School')
        ->assertDontSee('Name of your school')
        ->assertSee('Westsyde Elementary');

    $this->actingAs($staff)->get(route('admin.submissions.show', $new))
        ->assertSee('Name of your school')
        ->assertSee('Brock Middle');
});

test('a renamed label still finds old answers by key', function () {
    $school = Field::factory()->create(['key' => 'school', 'label' => 'School']);
    $form = formWithFields([$school]);
    $form->publish();
    $this->post(route('forms.store', $form), ['school' => 'Westsyde Elementary']);

    $school->update(['label' => 'Name of your school']);
    $form->publish();

    expect(Submission::sole()->answer('school'))->toBe('Westsyde Elementary')
        ->and(Submission::sole()->answer('not_asked'))->toBeNull();
});

test('code finds an answer by its role, never by its label', function () {
    $phone = Field::factory()->type(FieldType::Phone)->create([
        'key' => 'emergency_phone',
        'label' => 'Emergency phone',
        'role' => FieldRole::EmergencyPhone,
    ]);
    $form = formWithFields([$phone]);
    $form->publish();
    $this->post(route('forms.store', $form), ['emergency_phone' => '250-555-0100']);

    $phone->update(['label' => 'Who do we call in an emergency?']);

    expect(Submission::sole()->answerForRole(FieldRole::EmergencyPhone))->toBe('250-555-0100')
        ->and(Submission::sole()->answerForRole(FieldRole::Allergies))->toBeNull();
});

test('only staff can see submissions', function () {
    $form = formWithFields([]);

    $this->get(route('admin.forms.submissions.index', $form))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->get(route('admin.forms.submissions.index', $form))
        ->assertForbidden();
});

test('staff see the forms and the submissions of one form, newest first', function () {
    $form = formWithFields([Field::factory()->create(['key' => 'school'])]);
    $form->publish();
    $this->travelTo(now()->subDay());
    $this->post(route('forms.store', $form), ['school' => 'Westsyde Elementary']);
    $this->travelBack();
    $this->post(route('forms.store', $form), ['school' => 'Brock Middle']);
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)->get(route('admin.forms.index'))
        ->assertOk()
        ->assertSee('Camp form');

    $this->actingAs($staff)->get(route('admin.forms.submissions.index', $form))
        ->assertOk()
        ->assertSeeInOrder(['Brock Middle', 'Westsyde Elementary']);
});
