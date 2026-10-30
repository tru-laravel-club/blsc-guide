<?php

use Illuminate\Filesystem\Filesystem;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\FeatureMap;
use TruLaravelClub\BlscGuide\Progress;
use TruLaravelClub\BlscGuide\Status;
use TruLaravelClub\BlscGuide\Step;

function step(bool $passes): Step
{
    return Step::make('A step')->check(fn () => $passes);
}

test('a feature is done when every checked step passes', function () {
    $feature = Feature::make('a')->steps([step(true), step(true), Step::make('No check')]);

    expect((new FeatureMap([$feature]))->status($feature))->toBe(Status::Done);
});

test('a feature is in progress when some steps pass', function () {
    $feature = Feature::make('a')->steps([step(true), step(false)]);

    expect((new FeatureMap([$feature]))->status($feature))->toBe(Status::InProgress);
});

test('a feature is ready when the features it needs are done', function () {
    $done = Feature::make('done')->steps([step(true)]);
    $next = Feature::make('next')->needs(['done'])->steps([step(false)]);

    expect((new FeatureMap([$done, $next]))->status($next))->toBe(Status::Ready);
});

test('a feature is blocked by a dependency that is not done, and lists it', function () {
    $open = Feature::make('open')->steps([step(false)]);
    $next = Feature::make('next')->needs(['open'])->steps([step(false)]);
    $map = new FeatureMap([$open, $next]);

    expect($map->status($next))->toBe(Status::Blocked)
        ->and($map->blockers($next))->toBe([$open]);
});

test('a feature without steps is not planned and blocks what needs it', function () {
    $unplanned = Feature::make('unplanned');
    $next = Feature::make('next')->needs(['unplanned'])->steps([step(false)]);
    $map = new FeatureMap([$unplanned, $next]);

    expect($map->status($unplanned))->toBe(Status::NotPlanned)
        ->and($map->status($next))->toBe(Status::Blocked);
});

test('a check that throws counts as not passing', function () {
    $feature = Feature::make('a')->steps([Step::make('Broken')->check(fn () => throw new RuntimeException)]);

    expect((new FeatureMap([$feature]))->status($feature))->toBe(Status::Ready);
});

test('it reports unknown dependencies and loops', function () {
    $map = new FeatureMap([
        Feature::make('a')->needs(['b']),
        Feature::make('b')->needs(['a']),
        Feature::make('c')->needs(['missing']),
    ]);

    expect($map->problems())->toContain(
        '[a] depends on itself through a loop.',
        '[c] needs [missing], but no feature has that key.',
    );
});

test('a dependency loop does not hang the status check', function () {
    $a = Feature::make('a')->needs(['b'])->steps([step(false)]);
    $b = Feature::make('b')->needs(['a'])->steps([step(false)]);

    expect((new FeatureMap([$a, $b]))->status($a))->toBe(Status::Blocked);
});

test('two features cannot share a key', function () {
    new FeatureMap([Feature::make('a'), Feature::make('a')]);
})->throws(InvalidArgumentException::class);

test('a marked feature is done and unlocks what needs it', function () {
    $progress = new Progress(new Filesystem, sys_get_temp_dir().'/blsc-guide-progress-'.uniqid());
    $merged = Feature::make('merged')->steps([step(false)]);
    $next = Feature::make('next')->needs(['merged'])->steps([step(false)]);

    $progress->mark('merged');
    $map = new FeatureMap([$merged, $next], $progress);

    expect($map->status($merged))->toBe(Status::Done)
        ->and($map->isMarkedDone($merged))->toBeTrue()
        ->and($map->markedOn($merged))->toBe(date('Y-m-d'))
        ->and($map->status($next))->toBe(Status::Ready);
});

test('each marked feature gets its own file, and marking again keeps the first date', function () {
    $directory = sys_get_temp_dir().'/blsc-guide-progress-'.uniqid();
    $progress = new Progress(new Filesystem, $directory);

    $progress->mark('saturday-programs');
    file_put_contents($directory.'/saturday-programs', "2026-01-01\n");
    $progress->mark('saturday-programs');
    $progress->mark('layout');
    $progress->unmark('layout');

    expect(scandir($directory))->toBe(['.', '..', 'saturday-programs'])
        ->and($progress->markedOn('saturday-programs'))->toBe('2026-01-01')
        ->and($progress->isMarked('layout'))->toBeFalse();
});

test('a progress file name must be a feature key', function () {
    (new Progress(new Filesystem, sys_get_temp_dir()))->fileFor('../secrets');
})->throws(InvalidArgumentException::class);

test('step counts leave out steps without a check', function () {
    $feature = Feature::make('a')->steps([step(true), Step::make('No check')]);
    $map = new FeatureMap([$feature]);

    expect($map->completedSteps($feature))->toBe(1)
        ->and($map->checkableSteps($feature))->toBe(1);
});
