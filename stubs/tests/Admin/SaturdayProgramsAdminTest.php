<?php

use App\Models\SaturdayEvent;
use App\Models\User;

test('users who are not staff cannot manage Saturday events', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.saturday-events.index'))
        ->assertForbidden();
});

test('staff see past and upcoming events', function () {
    SaturdayEvent::factory()->create(['date' => today()->addWeek(), 'title' => 'Spooktacular Science']);
    SaturdayEvent::factory()->create(['date' => today()->subWeek(), 'title' => 'Last Week Show']);

    $this->actingAs(User::factory()->staff()->create())
        ->get(route('admin.saturday-events.index'))
        ->assertOk()
        ->assertSee('Spooktacular Science')
        ->assertSee('Last Week Show');
});

test('staff can add an event', function () {
    $this->actingAs(User::factory()->staff()->create())
        ->post(route('admin.saturday-events.store'), [
            'date' => '2026-10-31',
            'title' => 'Spooktacular Science',
            'schedule' => 'Shows at 11:00 AM and 1:30 PM',
        ])
        ->assertRedirect();

    $event = SaturdayEvent::sole();

    expect($event->title)->toBe('Spooktacular Science');
    expect($event->date->toDateString())->toBe('2026-10-31');
});

test('an event needs a date and a title', function () {
    $this->actingAs(User::factory()->staff()->create())
        ->post(route('admin.saturday-events.store'), [])
        ->assertSessionHasErrors(['date', 'title']);
});

test('staff can edit an event', function () {
    $event = SaturdayEvent::factory()->create();

    $this->actingAs(User::factory()->staff()->create())
        ->put(route('admin.saturday-events.update', $event), [
            'date' => $event->date->toDateString(),
            'title' => 'Renamed Show',
        ])
        ->assertRedirect();

    expect($event->fresh()->title)->toBe('Renamed Show');
});

test('staff can delete an event', function () {
    $event = SaturdayEvent::factory()->create();

    $this->actingAs(User::factory()->staff()->create())
        ->delete(route('admin.saturday-events.destroy', $event))
        ->assertRedirect(route('admin.saturday-events.index'));

    expect(SaturdayEvent::count())->toBe(0);
});
