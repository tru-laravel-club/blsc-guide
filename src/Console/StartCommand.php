<?php

namespace TruLaravelClub\BlscGuide\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\FeatureMap;

class StartCommand extends Command
{
    protected $signature = 'guide:start {feature : The feature key, such as big-little-scientists}
                            {--force : Start even if the feature is blocked}';

    protected $description = 'Start a feature: check what it needs and copy its acceptance test into the app';

    public function handle(FeatureMap $map, Filesystem $files): int
    {
        $feature = $map->find($this->argument('feature'));

        if ($feature === null) {
            $this->components->error('No feature has that key. Run `php artisan guide` to see them all.');

            return self::FAILURE;
        }

        if (! $feature->isPlanned()) {
            $this->components->error('A lead has not written the steps for this feature yet. Pick another one.');

            return self::FAILURE;
        }

        $blockers = $map->blockers($feature);

        if ($blockers !== []) {
            $this->components->warn('This feature needs work that is not done yet:');
            $this->components->bulletList(array_map(
                fn (Feature $blocker): string => "{$blocker->key()} ({$map->status($blocker)->label()})",
                $blockers,
            ));

            if (! $this->option('force') && ! $this->confirm('Start anyway? You may not be able to finish until those are merged.')) {
                return self::FAILURE;
            }
        }

        $this->copyTest($feature, $files);

        $this->components->info("Started [{$feature->key()}].");
        $this->line("Follow the steps with <comment>php artisan guide {$feature->key()}</comment>, or open <comment>".url(config('blsc-guide.path').'/'.$feature->key()).'</comment>.');

        return self::SUCCESS;
    }

    private function copyTest(Feature $feature, Filesystem $files): void
    {
        if ($feature->testTemplate() === null) {
            $this->components->warn('This feature has no acceptance test yet. Write your own test as you build it.');

            return;
        }

        $source = config('blsc-guide.tests_path').'/'.$feature->testTemplate();
        $target = base_path('tests/Feature/'.$feature->testTemplate());

        if ($files->exists($target)) {
            $this->components->twoColumnDetail('Acceptance test', 'already in tests/Feature, not changed');

            return;
        }

        $files->ensureDirectoryExists(dirname($target));
        $files->copy($source, $target);
        $this->components->twoColumnDetail('Acceptance test', 'tests/Feature/'.$feature->testTemplate());
        $this->line('  This test fails now. Your job is to make it pass. Do not change what it checks.');
    }
}
