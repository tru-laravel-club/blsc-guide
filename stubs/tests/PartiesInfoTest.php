<?php

use App\Enums\ProgramType;
use App\Models\Program;

test('the page lists the published party themes', function () {
    Program::factory()->create(['type' => ProgramType::Party, 'name' => 'Dinosaur Dig Party']);
    Program::factory()->unpublished()->create(['type' => ProgramType::Party, 'name' => 'Draft Party']);

    $this->get(route('parties'))
        ->assertOk()
        ->assertSee('Dinosaur Dig Party')
        ->assertDontSee('Draft Party');
});

test('the page does not list other program types', function () {
    Program::factory()->camp()->create(['name' => 'Summer Science Camp']);

    $this->get(route('parties'))
        ->assertOk()
        ->assertDontSee('Summer Science Camp');
});

test('the page explains the deposit', function () {
    $this->get(route('parties'))
        ->assertOk()
        ->assertSee('$25 deposit');
});

test('the main menu links to the page', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee(route('parties'));
});
