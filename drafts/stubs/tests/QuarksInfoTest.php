<?php

use App\Enums\ProgramType;
use App\Models\Program;

test('the page lists published QUARKS programs only', function () {
    Program::factory()->create(['type' => ProgramType::Quarks, 'name' => 'QUARKS Physics']);
    Program::factory()->club()->create(['name' => 'Robotics Club']);

    $this->get(route('quarks.index'))
        ->assertOk()
        ->assertSee('QUARKS Physics')
        ->assertDontSee('Robotics Club');
});

test('the page explains who QUARKS is for', function () {
    $this->get(route('quarks.index'))
        ->assertOk()
        ->assertSee('Grade 8')
        ->assertSee('TRU');
});

test('the main menu links to the page', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee(route('quarks.index'));
});
