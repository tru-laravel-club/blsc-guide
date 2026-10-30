<?php

use App\Models\SaturdayEvent;

test('the page lists upcoming events and hides past ones', function () {
    SaturdayEvent::factory()->create(['date' => today()->addWeek(), 'title' => 'Spooktacular Science']);
    SaturdayEvent::factory()->create(['date' => today()->subWeek(), 'title' => 'Last Week Show']);

    $this->get(route('saturday-programs'))
        ->assertOk()
        ->assertSee('Spooktacular Science')
        ->assertDontSee('Last Week Show');
});

test('an event happening today is still listed', function () {
    SaturdayEvent::factory()->create(['date' => today(), 'title' => 'Today Show']);

    $this->get(route('saturday-programs'))->assertSee('Today Show');
});

test('the page says that events are free with admission', function () {
    $this->get(route('saturday-programs'))
        ->assertOk()
        ->assertSee('Free with admission');
});

test('the main menu links to the page', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee(route('saturday-programs'));
});
