<?php

use App\Enums\ProgramType;
use App\Models\Program;
use App\Models\ProgramSession;
use Database\Seeders\ProgramSeeder;

/*
| Acceptance test for the "Programs and sessions" feature.
|
| A program is something BLSC offers, such as a club. A session is one run of it,
| with its own dates, prices and spots. Prices are stored in cents.
|
| The two rules about the type use the ProgramType enum. Later, the Program
| categories feature moves the types into a table that staff manage. It deletes
| those two rules.
*/

test('each program type has a label and a plural label', function () {
    expect(ProgramType::cases())->toHaveCount(5)
        ->and(ProgramType::Camp->label())->toBe('Camp')
        ->and(ProgramType::Party->pluralLabel())->toBe('Parties')
        ->and(ProgramType::BigNightOut->value)->toBe('big_night_out')
        ->and(ProgramType::BigNightOut->label())->toBe('BIG Night Out');
});

test('a program casts its type to the enum', function () {
    $program = Program::factory()->camp()->create()->fresh();

    expect($program->type)->toBe(ProgramType::Camp)
        ->and($program->is_published)->toBeTrue();
});

test('the published scope leaves out drafts', function () {
    Program::factory()->create(['name' => 'Robotics Club']);
    Program::factory()->unpublished()->create(['name' => 'Draft Club']);

    expect(Program::published()->pluck('name')->all())->toBe(['Robotics Club']);
});

test('a program shows its age range as text', function () {
    $range = fn (?int $min, ?int $max) => Program::factory()->make(['age_min' => $min, 'age_max' => $max])->ageRange();

    expect($range(6, 9))->toBe('Ages 6–9')
        ->and($range(9, null))->toBe('Ages 9+')
        ->and($range(null, 12))->toBe('Up to age 12')
        ->and($range(null, null))->toBeNull();
});

test('a program has many sessions in date order', function () {
    $program = Program::factory()->create();
    ProgramSession::factory()->for($program)->create(['label' => 'Spring', 'starts_on' => today()->addMonths(5), 'ends_on' => null]);
    ProgramSession::factory()->for($program)->create(['label' => 'Winter', 'starts_on' => today()->addMonth(), 'ends_on' => null]);

    expect($program->sessions->pluck('label')->all())->toBe(['Winter', 'Spring'])
        ->and($program->sessions->first()->program->is($program))->toBeTrue();
});

test('upcoming sessions leave out the sessions that have ended', function () {
    $program = Program::factory()->create();
    ProgramSession::factory()->for($program)->create(['label' => 'Next term']);
    ProgramSession::factory()->for($program)->past()->create(['label' => 'Last term']);
    ProgramSession::factory()->for($program)->create(['label' => 'Today only', 'starts_on' => today(), 'ends_on' => null]);
    ProgramSession::factory()->for($program)->create(['label' => 'Yesterday only', 'starts_on' => today()->subDay(), 'ends_on' => null]);

    expect($program->upcomingSessions->pluck('label')->sort()->values()->all())->toBe(['Next term', 'Today only'])
        ->and(ProgramSession::upcoming()->count())->toBe(2);
});

test('a session stores prices in cents and shows them in dollars', function () {
    $session = ProgramSession::factory()->make(['price_cents' => 15000, 'member_price_cents' => 13550]);

    expect($session->price())->toBe('$150.00')
        ->and($session->memberPrice())->toBe('$135.50')
        ->and(ProgramSession::factory()->make(['member_price_cents' => null])->memberPrice())->toBeNull();
});

test('a session shows its dates as text', function () {
    $dates = fn (string $starts, ?string $ends) => ProgramSession::factory()->make(['starts_on' => $starts, 'ends_on' => $ends])->dates();

    expect($dates('2026-10-06', '2026-12-08'))->toBe('Oct 6 – Dec 8, 2026')
        ->and($dates('2026-10-23', null))->toBe('Fri, Oct 23, 2026')
        ->and($dates('2026-10-23', '2026-10-23'))->toBe('Fri, Oct 23, 2026');
});

test('registration is open only between the opening and the closing time', function () {
    $open = fn (?string $opens, ?string $closes) => ProgramSession::factory()
        ->make(['registration_opens_at' => $opens, 'registration_closes_at' => $closes])
        ->isRegistrationOpen();

    expect($open(null, null))->toBeTrue()
        ->and($open('yesterday', 'tomorrow'))->toBeTrue()
        ->and($open('tomorrow', null))->toBeFalse()
        ->and($open(null, 'yesterday'))->toBeFalse();
});

test('the seeder adds the Fall 2026 programs with their sessions', function () {
    $this->seed(ProgramSeeder::class);

    $club = Program::firstWhere('slug', 'junior-robotics-coding-club');

    expect(Program::count())->toBe(7)
        ->and($club->ageRange())->toBe('Ages 6–9')
        ->and($club->sessions)->toHaveCount(1)
        ->and($club->sessions->first()->price())->toBe('$150.00');
});
