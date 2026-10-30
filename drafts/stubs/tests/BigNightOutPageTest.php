<?php

use App\Enums\ProgramType;
use App\Models\Program;

test('the page lists published BIG Night Out events only', function () {
    Program::factory()->create(['type' => ProgramType::BigNightOut, 'name' => 'Murder Mystery Night']);
    Program::factory()->unpublished()->create(['type' => ProgramType::BigNightOut, 'name' => 'Draft Night']);
    Program::factory()->club()->create(['name' => 'Robotics Club']);

    $this->get(route('big-night-out.index'))
        ->assertOk()
        ->assertSee('Murder Mystery Night')
        ->assertDontSee('Draft Night')
        ->assertDontSee('Robotics Club');
});

test('the page says the events are for adults only', function () {
    $this->get(route('big-night-out.index'))
        ->assertOk()
        ->assertSee('19');
});

test('the camps page does not show the adults-only introduction', function () {
    $this->get(route('camps.index'))
        ->assertOk()
        ->assertDontSee('19 and up');
});

test('the main menu links to the page', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee(route('big-night-out.index'));
});
