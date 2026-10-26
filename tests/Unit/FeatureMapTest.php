<?php

use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\FeatureMap;
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
