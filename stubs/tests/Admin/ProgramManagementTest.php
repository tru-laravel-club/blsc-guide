<?php

use App\Enums\ProgramType;
use App\Models\Program;
use App\Models\ProgramSession;
use App\Models\User;
use App\Services\ProgramService;
use App\Services\ProgramSessionService;

/*
| Acceptance test for the "Staff: manage programs" feature.
|
| Staff create, edit and delete programs and their sessions. Controllers do HTTP,
| and ProgramService and ProgramSessionService do the work. Only staff get in.
*/

test('guests are sent to the login page', function () {
    $this->get(route('admin.programs.index'))->assertRedirect(route('login'));
});

test('users who are not staff cannot manage programs', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.programs.index'))
        ->assertForbidden();
});

test('staff can see every program, including drafts', function () {
    Program::factory()->create(['name' => 'Robotics Club']);
    Program::factory()->unpublished()->create(['name' => 'Draft Club']);

    $this->actingAs(User::factory()->staff()->create())
        ->get(route('admin.programs.index'))
        ->assertOk()
        ->assertSee('Robotics Club')
        ->assertSee('Draft Club');
});

test('staff can create a program and the slug is filled from the name', function () {
    $this->actingAs(User::factory()->staff()->create())
        ->post(route('admin.programs.store'), [
            'type' => ProgramType::Camp->value,
            'name' => 'Summer Science Camp',
            'summary' => 'A week of experiments.',
            'age_min' => 6,
            'age_max' => 9,
            'is_published' => '1',
        ])
        ->assertRedirect();

    $program = Program::firstWhere('slug', 'summer-science-camp');

    expect($program)->not->toBeNull()
        ->and($program->type)->toBe(ProgramType::Camp)
        ->and($program->is_published)->toBeTrue();
});

test('the maximum age cannot be lower than the minimum age', function () {
    $this->actingAs(User::factory()->staff()->create())
        ->post(route('admin.programs.store'), [
            'type' => ProgramType::Club->value,
            'name' => 'Robotics Club',
            'summary' => 'Build robots.',
            'age_min' => 9,
            'age_max' => 6,
        ])
        ->assertSessionHasErrors('age_max');
});

test('staff can update a program', function () {
    $program = Program::factory()->create();

    $this->actingAs(User::factory()->staff()->create())
        ->put(route('admin.programs.update', $program), [
            'type' => ProgramType::Club->value,
            'name' => 'Renamed Club',
            'slug' => $program->slug,
            'summary' => 'New summary.',
        ])
        ->assertRedirect(route('admin.programs.edit', $program));

    expect($program->fresh())
        ->name->toBe('Renamed Club')
        ->is_published->toBeFalse();
});

test('deleting a program deletes its sessions', function () {
    $session = ProgramSession::factory()->create();

    $this->actingAs(User::factory()->staff()->create())
        ->delete(route('admin.programs.destroy', $session->program))
        ->assertRedirect(route('admin.programs.index'));

    expect(Program::count())->toBe(0)
        ->and(ProgramSession::count())->toBe(0);
});

test('staff see the Programs link in the sidebar, and parents do not', function () {
    $this->actingAs(User::factory()->staff()->create())
        ->get(route('dashboard'))
        ->assertSee(route('admin.programs.index'));

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertDontSee(route('admin.programs.index'));
});

test('the Programs link in the staff sidebar uses wire:navigate', function () {
    $html = $this->actingAs(User::factory()->staff()->create())
        ->get(route('dashboard'))
        ->getContent();

    // The opening <a> tag with this href. A quoted value can hold a ">", so the pattern skips quoted values.
    $tag = '<a\s(?:[^>"\']|"[^"]*"|\'[^\']*\')*';
    preg_match('/'.$tag.'href="'.preg_quote(route('admin.programs.index'), '/').'"(?:[^>"\']|"[^"]*"|\'[^\']*\')*>/', $html, $link);

    expect($link[0] ?? '')->toContain('wire:navigate');
});

test('the program service saves, changes and deletes a program without a request', function () {
    $programs = app(ProgramService::class);

    $program = $programs->create([
        'type' => ProgramType::Club,
        'name' => 'Chess Club',
        'slug' => 'chess-club',
        'summary' => 'Think ahead.',
    ]);

    $programs->update($program, ['name' => 'Chess & Logic Club']);

    expect($program->fresh()->name)->toBe('Chess & Logic Club');

    $programs->delete($program);

    expect(Program::count())->toBe(0);
});

/*
| Sessions
*/

test('users who are not staff cannot add sessions', function () {
    $program = Program::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('admin.programs.sessions.store', $program), [])
        ->assertForbidden();
});

test('staff can add a session with prices in dollars', function () {
    $program = Program::factory()->create();

    $this->actingAs(User::factory()->staff()->create())
        ->post(route('admin.programs.sessions.store', $program), [
            'label' => 'Winter 2027',
            'starts_on' => '2027-01-12',
            'ends_on' => '2027-03-16',
            'schedule' => 'Tuesdays, 3:00–4:30 PM',
            'price' => '150.00',
            'member_price' => '135.50',
            'capacity' => 12,
        ])
        ->assertRedirect(route('admin.programs.edit', $program));

    $session = $program->sessions()->sole();

    expect($session)
        ->label->toBe('Winter 2027')
        ->price_cents->toBe(15000)
        ->member_price_cents->toBe(13550);
});

test('a session cannot end before it starts', function () {
    $program = Program::factory()->create();

    $this->actingAs(User::factory()->staff()->create())
        ->post(route('admin.programs.sessions.store', $program), [
            'label' => 'Winter 2027',
            'starts_on' => '2027-03-16',
            'ends_on' => '2027-01-12',
            'price' => '150',
        ])
        ->assertSessionHasErrors('ends_on');
});

test('staff can update a session and clear the member price', function () {
    $session = ProgramSession::factory()->create();

    $this->actingAs(User::factory()->staff()->create())
        ->put(route('admin.sessions.update', $session), [
            'label' => 'Spring 2027',
            'starts_on' => '2027-04-06',
            'price' => '99.99',
            'member_price' => '',
        ])
        ->assertRedirect(route('admin.programs.edit', $session->program));

    expect($session->fresh())
        ->label->toBe('Spring 2027')
        ->price_cents->toBe(9999)
        ->member_price_cents->toBeNull();
});

test('staff can delete a session', function () {
    $session = ProgramSession::factory()->create();

    $this->actingAs(User::factory()->staff()->create())
        ->delete(route('admin.sessions.destroy', $session))
        ->assertRedirect(route('admin.programs.edit', $session->program));

    expect(ProgramSession::count())->toBe(0);
});

test('the session service adds a session to a program without a request', function () {
    $program = Program::factory()->create();

    $session = app(ProgramSessionService::class)->create($program, [
        'label' => 'Winter 2027',
        'starts_on' => '2027-01-12',
        'price_cents' => 15000,
    ]);

    expect($session->program->is($program))->toBeTrue()
        ->and($program->sessions()->sole()->price())->toBe('$150.00');
});
