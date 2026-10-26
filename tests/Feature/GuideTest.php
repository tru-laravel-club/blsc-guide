<?php

use TruLaravelClub\BlscGuide\TestResults;

test('the guide page lists the features', function () {
    $this->get('/guide')
        ->assertOk()
        ->assertSee('BLSC contributor guide')
        ->assertSee('BIG Little Scientists page')
        ->assertSee('Club and camp registration');
});

test('a feature page shows its steps and what blocks it', function () {
    $this->get('/guide/club-camp-registration')
        ->assertOk()
        ->assertSee('This feature is blocked.')
        ->assertSee('Online payments');

    $this->get('/guide/big-little-scientists')
        ->assertOk()
        ->assertSee('php artisan make:model WeeklyTheme -mf');
});

test('an unknown feature is not found', function () {
    $this->get('/guide/not-a-feature')->assertNotFound();
});

test('the guide command shows the map and one feature', function () {
    $this->artisan('guide')->assertSuccessful();
    $this->artisan('guide', ['feature' => 'science-articles'])
        ->expectsOutputToContain('php artisan make:model Article -mf')
        ->assertSuccessful();
    $this->artisan('guide', ['feature' => 'nope'])->assertFailed();
});

test('guide:start copies the acceptance test into the app', function () {
    $target = base_path('tests/Feature/SaturdayProgramsTest.php');
    @unlink($target);

    $this->artisan('guide:start', ['feature' => 'saturday-programs', '--force' => true])->assertSuccessful();

    expect($target)->toBeFile();
    unlink($target);
});

test('guide:start refuses a feature that is not planned', function () {
    $this->artisan('guide:start', ['feature' => 'payments'])->assertFailed();
});

test('test results are remembered', function () {
    $results = app(TestResults::class);
    $results->record('ExampleTest', true);

    expect($results->passed('ExampleTest'))->toBeTrue()
        ->and($results->passed('OtherTest'))->toBeFalse();
});
