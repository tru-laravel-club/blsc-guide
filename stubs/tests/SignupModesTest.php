<?php

use App\Enums\SignupMode;
use App\Models\Category;
use App\Models\Program;
use App\Models\User;

/*
| Acceptance test for the "Sign-up modes" feature.
|
| Each program has a sign-up mode: none, register, apply or request.
| The program page shows what a visitor can do. The code reads the mode, never the category.
|
| The program links name both parameters, so they also work after the Category pages
| feature adds the category to the URL.
*/

dataset('modes', [
    'none' => ['none', 'Drop in during opening hours'],
    'register' => ['register', 'Registration opens soon'],
    'apply' => ['apply', 'Applications open soon'],
    'request' => ['request', 'Requests open soon'],
]);

test('each mode shows its own text on the program page', function (string $mode, string $text) {
    $program = Program::factory()->create(['signup_mode' => $mode]);

    $others = collect(SignupMode::cases())
        ->reject(fn (SignupMode $other) => $other->value === $mode)
        ->map(fn (SignupMode $other) => $other->callToAction());

    $this->get(route('programs.show', ['category' => $program->category, 'program' => $program]))
        ->assertOk()
        ->assertSee($text)
        ->assertDontSee($others->all());
})->with('modes');

test('a new program needs no sign-up until staff choose a mode', function () {
    $program = Program::factory()->create();

    expect($program->fresh()->signup_mode)->toBe(SignupMode::None);
});

test('the program form offers every mode', function () {
    $this->actingAs(User::factory()->staff()->create())
        ->get(route('admin.programs.create'))
        ->assertOk()
        ->assertSee('How visitors sign up')
        ->assertSee(['No sign-up', 'Registration', 'Application', 'Request']);
});

test('staff change the sign-up mode of a program', function () {
    $program = Program::factory()->create(['name' => 'QUARKS']);

    $this->actingAs(User::factory()->staff()->create())
        ->put(route('admin.programs.update', $program), [
            'category_id' => $program->category_id,
            'name' => 'QUARKS',
            'slug' => $program->slug,
            'summary' => $program->summary,
            'signup_mode' => 'apply',
            'is_published' => '1',
        ])
        ->assertRedirect();

    expect($program->fresh()->signup_mode)->toBe(SignupMode::Apply);

    $this->get(route('programs.show', ['category' => $program->category, 'program' => $program]))->assertSee('Applications open soon');
});

test('a program saved without a mode needs no sign-up', function () {
    $this->actingAs(User::factory()->staff()->create())
        ->post(route('admin.programs.store'), [
            'category_id' => Category::factory()->create()->id,
            'name' => 'Saturday Science',
            'summary' => 'A new experiment every Saturday.',
            'is_published' => '1',
        ])
        ->assertRedirect();

    expect(Program::firstWhere('slug', 'saturday-science')->signup_mode)->toBe(SignupMode::None);
});

test('an unknown mode is refused', function () {
    $program = Program::factory()->create();

    $this->actingAs(User::factory()->staff()->create())
        ->put(route('admin.programs.update', $program), [
            'category_id' => $program->category_id,
            'name' => $program->name,
            'summary' => $program->summary,
            'signup_mode' => 'lottery',
        ])
        ->assertSessionHasErrors('signup_mode');
});

test('two programs in the same category can use different modes', function () {
    $camps = Category::factory()->create(['slug' => 'camps']);
    $dropIn = Program::factory()->for($camps)->create(['signup_mode' => 'none']);
    $summer = Program::factory()->for($camps)->create(['signup_mode' => 'register']);

    $this->get(route('programs.show', ['category' => $dropIn->category, 'program' => $dropIn]))->assertSee('Drop in during opening hours');
    $this->get(route('programs.show', ['category' => $summer->category, 'program' => $summer]))->assertSee('Registration opens soon');
});
