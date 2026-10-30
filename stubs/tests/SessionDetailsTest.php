<?php

use App\Models\Program;
use App\Models\ProgramSession;
use App\Models\User;

/*
| Acceptance test for the "Session titles and details" feature.
|
| A session can have its own title and description, such as one Saturday event
| of a drop-in program. A session without a title shows its label.
|
| The program links name both parameters, so they also work after the Category pages
| feature adds the category to the URL.
*/

test('staff add a session with a title and a description', function () {
    $program = Program::factory()->create();

    $this->actingAs(User::factory()->staff()->create())
        ->post(route('admin.programs.sessions.store', $program), [
            'label' => 'Saturday, Nov 7',
            'title' => 'Slime Lab',
            'description' => 'Mix your own slime and find out why it stretches.',
            'starts_on' => '2026-11-07',
            'price' => '0',
        ])
        ->assertRedirect(route('admin.programs.edit', $program));

    expect($program->sessions()->sole())
        ->title->toBe('Slime Lab')
        ->description->toBe('Mix your own slime and find out why it stretches.');
});

test('staff see the title and description fields', function () {
    $session = ProgramSession::factory()->create(['title' => 'Slime Lab']);

    $this->actingAs(User::factory()->staff()->create())
        ->get(route('admin.sessions.edit', $session))
        ->assertOk()
        ->assertSee('Title')
        ->assertSee('Description')
        ->assertSee('Slime Lab');
});

test('staff remove a title and the session shows its label again', function () {
    $session = ProgramSession::factory()->create(['label' => 'Saturday, Nov 7', 'title' => 'Slime Lab']);

    $this->actingAs(User::factory()->staff()->create())
        ->put(route('admin.sessions.update', $session), [
            'label' => 'Saturday, Nov 7',
            'title' => '',
            'description' => '',
            'starts_on' => $session->starts_on->toDateString(),
            'price' => '0',
        ])
        ->assertRedirect();

    expect($session->fresh())
        ->title->toBeNull()
        ->description->toBeNull()
        ->displayName()->toBe('Saturday, Nov 7');
});

test('a session with a title shows it with its date and description', function () {
    $program = Program::factory()->create(['name' => 'Saturday Science']);
    ProgramSession::factory()->for($program)->create([
        'label' => 'Saturday, Nov 7',
        'title' => 'Slime Lab',
        'description' => 'Mix your own slime and find out why it stretches.',
        'starts_on' => today()->addWeek(),
        'ends_on' => null,
    ]);

    $this->get(route('programs.show', ['category' => $program->category, 'program' => $program]))
        ->assertOk()
        ->assertSee('Slime Lab')
        ->assertSee(today()->addWeek()->format('D, M j, Y'))
        ->assertSee('Mix your own slime and find out why it stretches.');
});

test('a session without a title shows its label', function () {
    $program = Program::factory()->create();
    ProgramSession::factory()->for($program)->create(['label' => 'Winter 2027', 'title' => null]);

    $this->get(route('programs.show', ['category' => $program->category, 'program' => $program]))
        ->assertOk()
        ->assertSee('Winter 2027');
});

test('the display name is the title, or the label when there is no title', function () {
    expect(ProgramSession::factory()->make(['label' => 'Nov 7', 'title' => 'Slime Lab'])->displayName())->toBe('Slime Lab')
        ->and(ProgramSession::factory()->make(['label' => 'Nov 7', 'title' => null])->displayName())->toBe('Nov 7');
});

test('each Saturday of a drop-in program is a session with its own theme', function () {
    $program = Program::factory()->create(['name' => 'Saturday Science']);
    ProgramSession::factory()->for($program)->create(['title' => 'Slime Lab', 'starts_on' => today()->addDays(3), 'ends_on' => null]);
    ProgramSession::factory()->for($program)->create(['title' => 'Rocket Day', 'starts_on' => today()->addDays(10), 'ends_on' => null]);

    $this->get(route('programs.show', ['category' => $program->category, 'program' => $program]))
        ->assertSeeInOrder(['Slime Lab', 'Rocket Day']);
});
