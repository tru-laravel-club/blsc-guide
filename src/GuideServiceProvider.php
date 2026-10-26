<?php

namespace TruLaravelClub\BlscGuide;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;
use TruLaravelClub\BlscGuide\Console\GuideCommand;
use TruLaravelClub\BlscGuide\Console\StartCommand;
use TruLaravelClub\BlscGuide\Console\VerifyCommand;

class GuideServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/blsc-guide.php', 'blsc-guide');

        $this->app->singleton(TestResults::class, fn ($app): TestResults => new TestResults(
            $app->make(Filesystem::class),
            $app['config']->get('blsc-guide.results_path') ?? storage_path('framework/cache/blsc-guide.json'),
        ));

        // Build a fresh map each time, so every request and command runs the checks again.
        $this->app->bind(FeatureMap::class, fn ($app): FeatureMap => FeatureMap::fromDirectory(
            $app['config']->get('blsc-guide.features_path'),
        ));
    }

    public function boot(): void
    {
        // Views do nothing on their own. Without the routes below, nobody can open them.
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'blsc-guide');

        // The guide is for club members on their own machines. It never loads on a server.
        if (! $this->app->environment('local')) {
            return;
        }

        $this->loadRoutesFrom(__DIR__.'/../routes/guide.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                GuideCommand::class,
                StartCommand::class,
                VerifyCommand::class,
            ]);
        }
    }
}
