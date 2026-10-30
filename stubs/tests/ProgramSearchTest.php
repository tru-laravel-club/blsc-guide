<?php

use App\Models\Program;
use App\Models\User;
use Livewire\Livewire;

/*
| Acceptance test for the "Search the program list" feature.
|
| Staff search the programs by name or summary in the admin list. The search
| works through the URL (?search=robot) and, with Livewire, while staff type.
| Both front doors use the same Program::search() scope.
*/

beforeEach(function () {
    Program::factory()->create(['name' => 'Robotics Club', 'summary' => 'Build and program robots.']);
    Program::factory()->create(['name' => 'Summer Science Camp', 'summary' => 'Make a volcano erupt.']);
    Program::factory()->unpublished()->create(['name' => 'Rocket Lab', 'summary' => 'Launch water rockets.']);
});

test('the search scope finds programs by name or summary, in any case', function () {
    expect(Program::search('ROBOT')->pluck('name')->all())->toBe(['Robotics Club'])
        ->and(Program::search('volcano')->pluck('name')->all())->toBe(['Summer Science Camp']);
});

test('an empty search finds every program', function () {
    expect(Program::search('')->count())->toBe(3)
        ->and(Program::search(null)->count())->toBe(3);
});

test('staff search the program list through the address', function () {
    $this->actingAs(User::factory()->staff()->create())
        ->get(route('admin.programs.index', ['search' => 'rocket']))
        ->assertOk()
        ->assertSee('Rocket Lab')
        ->assertDontSee('Robotics Club')
        ->assertDontSee('Summer Science Camp');
});

test('the list says so when no program matches', function () {
    $this->actingAs(User::factory()->staff()->create())
        ->get(route('admin.programs.index', ['search' => 'dinosaur']))
        ->assertOk()
        ->assertSee('No programs match')
        ->assertDontSee('Robotics Club');
});

test('the list shows every program when the search is empty', function () {
    $this->actingAs(User::factory()->staff()->create())
        ->get(route('admin.programs.index'))
        ->assertOk()
        ->assertSee('Robotics Club')
        ->assertSee('Summer Science Camp')
        ->assertSee('Rocket Lab');
});

test('the list updates while staff type', function () {
    Livewire::actingAs(User::factory()->staff()->create())
        ->test('admin.program-list')
        ->assertSee('Summer Science Camp')
        ->set('search', 'robot')
        ->assertSee('Robotics Club')
        ->assertDontSee('Summer Science Camp')
        ->set('search', '')
        ->assertSee('Summer Science Camp');
});

test('the component reads the search from the address', function () {
    Livewire::withQueryParams(['search' => 'volcano'])
        ->actingAs(User::factory()->staff()->create())
        ->test('admin.program-list')
        ->assertSet('search', 'volcano')
        ->assertSee('Summer Science Camp')
        ->assertDontSee('Robotics Club');
});

test('a person who is not staff cannot use the search', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.programs.index', ['search' => 'robot']))
        ->assertForbidden();

    Livewire::actingAs(User::factory()->create())
        ->test('admin.program-list')
        ->assertForbidden();
});
