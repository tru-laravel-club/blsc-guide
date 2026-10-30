<?php

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use TruLaravelClub\BlscGuide\FeatureMap;
use TruLaravelClub\BlscGuide\Progress;
use TruLaravelClub\BlscGuide\Status;
use TruLaravelClub\BlscGuide\TestResults;

test('the guide page lists the features', function () {
    $this->get('/guide')
        ->assertOk()
        ->assertSee('BLSC contributor guide')
        ->assertSee('Science articles')
        ->assertSee('Club and camp registration');
});

test('a feature page shows its steps and what blocks it', function () {
    $this->get('/guide/registrations')
        ->assertOk()
        ->assertSee('This feature is blocked.')
        ->assertSee('Online payments');

    $this->get('/guide/articles')
        ->assertOk()
        ->assertSee('php artisan make:model Article -mf');
});

test('an unknown feature is not found', function () {
    $this->get('/guide/not-a-feature')->assertNotFound();
});

test('the guide command shows the map and one feature', function () {
    $this->artisan('guide')->assertSuccessful();
    $this->artisan('guide', ['feature' => 'articles'])
        ->expectsOutputToContain('php artisan make:model Article -mf')
        ->expectsOutputToContain("\$table->string('slug')->unique();")
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

test('guide:start shows who is assigned to the issue', function () {
    Http::fake(['api.github.com/repos/tru-laravel-club/blsc-website/issues/12' => Http::response(['assignees' => [['login' => 'ada']]])]);

    $this->artisan('guide:start', ['feature' => 'layout'])
        ->expectsOutputToContain('assigned to @ada')
        ->assertSuccessful();
});

test('guide:start asks before it starts an issue with no assignee', function () {
    Http::fake(['api.github.com/*' => Http::response(['assignees' => []])]);

    $this->artisan('guide:start', ['feature' => 'layout'])
        ->expectsOutputToContain('has no assignee yet')
        ->expectsConfirmation('Start without an assignment?', 'no')
        ->assertFailed();
});

test('guide:start continues with a warning when GitHub cannot be read', function () {
    Http::fake(['api.github.com/*' => Http::response([], 404)]);

    $this->artisan('guide:start', ['feature' => 'layout'])
        ->expectsOutputToContain('Could not read issue #12')
        ->assertSuccessful();
});

test('guide:start refuses a feature that is not planned', function () {
    $this->artisan('guide:start', ['feature' => 'payments'])->assertFailed();
});

test('guide:verify runs the tests without the values from .env', function () {
    $env = app()->environmentFilePath();
    $backup = is_file($env) ? file_get_contents($env) : null;
    file_put_contents($env, "APP_ENV=local\nDB_DATABASE=blsc\n");
    $this->artisan('guide:start', ['feature' => 'saturday-programs', '--force' => true]);
    Process::fake();

    try {
        $this->artisan('guide:verify', ['feature' => 'saturday-programs'])->assertSuccessful();
    } finally {
        $backup === null ? unlink($env) : file_put_contents($env, $backup);
    }

    Process::assertRan(fn (PendingProcess $process) => $process->environment === ['APP_ENV' => false, 'DB_DATABASE' => false]);
});

test('test results are remembered', function () {
    $results = app(TestResults::class);
    $results->record('ExampleTest', true);

    expect($results->passed('ExampleTest'))->toBeTrue()
        ->and($results->passed('OtherTest'))->toBeFalse();
});

test('a contributor can mark a feature as done on its page', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
    $status = fn (string $key): Status => app(FeatureMap::class)->status(app(FeatureMap::class)->find($key));

    expect($status('articles'))->toBe(Status::Blocked);
    $this->get('/guide/layout')->assertOk()->assertSee('Mark as done');

    $this->post('/guide/layout/done')->assertRedirect('/guide/layout');

    expect($status('layout'))->toBe(Status::Done)
        ->and($status('articles'))->toBe(Status::Ready);
    $this->get('/guide/layout')
        ->assertSee('This feature was marked as done')
        ->assertSee('How it was built')
        ->assertDontSee('Before you start')
        ->assertDontSee('git switch -c feature/layout')
        ->assertSee('Remove the mark');

    $this->delete('/guide/layout/done')->assertRedirect('/guide/layout');

    expect($status('articles'))->toBe(Status::Blocked);
});

test('marking an unknown feature is not found', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);

    $this->post('/guide/not-a-feature/done')->assertNotFound();
});

test('guide:done marks and unmarks a feature', function () {
    $this->artisan('guide:done', ['feature' => 'layout'])
        ->expectsOutputToContain('Commit')
        ->assertSuccessful();

    expect(app(Progress::class)->isMarked('layout'))->toBeTrue();

    $this->artisan('guide:done', ['feature' => 'layout', '--undo' => true])->assertSuccessful();

    expect(app(Progress::class)->isMarked('layout'))->toBeFalse();

    $this->artisan('guide:done', ['feature' => 'nope'])->assertFailed();
});

test('a feature page teaches before it gives the answer', function () {
    $this->get('/guide/saturday-programs')
        ->assertOk()
        ->assertSeeInOrder(['How it works', 'Learn first', 'Before you start', 'git pull upstream main', 'git switch -c feature/saturday-programs', 'Build it', 'Finish', 'git merge upstream/main'])
        ->assertSee('Think first')
        ->assertSee('Try it')
        ->assertSee('git commit -m "Add the SaturdayEvent model"', false)
        ->assertSeeInOrder(['Hint 1', 'Hint 2', 'Show the answer'])
        ->assertSee('<code>/saturdays</code>', false)
        ->assertSeeInOrder(['Learn first', 'Laravel From Scratch, episode 3: Routing 101', 'Build it'])
        ->assertSee('https://laravel.com/docs/routing#named-routes')
        ->assertDontSee('<script>', false);
});

test('the guide command hides answers until a member asks for them', function () {
    $this->artisan('guide', ['feature' => 'saturday-programs'])
        ->expectsOutputToContain('Think first:')
        ->expectsOutputToContain('git switch -c feature/saturday-programs')
        ->expectsOutputToContain('Add the SaturdayEvent model')
        ->doesntExpectOutputToContain('php artisan make:model SaturdayEvent -mf')
        ->assertSuccessful();

    $this->artisan('guide', ['feature' => 'saturday-programs', '--answers' => true])
        ->expectsOutputToContain('php artisan make:model SaturdayEvent -mf')
        ->assertSuccessful();
});

test('the git habits page explains the workflow', function () {
    $this->get('/guide/git')
        ->assertOk()
        ->assertSeeInOrder(['Your two remotes', 'Start every feature from the latest main', 'Look before you commit', 'Keep your branch up to date', 'Resolve a merge conflict', 'The usual cases', 'git merge --abort', 'Never commit secrets'])
        ->assertSee('git branch -D feature/program-categories');
});

test('the reactive way page compares components with controllers', function () {
    $this->get('/guide/reactive')
        ->assertOk()
        ->assertSeeInOrder(['Two ways to build a page', 'A Livewire component is a controller that stays on the page', 'One backend, two front doors', 'What happens underneath', 'The security rule', 'When to choose which'])
        ->assertSee('Hotwire');
});

test('the start page tells new contributors the story', function () {
    $this->get('/guide/start')
        ->assertOk()
        ->assertSeeInOrder(['The science centre', 'Why the club builds it', 'What you learn', 'Package managers', 'What we build', 'How we build it', 'Build for change', 'Your first hour']);

    $this->get('/guide')->assertSee(route('blsc-guide.start'));
});
