<?php

use App\Models\Program;
use App\Models\ProgramSession;

/*
| Acceptance test for the "Camps and clubs pages" feature.
|
| The camps and clubs pages list the published programs of one kind. The program page
| shows the upcoming sessions of one program. Visitors never see a draft.
*/

test('the clubs page lists published clubs only', function () {
    Program::factory()->club()->create(['name' => 'Robotics Club']);
    Program::factory()->club()->unpublished()->create(['name' => 'Secret Club']);
    Program::factory()->camp()->create(['name' => 'Summer Camp']);

    $this->get(route('clubs.index'))
        ->assertOk()
        ->assertSee('Robotics Club')
        ->assertDontSee('Secret Club')
        ->assertDontSee('Summer Camp');
});

test('the camps page explains when nothing is listed', function () {
    $this->get(route('camps.index'))
        ->assertOk()
        ->assertSee('There are no camps listed right now.');
});

test('a program page shows upcoming sessions and hides past ones', function () {
    $program = Program::factory()->club()->create();
    ProgramSession::factory()->for($program)->create(['label' => 'Winter 2027', 'price_cents' => 15000]);
    ProgramSession::factory()->for($program)->past()->create(['label' => 'Spring 2026']);

    $this->get(route('programs.show', $program))
        ->assertOk()
        ->assertSee($program->name)
        ->assertSee('Winter 2027')
        ->assertSee('$150.00')
        ->assertDontSee('Spring 2026');
});

test('a one-day session stays listed until that day ends', function () {
    $program = Program::factory()->camp()->create();
    ProgramSession::factory()->for($program)->create([
        'label' => 'Pro-D Day',
        'starts_on' => today(),
        'ends_on' => null,
    ]);

    $this->get(route('programs.show', $program))->assertSee('Pro-D Day');
});

test('an unpublished program returns not found', function () {
    $program = Program::factory()->unpublished()->create();

    $this->get(route('programs.show', $program))->assertNotFound();
});
