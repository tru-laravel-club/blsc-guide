<?php

namespace TruLaravelClub\BlscGuide\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use TruLaravelClub\BlscGuide\Feature;
use TruLaravelClub\BlscGuide\FeatureMap;

class StartCommand extends Command
{
    protected $signature = 'guide:start {feature : The feature key, such as big-little-scientists}
                            {--force : Start even if the feature is blocked or its issue is not assigned}';

    protected $description = 'Start a feature: check what it needs and copy its acceptance test into the app';

    public function handle(FeatureMap $map, Filesystem $files): int
    {
        $feature = $map->find($this->argument('feature'));

        if ($feature === null) {
            $this->components->error('No feature has that key. Run `php artisan guide` to see them all.');

            return self::FAILURE;
        }

        if (! $feature->isPlanned()) {
            $this->components->error('A maintainer has not written the steps for this feature yet. Pick another one.');

            return self::FAILURE;
        }

        if (! $this->checkIssue($feature)) {
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
        $this->line("Follow the steps with <comment>php artisan guide {$feature->key()}</comment>, or open <comment>".route('blsc-guide.show', $feature->key()).'</comment>.');

        return self::SUCCESS;
    }

    /**
     * Warn when the GitHub issue of the feature is not assigned, so two contributors do not build the same feature.
     */
    private function checkIssue(Feature $feature): bool
    {
        $number = $feature->issueNumber();

        if ($number === null) {
            return true;
        }

        $url = rtrim((string) config('blsc-guide.repository'), '/')."/issues/{$number}";
        $assignees = $this->assignees($number);

        if ($assignees === null) {
            $this->components->warn("Could not read issue #{$number}. Check on GitHub that a maintainer assigned it to you: {$url}");

            return true;
        }

        if ($assignees === []) {
            $this->components->warn("Issue #{$number} has no assignee yet. Ask for it on GitHub, and wait until a maintainer assigns it to you: {$url}");

            return $this->option('force') || $this->confirm('Start without an assignment?');
        }

        $names = implode(', ', array_map(fn (string $login): string => '@'.$login, $assignees));
        $this->components->twoColumnDetail("Issue #{$number}", "assigned to {$names}");
        $this->line('  If that is not you, stop now and pick another feature.');

        return true;
    }

    /**
     * The GitHub logins assigned to an issue, or null when GitHub cannot be read.
     *
     * @return list<string>|null
     */
    private function assignees(int $number): ?array
    {
        $repository = Str::after((string) config('blsc-guide.repository'), 'github.com/');
        $token = config('blsc-guide.github_token');

        try {
            $response = Http::acceptJson()
                ->timeout(5)
                ->when(is_string($token) && $token !== '', fn ($http) => $http->withToken((string) $token))
                ->get("https://api.github.com/repos/{$repository}/issues/{$number}");
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        return array_values(array_filter(
            array_map(fn (mixed $assignee): mixed => is_array($assignee) ? ($assignee['login'] ?? null) : null, (array) $response->json('assignees', [])),
            'is_string',
        ));
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
