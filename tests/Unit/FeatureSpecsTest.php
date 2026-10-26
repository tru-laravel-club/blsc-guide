<?php

use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\FeatureMap;

test('the BLSC feature map has no planning mistakes', function () {
    expect(app(FeatureMap::class)->problems())->toBe([]);
});

test('every acceptance test template that a feature names exists', function () {
    $features = array_filter(app(FeatureMap::class)->all(), fn (Feature $f) => $f->testTemplate() !== null);

    expect($features)->not->toBeEmpty();

    foreach ($features as $feature) {
        expect(config('blsc-guide.tests_path').'/'.$feature->testTemplate())->toBeFile();
    }
});
