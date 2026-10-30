<?php

use App\Enums\FieldRole;
use App\Enums\FieldScope;
use App\Enums\FieldType;
use App\Models\Field;
use App\Models\Form;
use App\Models\User;
use Database\Seeders\FormSeeder;

/*
| Acceptance test for the "Forms and fields" feature.
|
| Staff keep a library of questions (fields). Each field has a fixed key,
| a label that can change, a type, a scope and an optional role.
| A form uses fields from the library in an order that staff choose.
*/

test('only staff can open the field library', function () {
    $this->get(route('admin.fields.index'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->get(route('admin.fields.index'))
        ->assertForbidden();
});

test('staff see the fields in the library', function () {
    Field::factory()->create(['label' => 'School', 'key' => 'school']);
    Field::factory()->create(['label' => 'Allergies', 'key' => 'allergies', 'role' => FieldRole::Allergies]);
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)->get(route('admin.fields.index'))
        ->assertOk()
        ->assertSeeInOrder(['Allergies', 'School'])
        ->assertSee('allergies');

    $this->actingAs($staff)->get(route('admin.fields.create'))->assertOk()->assertSee('Key');
});

test('staff create a field and the key is filled from the label', function () {
    $this->actingAs(User::factory()->staff()->create())
        ->post(route('admin.fields.store'), [
            'label' => 'School name',
            'help' => 'The school that your child goes to.',
            'type' => 'text',
            'scope' => 'child',
        ])
        ->assertRedirect(route('admin.fields.index'));

    $field = Field::firstWhere('key', 'school_name');

    expect($field)->not->toBeNull()
        ->and($field->type)->toBe(FieldType::Text)
        ->and($field->scope)->toBe(FieldScope::Child)
        ->and($field->role)->toBeNull()
        ->and($field->sensitive)->toBeFalse();
});

test('two fields cannot have the same key', function () {
    Field::factory()->create(['key' => 'school']);

    $this->actingAs(User::factory()->staff()->create())
        ->post(route('admin.fields.store'), ['label' => 'School', 'type' => 'text', 'scope' => 'child'])
        ->assertSessionHasErrors('key');
});

test('the key cannot change after create, but the label can', function () {
    $field = Field::factory()->create(['key' => 'school', 'label' => 'School']);
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)->get(route('admin.fields.edit', $field))
        ->assertOk()
        ->assertSee('The key never changes');

    $this->actingAs($staff)
        ->put(route('admin.fields.update', $field), [
            'key' => 'school_name',
            'label' => 'Which school does your child go to?',
            'type' => 'text',
            'scope' => 'child',
        ])
        ->assertRedirect(route('admin.fields.index'));

    expect($field->fresh())
        ->key->toBe('school')
        ->label->toBe('Which school does your child go to?');
});

test('a choice field needs options', function () {
    $this->actingAs(User::factory()->staff()->create())
        ->post(route('admin.fields.store'), ['label' => 'Grade', 'type' => 'choice', 'scope' => 'child'])
        ->assertSessionHasErrors('options');
});

test('each new choice gets a fixed value from its label', function () {
    $this->actingAs(User::factory()->staff()->create())
        ->post(route('admin.fields.store'), [
            'label' => 'T-shirt size',
            'type' => 'choice',
            'scope' => 'child',
            'options' => [
                ['label' => 'Youth small'],
                ['label' => 'Youth medium'],
                ['label' => ''],
            ],
        ])
        ->assertRedirect(route('admin.fields.index'));

    expect(Field::firstWhere('key', 't_shirt_size')->options)->toBe([
        ['value' => 'youth_small', 'label' => 'Youth small', 'archived' => false],
        ['value' => 'youth_medium', 'label' => 'Youth medium', 'archived' => false],
    ]);
});

test('staff archive a choice and rename another, and the values stay the same', function () {
    $field = Field::factory()->choice(['small' => 'Small', 'large' => 'Large'])->create(['key' => 'size']);

    $this->actingAs(User::factory()->staff()->create())
        ->put(route('admin.fields.update', $field), [
            'label' => 'Size',
            'type' => 'choice',
            'scope' => 'child',
            'options' => [
                ['value' => 'small', 'label' => 'Small', 'archived' => '1'],
                ['value' => 'large', 'label' => 'Large (adult)'],
            ],
        ])
        ->assertRedirect(route('admin.fields.index'));

    expect($field->fresh()->options)->toBe([
        ['value' => 'small', 'label' => 'Small', 'archived' => true],
        ['value' => 'large', 'label' => 'Large (adult)', 'archived' => false],
    ]);
});

test('staff archive a field and it stays in the database', function () {
    $field = Field::factory()->create(['label' => 'Favourite colour']);
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)
        ->post(route('admin.fields.archive', $field))
        ->assertRedirect(route('admin.fields.index'));

    expect($field->fresh()->archived_at)->not->toBeNull()
        ->and(Field::active()->count())->toBe(0);

    $this->actingAs($staff)->get(route('admin.fields.index'))->assertSee('Archived');
    $this->actingAs($staff)->get(route('admin.fields.edit', $field))->assertSee('Restore field');

    $this->actingAs($staff)->post(route('admin.fields.restore', $field));

    expect($field->fresh()->archived_at)->toBeNull();
});

test('a form lists its fields in position order with a required flag', function () {
    $form = Form::factory()->create();
    $school = Field::factory()->create(['key' => 'school']);
    $grade = Field::factory()->create(['key' => 'grade']);

    $form->fields()->attach($school, ['position' => 2, 'required' => false]);
    $form->fields()->attach($grade, ['position' => 1, 'required' => true]);

    $fields = $form->fresh()->fields;

    expect($fields->pluck('key')->all())->toBe(['grade', 'school'])
        ->and((bool) $fields->first()->pivot->required)->toBeTrue();
});

test('the seeder adds the common fields with their roles', function () {
    $this->seed(FormSeeder::class);

    expect(Field::where('role', FieldRole::EmergencyPhone)->first())
        ->not->toBeNull()
        ->type->toBe(FieldType::Phone)
        ->scope->toBe(FieldScope::Family);

    expect(Field::where('role', FieldRole::MedicalNotes)->first()->sensitive)->toBeTrue();

    $this->seed(FormSeeder::class);

    expect(Field::where('role', FieldRole::Allergies)->count())->toBe(1);
});
