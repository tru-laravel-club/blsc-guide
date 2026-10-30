<?php

use App\Models\Category;
use App\Models\Program;
use App\Models\ProgramSession;
use Illuminate\Support\Facades\Route;

/*
| Acceptance test for the "Category pages" feature.
|
| Every category gets its own page at /programs/{category}. The menu and the home page
| list the categories from the database, so a new category needs no new code.
*/

test('the programs page lists the categories in position order', function () {
    Category::factory()->create(['name' => 'Clubs', 'position' => 2]);
    Category::factory()->create(['name' => 'Camps', 'position' => 1]);
    Category::factory()->create(['name' => 'QUARKS', 'position' => 3]);

    $this->get(route('programs.index'))
        ->assertOk()
        ->assertSeeInOrder(['Camps', 'Clubs', 'QUARKS']);
});

test('a new category gets a page and a menu link with no code change', function () {
    $homeschool = Category::factory()->create(['name' => 'Homeschool', 'slug' => 'homeschool']);
    Program::factory()->for($homeschool)->create(['name' => 'Rocks and Minerals']);

    expect(route('categories.show', $homeschool))->toBe(url('/programs/homeschool'));

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Homeschool')
        ->assertSee(route('categories.show', $homeschool));

    $this->get(route('categories.show', $homeschool))
        ->assertOk()
        ->assertSee('Homeschool')
        ->assertSee('Rocks and Minerals');
});

test('the menu follows the names and order that staff choose', function () {
    Category::factory()->create(['name' => 'After-school Clubs', 'position' => 2]);
    Category::factory()->create(['name' => 'Summer Camps', 'position' => 1]);

    $this->get(route('programs.index'))
        ->assertSeeInOrder(['Discover', 'Summer Camps', 'After-school Clubs']);
});

test('a category page lists its published programs and their upcoming sessions', function () {
    $clubs = Category::factory()->create(['name' => 'Clubs', 'slug' => 'clubs']);
    $robotics = Program::factory()->for($clubs)->create(['name' => 'Robotics Club']);
    ProgramSession::factory()->for($robotics)->create(['label' => 'Winter 2027']);
    ProgramSession::factory()->for($robotics)->past()->create(['label' => 'Spring 2026']);
    Program::factory()->for($clubs)->unpublished()->create(['name' => 'Secret Club']);
    Program::factory()->camp()->create(['name' => 'Summer Camp']);

    $this->get(route('categories.show', $clubs))
        ->assertOk()
        ->assertSee('Robotics Club')
        ->assertSee('Winter 2027')
        ->assertDontSee('Spring 2026')
        ->assertDontSee('Secret Club')
        ->assertDontSee('Summer Camp');
});

test('an empty category explains that nothing is listed', function () {
    $camps = Category::factory()->create(['name' => 'Camps', 'slug' => 'camps']);

    $this->get(route('categories.show', $camps))
        ->assertOk()
        ->assertSee('There are no camps listed right now.');
});

test('an unknown category is not found', function () {
    $this->get('/programs/no-such-category')->assertNotFound();
});

test('a program page has the category in its address', function () {
    $clubs = Category::factory()->create(['name' => 'Clubs', 'slug' => 'clubs']);
    $program = Program::factory()->for($clubs)->create(['name' => 'Robotics Club', 'slug' => 'robotics-club']);

    expect(route('programs.show', [$clubs, $program]))->toBe(url('/programs/clubs/robotics-club'));

    $this->get(route('programs.show', [$clubs, $program]))
        ->assertOk()
        ->assertSee('Robotics Club')
        ->assertSee(route('categories.show', $clubs));
});

test('a program address with the wrong category is not found', function () {
    $clubs = Category::factory()->create(['slug' => 'clubs']);
    $camps = Category::factory()->create(['slug' => 'camps']);
    $program = Program::factory()->for($clubs)->create(['slug' => 'robotics-club']);

    $this->get('/programs/camps/robotics-club')->assertNotFound();
    $this->get(route('programs.show', [$camps, $program]))->assertNotFound();
});

test('an unpublished program is not found', function () {
    $program = Program::factory()->unpublished()->create();

    $this->get(route('programs.show', [$program->category, $program]))->assertNotFound();
});

test('no category has its own route', function () {
    expect(Route::has('camps.index'))->toBeFalse()
        ->and(Route::has('clubs.index'))->toBeFalse();
});
