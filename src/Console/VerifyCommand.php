<?php

namespace TruLaravelClub\BlscGuide\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use TruLaravelClub\BlscGuide\FeatureMap;
use TruLaravelClub\BlscGuide\TestResults;

class VerifyCommand extends Command
{
    protected $signature = 'guide:verify {feature : The feature key, such as big-little-scientists}';

    protected $description = 'Run the acceptance test of a feature and record the result in the guide';

    public function handle(FeatureMap $map, TestResults $results): int
    {
        $feature = $map->find($this->argument('feature'));

        if ($feature === null || $feature->testName() === null) {
            $this->components->error('That feature has no acceptance test.');

            return self::FAILURE;
        }

        $path = 'tests/Feature/'.$feature->testTemplate();

        if (! is_file(base_path($path))) {
            $this->components->error("[{$path}] is missing. Run `php artisan guide:start {$feature->key()}` first.");

            return self::FAILURE;
        }

        $result = Process::path(base_path())
            ->timeout(300)
            ->run([PHP_BINARY, 'artisan', 'test', $path], function (string $type, string $output): void {
                $this->output->write($output);
            });

        $results->record($feature->testName(), $result->successful());

        if ($result->failed()) {
            $this->components->error('The acceptance test fails. Read the first failure above and fix that first.');

            return self::FAILURE;
        }

        $this->components->info('The acceptance test passes. Open a pull request when every step is done.');

        return self::SUCCESS;
    }
}
