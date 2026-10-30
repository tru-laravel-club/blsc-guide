<?php

use App\Enums\FieldType;
use App\Forms\FormRules;
use App\Models\Field;
use App\Models\Form;

/*
| Acceptance test for the "Show and validate a form" feature.
|
| One component shows any form. One class builds the validation rules
| from the field definitions. The code never checks a label.
*/

/**
 * Make a form with these fields, in this order. Each field is required unless the value says false.
 *
 * @param  array<int, Field>|array<int, array{0: Field, 1: bool}>  $fields
 */
function formForDisplay(array $fields): Form
{
    $form = Form::factory()->create(['slug' => 'test-form', 'name' => 'Test form']);

    foreach (array_values($fields) as $position => $field) {
        [$field, $required] = is_array($field) ? $field : [$field, true];
        $form->fields()->attach($field, ['position' => $position, 'required' => $required]);
    }

    return $form;
}

test('a visitor sees the fields of a form in order, with their help text', function () {
    $form = formForDisplay([
        Field::factory()->create(['label' => 'Your name']),
        Field::factory()->create(['label' => 'Your question', 'help' => 'Ask us anything about science.', 'type' => FieldType::LongText]),
        Field::factory()->archived()->create(['label' => 'Fax number']),
    ]);

    $this->get(route('forms.show', $form))
        ->assertOk()
        ->assertSee('Test form')
        ->assertSeeInOrder(['Your name', 'Your question'])
        ->assertSee('Ask us anything about science.')
        ->assertDontSee('Fax number');
});

test('an unknown form is not found', function () {
    $this->get('/forms/no-such-form')->assertNotFound();
});

test('a required field is required, and an optional field is not', function () {
    $name = Field::factory()->create(['key' => 'your_name', 'label' => 'Your name']);
    $school = Field::factory()->create(['key' => 'school']);
    $form = formForDisplay([$name, [$school, false]]);

    $this->post(route('forms.store', $form), [])
        ->assertSessionHasErrors(['your_name' => 'The Your name field is required.'])
        ->assertSessionDoesntHaveErrors('school');
});

test('each type refuses a wrong value and accepts a right one', function (FieldType $type, mixed $wrong, mixed $right) {
    $field = Field::factory()
        ->choice(['small' => 'Small', 'large' => 'Large'], $type)
        ->create(['key' => 'answer']);
    $form = formForDisplay([$field]);

    $this->post(route('forms.store', $form), ['answer' => $wrong])->assertSessionHasErrors('answer');
    $this->post(route('forms.store', $form), ['answer' => $right])->assertSessionHasNoErrors();
})->with([
    'short text' => [FieldType::Text, str_repeat('a', 256), 'Hello'],
    'long text' => [FieldType::LongText, str_repeat('a', 5001), "Line one\nLine two"],
    'email' => [FieldType::Email, 'not an email', 'parent@example.com'],
    'phone' => [FieldType::Phone, 'call me', '250-555-0100'],
    'date' => [FieldType::Date, 'someday', '2026-11-14'],
    'number' => [FieldType::Number, 'ten', '10'],
    'one choice' => [FieldType::Choice, 'medium', 'small'],
    'several choices' => [FieldType::Checkboxes, 'small', ['small', 'large']],
    'consent' => [FieldType::Consent, '0', '1'],
]);

test('several choices refuse a value that is not a choice', function () {
    $field = Field::factory()->choice(['small' => 'Small'], FieldType::Checkboxes)->create(['key' => 'sizes']);
    $form = formForDisplay([$field]);

    $this->post(route('forms.store', $form), ['sizes' => ['small', 'huge']])->assertSessionHasErrors('sizes.1');
});

test('an archived choice is not shown and is refused', function () {
    $field = Field::factory()->create([
        'key' => 'size',
        'type' => FieldType::Choice,
        'options' => [
            ['value' => 'small', 'label' => 'Small', 'archived' => false],
            ['value' => 'tiny', 'label' => 'Tiny', 'archived' => true],
        ],
    ]);
    $form = formForDisplay([$field]);

    $this->get(route('forms.show', $form))->assertSee('Small')->assertDontSee('Tiny');

    $this->post(route('forms.store', $form), ['size' => 'tiny'])->assertSessionHasErrors('size');
});

test('a valid answer shows the thank-you page', function () {
    $form = formForDisplay([Field::factory()->create(['key' => 'your_name'])]);

    $this->post(route('forms.store', $form), ['your_name' => 'Ada'])
        ->assertRedirect(route('forms.thanks', $form));

    $this->get(route('forms.thanks', $form))->assertOk()->assertSee('Thank you');
});

test('the rules come from the field definitions, keyed by the field key', function () {
    $rules = FormRules::for([
        ['key' => 'sizes', 'label' => 'Sizes', 'type' => 'checkboxes', 'required' => true, 'help' => null, 'options' => [
            ['value' => 'small', 'label' => 'Small', 'archived' => false],
        ]],
        ['key' => 'school', 'label' => 'School', 'type' => 'text', 'required' => false, 'help' => null, 'options' => []],
    ]);

    expect($rules)->toHaveKeys(['sizes', 'sizes.*', 'school'])
        ->and($rules['sizes'])->toContain('required')
        ->and($rules['school'])->toContain('nullable');
});
