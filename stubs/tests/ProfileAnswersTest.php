<?php

use App\Enums\FieldScope;
use App\Enums\FieldType;
use App\Models\Child;
use App\Models\Field;
use App\Models\Form;
use App\Models\ProfileValue;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Carbon;

/*
| Acceptance test for the "Saved profile answers" feature.
|
| A parent answers child and family questions once. The app saves the answers
| to the child or to the family, and fills them in on the next form.
| Sign-up answers stay only in the submission. A sensitive answer that is
| too old must be confirmed again. Each family sees only its own answers.
*/

/**
 * Make and publish a form with these fields, in this order. No field is required.
 *
 * @param  array<int, Field>  $fields
 */
function profileForm(string $slug, array $fields): Form
{
    $form = Form::factory()->create(['slug' => $slug, 'name' => 'Form '.$slug]);

    foreach (array_values($fields) as $position => $field) {
        $form->fields()->attach($field, ['position' => $position, 'required' => false]);
    }

    $form->publish();

    return $form;
}

/**
 * Make the fields that the tests use: two child fields, a family field and a sign-up field.
 *
 * @return array<string, Field>
 */
function profileFields(): array
{
    return [
        'school' => Field::factory()->create(['key' => 'school', 'label' => 'School', 'scope' => FieldScope::Child]),
        'allergies' => Field::factory()->sensitive()->create(['key' => 'allergies', 'label' => 'Allergies', 'type' => FieldType::LongText, 'scope' => FieldScope::Child]),
        'emergency_phone' => Field::factory()->type(FieldType::Phone)->create(['key' => 'emergency_phone', 'label' => 'Emergency phone', 'scope' => FieldScope::Family]),
        'message' => Field::factory()->create(['key' => 'message', 'label' => 'Message', 'scope' => FieldScope::Signup]),
    ];
}

test('a parent\'s answers are saved to the child and to the family, but sign-up answers are not', function () {
    $fields = profileFields();
    $form = profileForm('camp-form', $fields);
    $parent = User::factory()->create();
    $child = Child::factory()->for($parent)->create();

    $this->actingAs($parent)
        ->post(route('forms.store', [$form, 'child' => $child->id]), [
            'school' => 'Westsyde Elementary',
            'emergency_phone' => '250-555-0100',
            'message' => 'See you soon',
        ])
        ->assertRedirect(route('forms.thanks', $form));

    $childValue = $child->profileValues()->sole();
    $familyValue = $parent->profileValues()->sole();

    expect(ProfileValue::count())->toBe(2)
        ->and($childValue->field_id)->toBe($fields['school']->id)
        ->and($childValue->value)->toBe('Westsyde Elementary')
        ->and($childValue->confirmed_at)->not->toBeNull()
        ->and($familyValue->field_id)->toBe($fields['emergency_phone']->id)
        ->and($familyValue->value)->toBe('250-555-0100');
});

test('the second form fills in the answers from the first', function () {
    $fields = profileFields();
    $first = profileForm('camp-form', $fields);
    $second = profileForm('club-form', $fields);
    $parent = User::factory()->create();
    $child = Child::factory()->for($parent)->create();

    $this->actingAs($parent)->post(route('forms.store', [$first, 'child' => $child->id]), [
        'school' => 'Westsyde Elementary',
        'emergency_phone' => '250-555-0100',
        'message' => 'See you soon',
    ]);

    $this->actingAs($parent)
        ->get(route('forms.show', [$second, 'child' => $child->id]))
        ->assertOk()
        ->assertSee('Westsyde Elementary')
        ->assertSee('250-555-0100')
        ->assertDontSee('See you soon');
});

test('two children keep separate answers', function () {
    $form = profileForm('camp-form', profileFields());
    $parent = User::factory()->create();
    $sam = Child::factory()->for($parent)->create(['name' => 'Sam']);
    $alex = Child::factory()->for($parent)->create(['name' => 'Alex']);

    $this->actingAs($parent)->post(route('forms.store', [$form, 'child' => $sam->id]), ['school' => 'Westsyde Elementary']);
    $this->actingAs($parent)->post(route('forms.store', [$form, 'child' => $alex->id]), ['school' => 'Brock Middle']);

    expect($sam->profileValues()->sole()->value)->toBe('Westsyde Elementary')
        ->and($alex->profileValues()->sole()->value)->toBe('Brock Middle');

    $this->actingAs($parent)
        ->get(route('forms.show', [$form, 'child' => $alex->id]))
        ->assertSee('Brock Middle')
        ->assertDontSee('Westsyde Elementary');
});

test('a guest\'s answers are kept only in the submission', function () {
    $form = profileForm('camp-form', profileFields());

    $this->post(route('forms.store', $form), ['school' => 'Westsyde Elementary', 'emergency_phone' => '250-555-0100'])
        ->assertRedirect(route('forms.thanks', $form));

    expect(Submission::count())->toBe(1)
        ->and(ProfileValue::count())->toBe(0);
});

test('a parent chooses which child a form is about', function () {
    $fields = profileFields();
    $childForm = profileForm('camp-form', $fields);
    $familyForm = profileForm('family-form', [$fields['emergency_phone']]);
    $parent = User::factory()->create();
    Child::factory()->for($parent)->create(['name' => 'Sam Rivers']);
    Child::factory()->for($parent)->create(['name' => 'Alex Rivers']);

    $this->actingAs($parent)
        ->get(route('forms.show', $childForm))
        ->assertSee('Which child is this form about?')
        ->assertSee('Sam Rivers')
        ->assertSee('Alex Rivers');

    $this->actingAs($parent)
        ->get(route('forms.show', $familyForm))
        ->assertDontSee('Which child is this form about?');
});

test('a stale sensitive answer asks for confirmation', function () {
    $this->travelTo(Carbon::parse('2025-01-15'));
    $form = profileForm('camp-form', profileFields());
    $parent = User::factory()->create();
    $child = Child::factory()->for($parent)->create();
    $this->actingAs($parent)->post(route('forms.store', [$form, 'child' => $child->id]), ['allergies' => 'Peanuts']);

    $this->travelTo(Carbon::parse('2025-07-15'));
    $this->actingAs($parent)
        ->get(route('forms.show', [$form, 'child' => $child->id]))
        ->assertSee('Peanuts')
        ->assertDontSee('Is this still correct?');

    $this->travelTo(Carbon::parse('2026-02-01'));
    $this->actingAs($parent)
        ->get(route('forms.show', [$form, 'child' => $child->id]))
        ->assertSee('Peanuts')
        ->assertSee('Is this still correct?');

    $this->actingAs($parent)
        ->post(route('forms.store', [$form, 'child' => $child->id]), ['allergies' => 'Peanuts'])
        ->assertSessionHasErrors('confirm.allergies');

    $this->actingAs($parent)
        ->post(route('forms.store', [$form, 'child' => $child->id]), ['allergies' => 'Peanuts', 'confirm' => ['allergies' => '1']])
        ->assertRedirect(route('forms.thanks', $form));

    expect($child->profileValues()->sole()->confirmed_at->toDateString())->toBe('2026-02-01');
});

test('the submission keeps its own copy of the answers', function () {
    $form = profileForm('camp-form', profileFields());
    $parent = User::factory()->create();
    $child = Child::factory()->for($parent)->create();
    $this->actingAs($parent)->post(route('forms.store', [$form, 'child' => $child->id]), ['allergies' => 'Peanuts']);
    $value = $child->profileValues()->sole();

    $this->actingAs($parent)
        ->put(route('children.answers.update', [$child, $value]), ['allergies' => 'None'])
        ->assertRedirect(route('children.answers.index', $child));

    expect($value->fresh()->value)->toBe('None')
        ->and(Submission::sole()->answer('allergies'))->toBe('Peanuts');
});

test('a parent sees, changes and deletes the saved answers of a child', function () {
    $fields = profileFields();
    $parent = User::factory()->create();
    $child = Child::factory()->for($parent)->create(['name' => 'Sam Rivers']);
    $school = ProfileValue::factory()->for($child, 'owner')->for($fields['school'])->create(['value' => 'Westsyde Elementary']);
    $allergies = ProfileValue::factory()->for($child, 'owner')->for($fields['allergies'])->create(['value' => 'Peanuts']);

    $this->actingAs($parent)
        ->get(route('children.answers.index', $child))
        ->assertOk()
        ->assertSee('Sam Rivers')
        ->assertSee('School')
        ->assertSee('Westsyde Elementary')
        ->assertSee('Peanuts');

    $this->actingAs($parent)
        ->put(route('children.answers.update', [$child, $school]), ['school' => 'Brock Middle'])
        ->assertRedirect(route('children.answers.index', $child));

    $this->actingAs($parent)
        ->delete(route('children.answers.destroy', [$child, $allergies]))
        ->assertRedirect(route('children.answers.index', $child));

    expect($school->fresh()->value)->toBe('Brock Middle')
        ->and($allergies->fresh())->toBeNull();
});

test('a changed answer is checked with the rules of its field', function () {
    $parent = User::factory()->create();
    $child = Child::factory()->for($parent)->create();
    $field = Field::factory()->type(FieldType::Email)->create(['key' => 'school_email', 'scope' => FieldScope::Child]);
    $value = ProfileValue::factory()->for($child, 'owner')->for($field)->create(['value' => 'office@school.ca']);

    $this->actingAs($parent)
        ->put(route('children.answers.update', [$child, $value]), ['school_email' => 'not an email'])
        ->assertSessionHasErrors('school_email');

    expect($value->fresh()->value)->toBe('office@school.ca');
});

test('a parent cannot see or change another family\'s answers', function () {
    $fields = profileFields();
    $form = profileForm('camp-form', $fields);
    $other = Child::factory()->create();
    $value = ProfileValue::factory()->for($other, 'owner')->for($fields['allergies'])->create(['value' => 'Peanuts']);
    $parent = User::factory()->create();
    $mine = Child::factory()->for($parent)->create();

    $this->actingAs($parent)->get(route('forms.show', [$form, 'child' => $other->id]))->assertNotFound();
    $this->actingAs($parent)->get(route('children.answers.index', $other))->assertForbidden();
    $this->actingAs($parent)->put(route('children.answers.update', [$other, $value]), ['allergies' => 'None'])->assertForbidden();
    $this->actingAs($parent)->delete(route('children.answers.destroy', [$other, $value]))->assertForbidden();
    $this->actingAs($parent)->delete(route('children.answers.destroy', [$mine, $value]))->assertNotFound();

    expect($value->fresh()->value)->toBe('Peanuts');
});

test('deleting a child or an account deletes the saved answers', function () {
    $parent = User::factory()->create();
    $sam = Child::factory()->for($parent)->create();
    $alex = Child::factory()->for($parent)->create();
    ProfileValue::factory()->for($sam, 'owner')->create();
    ProfileValue::factory()->for($alex, 'owner')->create();
    ProfileValue::factory()->for($parent, 'owner')->create();

    $this->actingAs($parent)->delete(route('children.destroy', $sam));

    expect(ProfileValue::count())->toBe(2);

    $parent->delete();

    expect(ProfileValue::count())->toBe(0);
});

test('my children links to the saved answers of each child', function () {
    $parent = User::factory()->create();
    $child = Child::factory()->for($parent)->create();

    $this->actingAs($parent)
        ->get(route('children.index'))
        ->assertSee('Saved answers')
        ->assertSee(route('children.answers.index', $child));
});
