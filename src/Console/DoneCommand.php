<?php

namespace TruLaravelClub\BlscGuide\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use TruLaravelClub\BlscGuide\FeatureMap;
use TruLaravelClub\BlscGuide\Progress;

class DoneCommand extends Command
{
    protected $signature = 'guide:done
        {feature : The feature key, such as big-little-scientists}
        {--undo : Remove the mark}';

    protected $description = 'Mark a feature as done, so the features that need it unlock';

    public function handle(FeatureMap $map, Progress $progress): int
    {
        $feature = $map->find($this->argument('feature'));

        if ($feature === null) {
            $this->components->error('There is no feature with that key. Run `php artisan guide` to see the keys.');

            return self::FAILURE;
        }

        if ($this->option('undo')) {
            $progress->unmark($feature->key());
            $this->components->info("{$feature->name()} is no longer marked as done.");
        } else {
            $progress->mark($feature->key());
            $this->components->info("{$feature->name()} is marked as done.");
        }

        $this->line('  Commit '.Str::after($progress->fileFor($feature->key()), base_path().'/').' and push it, so every contributor sees the change.');

        return self::SUCCESS;
    }
}
