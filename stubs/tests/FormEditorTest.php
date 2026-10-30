<?php

use App\Enums\FieldType;
use App\Models\Category;
use App\Models\Field;
use App\Models\Form;
use App\Models\Program;
use App\Models\User;
use Livewire\Livewire;

/*
| Acceptance test for the "Form editor for staff" feature.
|
| Staff create a form, add fields from the library, order them, mark them as
| required, remove them, and publish a new version. A program can use a form.
*/

/**
 * Make a form with these fields, in this order. No field is required.
 *
 * @param  array<int, Field>  $fields
 */
function formForEditor(array $fields = []): Form
{
    $form = Form::factory()->create();

    foreach (array_values($fields) as $position => $field) {
        $form->fields()->attach($field, ['position' => $position, 'required' => false]);
    }

    return $form;
}

test('only staff can open the form pages', function () {
    $form = formForEditor();

    $this->get(route('admin.forms.create'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->get(route('admin.forms.edit', $form))
        ->assertForbidden();
});

test('staff create a form and go to its editor', function () {
    $this->actingAs(User::factory()->staff()->create())
        ->post(route('admin.forms.store'), [
            'name' => 'School booking',
            'notify_email' => 'bookings@example.com',
        ])
        ->assertRedirect(route('admin.forms.edit', Form::firstWhere('slug', 'school-booking')));
});

test('staff change the details of a form', function () {
    $form = formForEditor();
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)->get(route('admin.forms.edit', $form))
        ->assertOk()
        ->assertSee($form->name)
        ->assertSeeLivewire('admin.form-editor');

    $this->actingAs($staff)
        ->put(route('admin.forms.update', $form), [
            'name' => 'Party request',
            'slug' => 'party-request',
            'notify_email' => 'not an email',
        ])
        ->assertSessionHasErrors('notify_email');

    $this->actingAs($staff)
        ->put(route('admin.forms.update', $form), [
            'name' => 'Party request',
            'slug' => 'party-request',
            'notify_email' => 'parties@example.com',
        ])
        ->assertRedirect(route('admin.forms.edit', $form));

    expect($form->fresh())->name->toBe('Party request')->notify_email->toBe('parties@example.com');
});

test('staff add fields from the library, but not archived ones', function () {
    $form = formForEditor();
    $school = Field::factory()->create(['label' => 'School']);
    Field::factory()->archived()->create(['label' => 'Fax number']);

    Livewire::actingAs(User::factory()->staff()->create())
        ->test('admin.form-editor', ['form' => $form])
        ->assertSee('School')
        ->assertDontSee('Fax number')
        ->set('newField', $school->id)
        ->call('add')
        ->assertHasNoErrors();

    expect($form->fields()->pluck('key')->all())->toBe([$school->key]);
});

test('staff cannot add an archived field or the same field twice', function () {
    $school = Field::factory()->create();
    $fax = Field::factory()->archived()->create();
    $form = formForEditor([$school]);

    Livewire::actingAs(User::factory()->staff()->create())
        ->test('admin.form-editor', ['form' => $form])
        ->set('newField', $fax->id)
        ->call('add')
        ->assertHasErrors('newField')
        ->set('newField', $school->id)
        ->call('add')
        ->assertHasErrors('newField');

    expect($form->fields()->count())->toBe(1);
});

test('staff reorder the fields', function () {
    [$a, $b, $c] = Field::factory()->count(3)->create();
    $form = formForEditor([$a, $b, $c]);

    Livewire::actingAs(User::factory()->staff()->create())
        ->test('admin.form-editor', ['form' => $form])
        ->call('sort', $c->id, 0);

    expect($form->fields()->pluck('fields.id')->all())->toBe([$c->id, $a->id, $b->id]);
});

test('staff mark a field as required and remove another', function () {
    [$school, $grade] = Field::factory()->count(2)->create();
    $form = formForEditor([$school, $grade]);

    Livewire::actingAs(User::factory()->staff()->create())
        ->test('admin.form-editor', ['form' => $form])
        ->call('toggleRequired', $school->id)
        ->call('remove', $grade->id);

    $fields = $form->fields()->get();

    expect($fields->pluck('id')->all())->toBe([$school->id])
        ->and($fields->first()->pivot->required)->toBeTrue()
        ->and($grade->fresh())->not->toBeNull();
});

test('the editor shows unpublished changes until staff publish', function () {
    $form = formForEditor([Field::factory()->create()]);

    $editor = Livewire::actingAs(User::factory()->staff()->create())
        ->test('admin.form-editor', ['form' => $form])
        ->assertSee('Unpublished changes')
        ->call('publish')
        ->assertDontSee('Unpublished changes');

    expect($form->versions()->count())->toBe(1);

    $editor->set('newField', Field::factory()->create()->id)
        ->call('add')
        ->assertSee('Unpublished changes');
});

test('a person who is not staff cannot use the editor', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('admin.form-editor', ['form' => formForEditor()])
        ->assertForbidden();
});

test('a field with answers cannot change its type', function () {
    $field = Field::factory()->create(['key' => 'school', 'label' => 'School']);
    $form = formForEditor([$field]);
    $form->publish();
    $this->post(route('forms.store', $form), ['school' => 'Westsyde Elementary']);
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)
        ->put(route('admin.fields.update', $field), ['label' => 'School', 'type' => 'long_text', 'scope' => 'signup'])
        ->assertSessionHasErrors('type');

    $this->actingAs($staff)
        ->put(route('admin.fields.update', $field), ['label' => 'Your school', 'type' => 'text', 'scope' => 'signup'])
        ->assertSessionHasNoErrors();

    $unused = Field::factory()->create();

    $this->actingAs($staff)
        ->put(route('admin.fields.update', $unused), ['label' => 'Notes', 'type' => 'long_text', 'scope' => 'signup'])
        ->assertSessionHasNoErrors();

    expect($field->fresh()->type)->toBe(FieldType::Text)
        ->and($unused->fresh()->type)->toBe(FieldType::LongText);
});

test('a program can use a form', function () {
    $form = formForEditor();
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)->get(route('admin.programs.create'))->assertSee($form->name);

    $this->actingAs($staff)
        ->post(route('admin.programs.store'), [
            'category_id' => Category::factory()->create()->id,
            'form_id' => $form->id,
            'name' => 'Rocks and Minerals',
            'summary' => 'Find out what rocks are made of.',
        ])
        ->assertRedirect();

    expect(Program::firstWhere('slug', 'rocks-and-minerals')->form->is($form))->toBeTrue();
});
