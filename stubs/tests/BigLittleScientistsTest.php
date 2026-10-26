<?php

use App\Models\WeeklyTheme;

test('the page lists the weekly themes in date order', function () {
    WeeklyTheme::factory()->create(['date' => '2026-10-15', 'title' => 'Fun in Fall']);
    WeeklyTheme::factory()->create(['date' => '2026-10-08', 'title' => 'Magical Music']);

    $this->get(route('big-little-scientists'))
        ->assertOk()
        ->assertSeeInOrder(['Magical Music', 'Fun in Fall']);
});

test('the page tells parents that they do not need to sign up', function () {
    $this->get(route('big-little-scientists'))
        ->assertOk()
        ->assertSee('No need to sign up');
});

test('the main menu links to the page', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee(route('big-little-scientists'));
});
