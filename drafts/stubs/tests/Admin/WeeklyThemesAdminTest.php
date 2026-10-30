<?php

use App\Models\User;
use App\Models\WeeklyTheme;

test('users who are not staff cannot manage weekly themes', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.weekly-themes.index'))
        ->assertForbidden();
});

test('staff see the themes in date order', function () {
    WeeklyTheme::factory()->create(['date' => '2026-11-12', 'title' => 'Sticky Things']);
    WeeklyTheme::factory()->create(['date' => '2026-11-05', 'title' => 'Up in the Air']);

    $this->actingAs(User::factory()->staff()->create())
        ->get(route('admin.weekly-themes.index'))
        ->assertOk()
        ->assertSeeInOrder(['Up in the Air', 'Sticky Things']);
});

test('staff can set the theme for a Thursday', function () {
    $this->actingAs(User::factory()->staff()->create())
        ->post(route('admin.weekly-themes.store'), [
            'date' => '2026-11-05',
            'title' => 'Up in the Air',
        ])
        ->assertRedirect();

    expect(WeeklyTheme::sole()->title)->toBe('Up in the Air');
});

test('the date must be a Thursday', function () {
    $this->actingAs(User::factory()->staff()->create())
        ->post(route('admin.weekly-themes.store'), [
            'date' => '2026-11-06',
            'title' => 'Friday Theme',
        ])
        ->assertSessionHasErrors('date');
});

test('a Thursday cannot have two themes', function () {
    WeeklyTheme::factory()->create(['date' => '2026-11-05']);

    $this->actingAs(User::factory()->staff()->create())
        ->post(route('admin.weekly-themes.store'), [
            'date' => '2026-11-05',
            'title' => 'Second Theme',
        ])
        ->assertSessionHasErrors('date');
});

test('staff can rename a theme and keep its date', function () {
    $theme = WeeklyTheme::factory()->create(['date' => '2026-11-05']);

    $this->actingAs(User::factory()->staff()->create())
        ->put(route('admin.weekly-themes.update', $theme), [
            'date' => '2026-11-05',
            'title' => 'Renamed Theme',
        ])
        ->assertSessionHasNoErrors();

    expect($theme->fresh()->title)->toBe('Renamed Theme');
});

test('staff can delete a theme', function () {
    $theme = WeeklyTheme::factory()->create();

    $this->actingAs(User::factory()->staff()->create())
        ->delete(route('admin.weekly-themes.destroy', $theme))
        ->assertRedirect(route('admin.weekly-themes.index'));

    expect(WeeklyTheme::count())->toBe(0);
});
