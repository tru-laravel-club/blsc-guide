<?php

namespace TruLaravelClub\BlscGuide\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use TruLaravelClub\BlscGuide\GuideServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [GuideServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        // The guide only boots in the local environment.
        $app['env'] = 'local';
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('blsc-guide.results_path', sys_get_temp_dir().'/blsc-guide-test-'.getmypid().'.json');
        $app['config']->set('blsc-guide.progress_path', sys_get_temp_dir().'/blsc-guide-progress-'.uniqid());

        // Fixed sample features, so changes to the real BLSC map do not break these tests.
        $app['config']->set('blsc-guide.features_path', __DIR__.'/fixtures/features');
        $app['config']->set('blsc-guide.tests_path', __DIR__.'/fixtures/stubs');
    }
}
