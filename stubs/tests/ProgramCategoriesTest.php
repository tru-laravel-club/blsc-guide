<?php

use App\Models\Category;
use App\Models\Program;
use App\Models\User;

/*
| Acceptance test for the "Program categories" feature.
|
| Staff manage the kinds of program, such as Camps and Clubs, in the admin area.
| Programs belong to a category, and the code never checks a category name.
*/

test('only staff can open the categories admin', function () {
    $this->get(route('admin.categories.index'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->get(route('admin.categories.index'))
        ->assertForbidden();
});

test('staff see the categories in position order with a count of programs', function () {
    $clubs = Category::factory()->create(['name' => 'Clubs', 'position' => 2]);
    Category::factory()->create(['name' => 'Camps', 'position' => 1]);
    Program::factory()->count(3)->for($clubs)->create();

    $this->actingAs(User::factory()->staff()->create())
        ->get(route('admin.categories.index'))
        ->assertOk()
        ->assertSeeInOrder(['Camps', 'Clubs'])
        ->assertSee('3');
});

test('staff open the forms to add and edit a category', function () {
    $category = Category::factory()->create(['name' => 'Clubs']);
    Program::factory()->for($category)->create();
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)->get(route('admin.categories.create'))->assertOk()->assertSee('Position');

    $this->actingAs($staff)->get(route('admin.categories.edit', $category))
        ->assertOk()
        ->assertSee('Clubs')
        ->assertSee('Move them to another category');
});

test('staff create a category and the slug is filled from the name', function () {
    $this->actingAs(User::factory()->staff()->create())
        ->post(route('admin.categories.store'), [
            'name' => 'Homeschool Classes',
            'summary' => 'Weekly science classes for homeschool families.',
            'position' => 3,
        ])
        ->assertRedirect(route('admin.categories.index'));

    expect(Category::firstWhere('slug', 'homeschool-classes'))
        ->not->toBeNull()
        ->position->toBe(3);
});

test('two categories cannot have the same web address', function () {
    Category::factory()->create(['slug' => 'camps']);

    $this->actingAs(User::factory()->staff()->create())
        ->post(route('admin.categories.store'), ['name' => 'Camps', 'position' => 0])
        ->assertSessionHasErrors('slug');
});

test('staff rename a category', function () {
    $category = Category::factory()->create(['name' => 'Clubs', 'slug' => 'clubs']);

    $this->actingAs(User::factory()->staff()->create())
        ->put(route('admin.categories.update', $category), [
            'name' => 'After-school Clubs',
            'slug' => 'clubs',
            'position' => 0,
        ])
        ->assertRedirect(route('admin.categories.index'));

    expect($category->fresh()->name)->toBe('After-school Clubs');
});

test('a category with programs cannot be deleted', function () {
    $category = Category::factory()->create();
    Program::factory()->for($category)->create();

    $this->actingAs(User::factory()->staff()->create())
        ->delete(route('admin.categories.destroy', $category))
        ->assertSessionHasErrors('category');

    expect($category->fresh())->not->toBeNull();
});

test('an empty category can be deleted', function () {
    $category = Category::factory()->create();

    $this->actingAs(User::factory()->staff()->create())
        ->delete(route('admin.categories.destroy', $category))
        ->assertRedirect(route('admin.categories.index'));

    expect($category->fresh())->toBeNull();
});

test('a program is saved in a category and shows it', function () {
    $category = Category::factory()->create(['name' => 'Homeschool Classes']);

    $this->actingAs(User::factory()->staff()->create())
        ->post(route('admin.programs.store'), [
            'category_id' => $category->id,
            'name' => 'Rocks and Minerals',
            'summary' => 'Find out what rocks are made of.',
            'is_published' => '1',
        ])
        ->assertRedirect();

    $program = Program::firstWhere('slug', 'rocks-and-minerals');

    expect($program->category->is($category))->toBeTrue();

    $this->get(route('programs.show', ['category' => $program->category, 'program' => $program]))->assertSee('Homeschool Classes');
});

test('a program needs a category that exists', function () {
    $this->actingAs(User::factory()->staff()->create())
        ->post(route('admin.programs.store'), [
            'category_id' => 999,
            'name' => 'Rocks and Minerals',
            'summary' => 'Find out what rocks are made of.',
        ])
        ->assertSessionHasErrors('category_id');
});

test('the clubs page lists the programs in the clubs category only', function () {
    $clubs = Category::factory()->create(['name' => 'Clubs', 'slug' => 'clubs']);
    $camps = Category::factory()->create(['name' => 'Camps', 'slug' => 'camps']);
    Program::factory()->for($clubs)->create(['name' => 'Robotics Club']);
    Program::factory()->for($camps)->create(['name' => 'Summer Camp']);

    $this->get(route('clubs.index'))
        ->assertOk()
        ->assertSee('Robotics Club')
        ->assertDontSee('Summer Camp');
});
